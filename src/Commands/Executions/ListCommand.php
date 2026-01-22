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
            ->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Pipeline ID')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status (SUCCESSFUL, FAILED, INPROGRESS)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of executions to show', '10');

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = $input->getOption('pipeline');

        if ($pipelineId === null) {
            $output->writeln('<error>Pipeline ID is required. Use --pipeline=<id></error>');
            return self::FAILURE;
        }

        $filters = [
            'per_page' => (int) $input->getOption('limit'),
        ];

        if ($status = $input->getOption('status')) {
            $filters['status'] = strtoupper($status);
        }

        $response = $this->getBuddyService()->getExecutions($workspace, $project, (int) $pipelineId, $filters);
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

    private function formatDuration(?string $start, ?string $finish): string
    {
        if ($start === null) {
            return '-';
        }

        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = $finish !== null ? new \DateTimeImmutable($finish) : new \DateTimeImmutable();
            $diff = $endDate->diff($startDate);

            if ($diff->h > 0) {
                return sprintf('%dh %dm', $diff->h, $diff->i);
            }
            if ($diff->i > 0) {
                return sprintf('%dm %ds', $diff->i, $diff->s);
            }
            return sprintf('%ds', $diff->s);
        } catch (\Exception) {
            return '-';
        }
    }
}
