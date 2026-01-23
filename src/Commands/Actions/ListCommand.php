<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Actions;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('actions:list')
            ->setDescription('List actions in a pipeline');

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

        $response = $this->getBuddyService()->getPipelineActions($workspace, $project, $pipelineId);
        $actions = $response['actions'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $actions);
            return self::SUCCESS;
        }

        if (empty($actions)) {
            $output->writeln('<comment>No actions found.</comment>');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($actions as $action) {
            $rows[] = [
                $action['id'] ?? '-',
                $action['name'] ?? '-',
                $action['type'] ?? '-',
                $action['trigger_time'] ?? '-',
            ];
        }

        TableFormatter::render($output, ['ID', 'Name', 'Type', 'Trigger'], $rows);

        return self::SUCCESS;
    }
}
