<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Executions;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FailedCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('executions:failed')
            ->setDescription('Show failed action details from an execution')
            ->addArgument('execution-id', InputArgument::REQUIRED, 'Execution ID');

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

        foreach ($failedActions as $action) {
            $output->writeln('');
            $output->writeln(sprintf('<error>Failed Action: %s</error>', $action['action']['name'] ?? 'Unknown'));

            $data = [
                'Type' => $action['action']['type'] ?? '-',
                'Started' => $this->formatTime($action['start_date'] ?? null),
                'Finished' => $this->formatTime($action['finish_date'] ?? null),
            ];

            TableFormatter::keyValue($output, $data);

            $logs = $action['logs'] ?? null;
            if ($logs !== null) {
                $output->writeln('');
                $output->writeln('<comment>Logs:</comment>');
                $output->writeln($logs);
            }
        }

        return self::SUCCESS;
    }
}
