<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RetryCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:retry')
            ->setDescription('Retry the last failed execution')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->setHelp(<<<'HELP'
Retries the most recent execution of the specified pipeline.

Useful for re-running failed builds without manually triggering a new execution.
The retry uses the same commit and configuration as the original execution.

Example:
  buddy pipelines:retry 12345 --project=my-project
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = (int) $input->getArgument('pipeline-id');

        // Get last execution
        $executions = $this->getBuddyService()->getExecutions($workspace, $project, $pipelineId, ['per_page' => 1]);
        $lastExecution = $executions['executions'][0] ?? null;

        if ($lastExecution === null) {
            $output->writeln('<error>No executions found for this pipeline.</error>');
            return self::FAILURE;
        }

        $execution = $this->getBuddyService()->retryExecution(
            $workspace,
            $project,
            $pipelineId,
            (int) $lastExecution['id']
        );

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $execution);
            return self::SUCCESS;
        }

        $data = [
            'Execution ID' => $execution['id'] ?? '-',
            'Status' => $this->formatStatus($execution['status'] ?? 'UNKNOWN'),
        ];

        TableFormatter::keyValue($output, $data, 'Execution Retried');

        return self::SUCCESS;
    }
}
