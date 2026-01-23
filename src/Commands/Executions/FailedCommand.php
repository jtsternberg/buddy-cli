<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Executions;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FailedCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('executions:failed')
            ->setDescription('Show failed action details from an execution')
            ->addArgument('execution-id', InputArgument::REQUIRED, 'Execution ID')
            ->addOption('analyze', null, InputOption::VALUE_NONE, 'Extract and summarize error patterns from logs')
            ->setHelp(<<<'HELP'
Shows details and logs for failed actions in an execution.

Filters the execution to only show actions that failed, then fetches and
displays the full logs for each failed action to help diagnose the issue.

Options:
      --analyze  Extract and categorize error patterns from logs

Examples:
  buddy executions:failed 67890 --pipeline=12345
  buddy executions:failed 67890 --pipeline=12345 --analyze
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        $this->addPipelineOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = $this->requirePipeline($input);
        $executionId = (int) $input->getArgument('execution-id');

        $execution = $this->getBuddyService()->getExecution($workspace, $project, $pipelineId, $executionId);

        if ($this->isJsonOutput($input)) {
            // Filter to only failed actions
            $failedActions = array_filter(
                $execution['action_executions'] ?? [],
                fn($a) => ($a['status'] ?? '') === 'FAILED'
            );
            $this->outputJson($output, array_values($failedActions));
            return self::SUCCESS;
        }

        $actionExecutions = $execution['action_executions'] ?? [];
        $failedActions = array_filter($actionExecutions, fn($a) => ($a['status'] ?? '') === 'FAILED');

        if (empty($failedActions)) {
            $output->writeln('<comment>No failed actions in this execution.</comment>');
            return self::SUCCESS;
        }

        if ($input->getOption('analyze')) {
            $this->outputAnalysis($output, $workspace, $project, $pipelineId, $executionId, array_values($failedActions));
            return self::SUCCESS;
        }

        foreach ($failedActions as $actionExec) {
            $output->writeln('');
            $output->writeln(sprintf('<error>Failed Action: %s</error>', $actionExec['action']['name'] ?? 'Unknown'));

            $data = [
                'Type' => $actionExec['action']['type'] ?? '-',
                'Started' => $this->formatTime($actionExec['start_date'] ?? null),
                'Finished' => $this->formatTime($actionExec['finish_date'] ?? null),
            ];

            TableFormatter::keyValue($output, $data);

            // Fetch action details with logs
            $actionId = $actionExec['action']['id'] ?? null;
            if ($actionId !== null) {
                try {
                    $actionDetails = $this->getBuddyService()->getActionExecution(
                        $workspace,
                        $project,
                        $pipelineId,
                        $executionId,
                        (int) $actionId
                    );

                    $logs = $actionDetails['log'] ?? [];
                    if (!empty($logs)) {
                        $output->writeln('');
                        $output->writeln('<comment>Logs:</comment>');
                        foreach ($logs as $line) {
                            $output->writeln($line);
                        }
                    }
                } catch (\Exception $e) {
                    // Skip if we can't fetch logs
                }
            }
        }

        return self::SUCCESS;
    }

    private function outputAnalysis(OutputInterface $output, string $workspace, string $project, int $pipelineId, int $executionId, array $failedActions): void
    {
        $allErrors = [];

        foreach ($failedActions as $action) {
            $actionName = $action['action']['name'] ?? 'Unknown';
            $actionId = $action['action']['id'] ?? null;

            if ($actionId === null) {
                $allErrors['No Logs'][] = [
                    'action' => $actionName,
                    'detail' => 'Action failed but no action ID available',
                ];
                continue;
            }

            try {
                $actionDetails = $this->getBuddyService()->getActionExecution(
                    $workspace,
                    $project,
                    $pipelineId,
                    $executionId,
                    (int) $actionId
                );
                $log = $actionDetails['log'] ?? [];
            } catch (\Exception $e) {
                $allErrors['No Logs'][] = [
                    'action' => $actionName,
                    'detail' => 'Could not fetch logs: ' . $e->getMessage(),
                ];
                continue;
            }

            if (empty($log)) {
                $allErrors['No Logs'][] = [
                    'action' => $actionName,
                    'detail' => 'Action failed but no logs available',
                ];
                continue;
            }

            $errors = $this->extractErrorsFromLog($log);

            if (empty($errors)) {
                $lastLines = array_slice($log, -5);
                $allErrors['Unidentified'][] = [
                    'action' => $actionName,
                    'detail' => implode("\n", $lastLines),
                ];
            } else {
                foreach ($errors as $error) {
                    $allErrors[$error['category']][] = [
                        'action' => $actionName,
                        'detail' => $error['detail'],
                    ];
                }
            }
        }

        $output->writeln('ERROR SUMMARY');
        $output->writeln(str_repeat('=', 40));

        ksort($allErrors);
        foreach ($allErrors as $category => $errors) {
            $count = count($errors);
            $plural = $count > 1 ? 's' : '';
            $output->writeln('');
            $output->writeln("{$category} ({$count} occurrence{$plural}):");

            $seenDetails = [];
            foreach ($errors as $error) {
                $key = $error['action'] . '|' . substr($error['detail'], 0, 50);
                if (isset($seenDetails[$key])) {
                    continue;
                }
                $seenDetails[$key] = true;
                $output->writeln("  [{$error['action']}] {$error['detail']}");
            }
        }

        $output->writeln('');
        $output->writeln('FAILED ACTIONS:');
        foreach ($failedActions as $action) {
            $name = $action['action']['name'] ?? 'Unknown';
            $type = $action['action']['type'] ?? '-';
            $output->writeln("  - {$name} ({$type})");
        }
    }

    /**
     * @param string[] $logLines
     * @return array<array{category: string, detail: string}>
     */
    private function extractErrorsFromLog(array $logLines): array
    {
        $patterns = [
            // General errors
            ['/(?i)\b(error|fatal|exception):\s*(.+)/', 'Error'],
            ['/(?i)^\s*Error:\s*(.+)/', 'Error'],
            // Exit codes
            ['/exit(?:ed)?\s+(?:with\s+)?(?:code\s+)?(\d+)/', 'Exit Code'],
            ['/return(?:ed)?\s+(?:code\s+)?(\d+)/', 'Return Code'],
            // Build failures
            ['/(?i)build\s+failed/', 'Build Failed'],
            ['/(?i)compilation\s+(?:error|failed)/', 'Compilation Error'],
            // Test failures
            ['/(?i)(\d+)\s+(?:test[s]?\s+)?failed/', 'Test Failures'],
            ['/(?i)FAIL[ED]?\s+(.+)/', 'Test Failed'],
            // Dependency issues
            ['/(?i)(?:could\s+not\s+(?:find|resolve)|missing)\s+(?:dependency|package|module)\s*:?\s*(.+)/', 'Missing Dependency'],
            ['/(?i)npm\s+ERR!\s*(.+)/', 'NPM Error'],
            ['/(?i)composer\s+(?:error|failed)/', 'Composer Error'],
            // Permission/access issues
            ['/(?i)permission\s+denied/', 'Permission Denied'],
            ['/(?i)access\s+denied/', 'Access Denied'],
            ['/(?i)authentication\s+(?:failed|error|required)/', 'Auth Error'],
            // Network issues
            ['/(?i)connection\s+(?:refused|timed?\s*out|failed)/', 'Connection Error'],
            ['/(?i)timeout\s+(?:exceeded|error)/', 'Timeout'],
            // Resource issues
            ['/(?i)out\s+of\s+memory/', 'Out of Memory'],
            ['/(?i)disk\s+(?:full|space)/', 'Disk Space'],
        ];

        $errors = [];
        foreach ($logLines as $line) {
            foreach ($patterns as [$pattern, $category]) {
                if (preg_match($pattern, $line, $match)) {
                    $detail = isset($match[1]) ? $match[1] : trim($line);
                    $errors[] = [
                        'category' => $category,
                        'detail' => substr($detail, 0, 200),
                    ];
                    break; // Only match first pattern per line
                }
            }
        }
        return $errors;
    }
}
