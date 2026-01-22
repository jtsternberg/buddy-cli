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
            ->addOption('logs', null, InputOption::VALUE_NONE, 'Show action logs');

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
                $this->showLogs($output, $actionExecutions);
            }
        }

        return self::SUCCESS;
    }

    private function showLogs(OutputInterface $output, array $actionExecutions): void
    {
        foreach ($actionExecutions as $action) {
            $logs = $action['logs'] ?? null;
            if ($logs === null) {
                continue;
            }

            $output->writeln('');
            $output->writeln(sprintf('<comment>--- Logs: %s ---</comment>', $action['action']['name'] ?? 'Unknown'));
            $output->writeln($logs);
        }
    }
}
