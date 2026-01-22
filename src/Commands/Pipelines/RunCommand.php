<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:run')
            ->setDescription('Run a pipeline')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('revision', 'r', InputOption::VALUE_REQUIRED, 'Git revision (branch, tag, or commit)')
            ->addOption('comment', 'c', InputOption::VALUE_REQUIRED, 'Execution comment')
            ->addOption('wait', null, InputOption::VALUE_NONE, 'Wait for execution to complete');

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = (int) $input->getArgument('pipeline-id');

        $data = [];
        if ($revision = $input->getOption('revision')) {
            $data['to_revision'] = ['revision' => $revision];
        }
        if ($comment = $input->getOption('comment')) {
            $data['comment'] = $comment;
        }

        $execution = $this->getBuddyService()->runExecution($workspace, $project, $pipelineId, $data);

        if ($input->getOption('wait')) {
            $output->writeln('<comment>Waiting for execution to complete...</comment>');
            $execution = $this->waitForExecution($workspace, $project, $pipelineId, (int) $execution['id'], $output);
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $execution);
            return self::SUCCESS;
        }

        $status = $execution['status'] ?? 'UNKNOWN';
        $data = [
            'Execution ID' => $execution['id'] ?? '-',
            'Status' => $this->formatStatus($status),
            'Branch' => $execution['branch']['name'] ?? '-',
            'Started' => $this->formatTime($execution['start_date'] ?? null),
        ];

        TableFormatter::keyValue($output, $data, 'Execution Started');

        return $status === 'FAILED' ? self::FAILURE : self::SUCCESS;
    }

    private function waitForExecution(string $workspace, string $project, int $pipelineId, int $executionId, OutputInterface $output): array
    {
        $maxAttempts = 600; // 10 minutes with 1s intervals
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $execution = $this->getBuddyService()->getExecution($workspace, $project, $pipelineId, $executionId);
            $status = $execution['status'] ?? '';

            if (!in_array($status, ['INPROGRESS', 'ENQUEUED', 'INITIAL'], true)) {
                return $execution;
            }

            sleep(1);
            $attempt++;

            if ($attempt % 10 === 0) {
                $output->write('.');
            }
        }

        $output->writeln('');
        $output->writeln('<comment>Timeout waiting for execution.</comment>');
        return $execution;
    }
}
