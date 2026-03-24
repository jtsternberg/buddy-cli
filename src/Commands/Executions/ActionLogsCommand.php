<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Executions;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActionLogsCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('executions:action-logs')
            ->setDescription('Show log output for a specific action execution')
            ->addArgument('execution-id', InputArgument::REQUIRED, 'Execution ID (numeric or hex hash from Buddy URL)')
            ->addArgument('action-execution-id', InputArgument::REQUIRED, 'Action execution ID (hex string from executions:actions)')
            ->setHelp(<<<'HELP'
Fetches and displays the full log output for a specific action within a pipeline execution.

Use executions:actions to list available action_execution_ids first.

Accepts both numeric IDs and hex hashes from Buddy URLs for the execution-id argument.

Examples:
  buddy executions:action-logs 191 69c2e627e09152558bd74820 --pipeline=493770
  buddy executions:action-logs 69c2e5efe09152558bd745e5 69c2e627e09152558bd74820 --pipeline=493770
  buddy executions:action-logs 191 69c2e627e09152558bd74820 --pipeline=493770 --json
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
        $executionId = $this->resolveExecutionId($input, $output, $workspace, $project, $pipelineId);
        $actionExecutionId = $input->getArgument('action-execution-id');

        if (!ctype_xdigit($actionExecutionId) || $actionExecutionId === '') {
            throw new \RuntimeException(
                "Invalid action-execution-id '{$actionExecutionId}'. Expected a hex string (e.g., 69c2e627e09152558bd74820). "
                . "Use 'buddy executions:actions' to find valid action execution IDs."
            );
        }

        $actionExecution = $this->getBuddyService()->getActionExecutionByExecId(
            $workspace,
            $project,
            $pipelineId,
            $executionId,
            $actionExecutionId
        );

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $actionExecution);
            return self::SUCCESS;
        }

        $name = $actionExecution['action']['name'] ?? 'Unknown';
        $status = $actionExecution['status'] ?? 'UNKNOWN';
        $duration = $this->formatDuration($actionExecution['start_date'] ?? null, $actionExecution['finish_date'] ?? null);

        $output->writeln(sprintf('<comment>--- %s | %s | %s ---</comment>', $name, $this->formatStatus($status), $duration));

        $logs = $actionExecution['log'] ?? [];
        if (empty($logs)) {
            $output->writeln('<fg=gray>No log output available.</>');
            return self::SUCCESS;
        }

        $output->writeln('');
        foreach ($logs as $line) {
            $output->writeln($line);
        }

        return self::SUCCESS;
    }
}
