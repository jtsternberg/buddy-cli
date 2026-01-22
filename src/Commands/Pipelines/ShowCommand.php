<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

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
            ->setName('pipelines:show')
            ->setDescription('Show pipeline details')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID');

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = (int) $input->getArgument('pipeline-id');

        $pipeline = $this->getBuddyService()->getPipeline($workspace, $project, $pipelineId);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $pipeline);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $pipeline['id'] ?? '-',
            'Name' => $pipeline['name'] ?? '-',
            'Status' => $this->formatStatus($pipeline['last_execution_status'] ?? 'UNKNOWN'),
            'Trigger' => $pipeline['trigger_mode'] ?? '-',
            'Branch/Ref' => $pipeline['ref_name'] ?? '-',
            'Last Run' => $this->formatTime($pipeline['last_execution_date'] ?? null),
            'Created' => $this->formatTime($pipeline['create_date'] ?? null),
        ];

        TableFormatter::keyValue($output, $data, "Pipeline: {$pipeline['name']}");

        // Show actions if available
        $actions = $this->getBuddyService()->getPipelineActions($workspace, $project, $pipelineId);
        $actionList = $actions['actions'] ?? [];

        if (!empty($actionList)) {
            $output->writeln('');
            $output->writeln('<info>Actions:</info>');

            $rows = [];
            foreach ($actionList as $action) {
                $rows[] = [
                    $action['id'] ?? '-',
                    $action['name'] ?? '-',
                    $action['type'] ?? '-',
                ];
            }

            TableFormatter::render($output, ['ID', 'Name', 'Type'], $rows);
        }

        return self::SUCCESS;
    }
}
