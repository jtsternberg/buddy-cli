<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Executions;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ActionsCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('executions:actions')
            ->setDescription('List action executions for a given execution, with their IDs')
            ->addArgument('execution-id', InputArgument::REQUIRED, 'Execution ID (numeric or hex hash from Buddy URL)')
            ->setHelp(<<<'HELP'
Lists all action executions within a specific pipeline execution, showing each
action's name, status, duration, and action_execution_id.

The action_execution_id can be passed to executions:action-logs to retrieve
the full log output for that specific action run.

Accepts both numeric IDs and hex hashes from Buddy URLs.

Examples:
  buddy executions:actions 191 --pipeline=493770
  buddy executions:actions 69c2e5efe09152558bd745e5 --pipeline=493770
  buddy executions:actions 191 --pipeline=493770 --json
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

        $execution = $this->getBuddyService()->getExecution($workspace, $project, $pipelineId, $executionId);
        $actionExecutions = $execution['action_executions'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $actionExecutions);
            return self::SUCCESS;
        }

        if (empty($actionExecutions)) {
            $output->writeln('No action executions found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($actionExecutions as $actionExec) {
            $rows[] = [
                $actionExec['action']['name'] ?? '-',
                $this->formatStatus($actionExec['status'] ?? 'UNKNOWN'),
                $this->formatDuration($actionExec['start_date'] ?? null, $actionExec['finish_date'] ?? null),
                $actionExec['action_execution_id'] ?? '-',
            ];
        }

        TableFormatter::render($output, ['Action', 'Status', 'Duration', 'Action Execution ID'], $rows);

        return self::SUCCESS;
    }
}
