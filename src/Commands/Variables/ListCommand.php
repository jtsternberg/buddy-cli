<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Variables;

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
            ->setName('vars:list')
            ->setDescription('List environment variables');

        $this->addWorkspaceOption();
        $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Filter by project name');
        $this->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Filter by pipeline ID');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);

        $filters = [];
        if ($input->getOption('project') !== null) {
            $filters['projectName'] = $input->getOption('project');
        }
        if ($input->getOption('pipeline') !== null) {
            $filters['pipelineId'] = (int) $input->getOption('pipeline');
        }

        $response = $this->getBuddyService()->getVariables($workspace, $filters);
        $variables = $response['variables'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $variables);
            return self::SUCCESS;
        }

        if (empty($variables)) {
            $output->writeln('<comment>No variables found.</comment>');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($variables as $variable) {
            $rows[] = [
                $variable['id'] ?? '-',
                $variable['key'] ?? '-',
                $variable['type'] ?? 'VAR',
                $variable['encrypted'] ?? false ? 'Yes' : 'No',
                $this->getScope($variable),
            ];
        }

        TableFormatter::render($output, ['ID', 'Key', 'Type', 'Encrypted', 'Scope'], $rows);

        return self::SUCCESS;
    }

    private function getScope(array $variable): string
    {
        if (isset($variable['action']['id'])) {
            return 'action:' . $variable['action']['id'];
        }
        if (isset($variable['pipeline']['id'])) {
            return 'pipeline:' . $variable['pipeline']['id'];
        }
        if (isset($variable['project']['name'])) {
            return 'project:' . $variable['project']['name'];
        }
        return 'workspace';
    }
}
