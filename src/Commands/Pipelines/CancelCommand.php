<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CancelCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:cancel')
            ->setDescription('Cancel a running execution')
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

        // Get running execution
        $executions = $this->getBuddyService()->getExecutions($workspace, $project, $pipelineId, [
            'status' => 'INPROGRESS',
            'per_page' => 1,
        ]);
        $runningExecution = $executions['executions'][0] ?? null;

        if ($runningExecution === null) {
            $output->writeln('<comment>No running execution found for this pipeline.</comment>');
            return self::SUCCESS;
        }

        $execution = $this->getBuddyService()->cancelExecution(
            $workspace,
            $project,
            $pipelineId,
            (int) $runningExecution['id']
        );

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $execution);
            return self::SUCCESS;
        }

        $data = [
            'Execution ID' => $execution['id'] ?? '-',
            'Status' => $this->formatStatus($execution['status'] ?? 'UNKNOWN'),
        ];

        TableFormatter::keyValue($output, $data, 'Execution Cancelled');

        return self::SUCCESS;
    }
}
