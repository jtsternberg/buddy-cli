<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:list')
            ->setDescription('List all pipelines');

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);

        $response = $this->getBuddyService()->getPipelines($workspace, $project);
        $pipelines = $response['pipelines'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $pipelines);
            return self::SUCCESS;
        }

        if (empty($pipelines)) {
            $output->writeln('<comment>No pipelines found.</comment>');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($pipelines as $pipeline) {
            $rows[] = [
                $pipeline['id'] ?? '-',
                $pipeline['name'] ?? '-',
                $this->formatStatus($pipeline['last_execution_status'] ?? 'UNKNOWN'),
                $pipeline['trigger_mode'] ?? '-',
                $this->formatTime($pipeline['last_execution_date'] ?? null),
            ];
        }

        TableFormatter::render($output, ['ID', 'Name', 'Status', 'Trigger', 'Last Run'], $rows);

        return self::SUCCESS;
    }
}
