<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Projects;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('projects:show')
            ->setDescription('Show project details')
            ->addArgument('project', InputArgument::OPTIONAL, 'Project name (uses default if not specified)');

        $this->addWorkspaceOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $projectName = $input->getArgument('project') ?? $this->getConfigService()->get('project');

        if ($projectName === null) {
            $output->writeln('<error>No project specified. Provide project name as argument or set default with config:set project <name></error>');
            return self::FAILURE;
        }

        $project = $this->getBuddyService()->getProject($workspace, $projectName);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $project);
            return self::SUCCESS;
        }

        $data = [
            'Name' => $project['name'] ?? '-',
            'Display Name' => $project['display_name'] ?? '-',
            'Status' => $project['status'] ?? '-',
            'Created' => $this->formatTime($project['create_date'] ?? null),
            'Repository' => $project['http_repository'] ?? '-',
        ];

        TableFormatter::keyValue($output, $data, "Project: {$projectName}");

        return self::SUCCESS;
    }
}
