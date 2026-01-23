<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Executions;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('executions:show')
            ->setDescription('Show execution details')
            ->addArgument('execution-id', InputArgument::REQUIRED, 'Execution ID')
            ->addOption('logs', null, InputOption::VALUE_NONE, 'Show action logs')
            ->addOption('summary', null, InputOption::VALUE_NONE, 'Show compact status summary')
            ->setHelp(<<<'HELP'
Displays detailed information about a specific execution.

Shows execution metadata (status, branch, revision, creator, timing) and lists
all actions with their individual status and duration.

Options:
      --logs     Include full output logs for each action
      --summary  Show compact, readable status summary

Examples:
  buddy executions:show 67890 --pipeline=12345
  buddy executions:show 67890 --pipeline=12345 --logs
  buddy executions:show 67890 --pipeline=12345 --summary
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
            $this->outputJson($output, $execution);
            return self::SUCCESS;
        }

        if ($input->getOption('summary')) {
            $this->outputSummary($output, $execution);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $execution['id'] ?? '-',
            'Status' => $this->formatStatus($execution['status'] ?? 'UNKNOWN'),
            'Branch' => $execution['branch']['name'] ?? '-',
            'Revision' => substr($execution['to_revision']['revision'] ?? '-', 0, 8),
            'Creator' => $execution['creator']['name'] ?? '-',
            'Started' => $this->formatTime($execution['start_date'] ?? null),
            'Finished' => $this->formatTime($execution['finish_date'] ?? null),
            'Comment' => $execution['comment'] ?? '-',
        ];

        TableFormatter::keyValue($output, $data, 'Execution Details');

        // Show action execution details
        $actionExecutions = $execution['action_executions'] ?? [];
        if (!empty($actionExecutions)) {
            $output->writeln('');
            $output->writeln('<info>Actions:</info>');

            $rows = [];
            foreach ($actionExecutions as $action) {
                $rows[] = [
                    $action['action']['name'] ?? '-',
                    $this->formatStatus($action['status'] ?? 'UNKNOWN'),
                    $this->formatDuration($action['start_date'] ?? null, $action['finish_date'] ?? null),
                ];
            }

            TableFormatter::render($output, ['Action', 'Status', 'Duration'], $rows);

            // Show logs if requested
            if ($input->getOption('logs')) {
                $this->showLogs($output, $workspace, $project, $pipelineId, $executionId, $actionExecutions);
            }
        }

        return self::SUCCESS;
    }

    private function showLogs(OutputInterface $output, string $workspace, string $project, int $pipelineId, int $executionId, array $actionExecutions): void
    {
        foreach ($actionExecutions as $actionExec) {
            $actionId = $actionExec['action']['id'] ?? null;
            if ($actionId === null) {
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

                $logs = $actionDetails['log'] ?? [];
                if (empty($logs)) {
                    continue;
                }

                $output->writeln('');
                $output->writeln(sprintf('<comment>--- Logs: %s ---</comment>', $actionExec['action']['name'] ?? 'Unknown'));
                foreach ($logs as $line) {
                    $output->writeln($line);
                }
            } catch (\Exception $e) {
                // Skip actions where we can't fetch logs
            }
        }
    }

    private function outputSummary(OutputInterface $output, array $execution): void
    {
        $execId = $execution['id'] ?? 'Unknown';
        $status = $execution['status'] ?? 'UNKNOWN';
        $branch = $execution['branch']['name'] ?? '-';
        $revision = substr($execution['to_revision']['revision'] ?? '-', 0, 8);
        $creator = $execution['creator']['name'] ?? '-';
        $duration = $this->formatDuration($execution['start_date'] ?? null, $execution['finish_date'] ?? null);

        $output->writeln("Execution #{$execId}");
        $output->writeln("  Status:   " . $this->statusIndicator($status) . " {$status}");
        $output->writeln("  Branch:   {$branch}");
        $output->writeln("  Revision: {$revision}");
        $output->writeln("  Creator:  {$creator}");
        $output->writeln("  Duration: {$duration}");
        $output->writeln('');

        $actions = $execution['action_executions'] ?? [];
        if (empty($actions)) {
            $output->writeln('No actions in this execution.');
            return;
        }

        $failed = array_filter($actions, fn ($a) => ($a['status'] ?? '') === 'FAILED');
        $successful = array_filter($actions, fn ($a) => ($a['status'] ?? '') === 'SUCCESSFUL');

        $total = count($actions);
        $passedCount = count($successful);
        $output->writeln("Actions: {$passedCount}/{$total} passed");
        $output->writeln('');

        foreach ($actions as $action) {
            $name = $action['action']['name'] ?? 'Unknown';
            $actionStatus = $action['status'] ?? 'UNKNOWN';
            $actionDuration = $this->formatDuration($action['start_date'] ?? null, $action['finish_date'] ?? null);
            $indicator = $this->statusIndicator($actionStatus);

            $line = "  {$indicator} {$name}";
            if ($actionDuration !== '-') {
                $line .= " ({$actionDuration})";
            }
            $output->writeln($line);
        }

        if (!empty($failed)) {
            $output->writeln('');
            $output->writeln('FAILED ACTIONS:');
            foreach ($failed as $action) {
                $name = $action['action']['name'] ?? 'Unknown';
                $type = $action['action']['type'] ?? '-';
                $output->writeln("  - {$name} ({$type})");
            }
        }
    }

    private function statusIndicator(string $status): string
    {
        return match ($status) {
            'SUCCESSFUL' => '[OK]',
            'FAILED' => '[X]',
            'INPROGRESS' => '[->]',
            'ENQUEUED' => '[..]',
            'SKIPPED' => '[--]',
            'TERMINATED' => '[##]',
            default => '[?]',
        };
    }
}
