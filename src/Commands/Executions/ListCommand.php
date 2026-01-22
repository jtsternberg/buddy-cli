<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Executions;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('executions:list')
            ->setDescription('List recent executions')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status (SUCCESSFUL, FAILED, INPROGRESS)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of executions to show', '10');

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

        $filters = [
            'per_page' => (int) $input->getOption('limit'),
        ];

        if ($status = $input->getOption('status')) {
            $filters['status'] = strtoupper($status);
        }

        $response = $this->getBuddyService()->getExecutions($workspace, $project, $pipelineId, $filters);
        $executions = $response['executions'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $executions);
            return self::SUCCESS;
        }

        if (empty($executions)) {
            $output->writeln('<comment>No executions found.</comment>');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($executions as $execution) {
            $rows[] = [
                $execution['id'] ?? '-',
                $this->formatStatus($execution['status'] ?? 'UNKNOWN'),
                $execution['branch']['name'] ?? '-',
                $execution['creator']['name'] ?? '-',
                $this->formatTime($execution['start_date'] ?? null),
                $this->formatDuration($execution['start_date'] ?? null, $execution['finish_date'] ?? null),
            ];
        }

        TableFormatter::render($output, ['ID', 'Status', 'Branch', 'Creator', 'Started', 'Duration'], $rows);

        return self::SUCCESS;
    }

}
