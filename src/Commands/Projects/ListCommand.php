<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Projects;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('projects:list')
            ->setDescription('List projects in workspace');

        $this->addWorkspaceOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);

        $response = $this->getBuddyService()->getProjects($workspace);
        $projects = $response['projects'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $projects);
            return self::SUCCESS;
        }

        if (empty($projects)) {
            $output->writeln('<comment>No projects found.</comment>');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($projects as $project) {
            $rows[] = [
                $project['name'] ?? '-',
                $project['display_name'] ?? '-',
                $project['status'] ?? '-',
            ];
        }

        TableFormatter::render($output, ['Name', 'Display Name', 'Status'], $rows);

        return self::SUCCESS;
    }
}
