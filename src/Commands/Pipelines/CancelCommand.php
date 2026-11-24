<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CancelCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:cancel')
            ->setDescription('Cancel a running execution')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('execution', 'e', InputOption::VALUE_REQUIRED, 'Execution ID to cancel (any run, including a queued one)')
            ->setHelp(<<<'HELP'
Cancels the running (INPROGRESS) execution of the specified pipeline. Queued runs
are left alone. To cancel a specific run, running or queued, pass
<info>--execution=<id></info>.

If no execution is running, the command succeeds and names any queued runs. If
more than one is running, it refuses and lists them so you can pick one with
<info>--execution</info>.

Examples:
  buddy pipelines:cancel 12345 --project=my-project
  buddy pipelines:cancel 12345 --project=my-project --execution=16
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
        $executionOption = $input->getOption('execution');

        if ($executionOption !== null) {
            if (!ctype_digit((string) $executionOption)) {
                $output->writeln("<error>--execution must be a numeric execution ID, got '{$executionOption}'.</error>");
                return self::FAILURE;
            }
            $targetId = (int) $executionOption;
        } else {
            // Buddy ignores a status query param here and returns newest first, so
            // asking for "INPROGRESS, per_page=1" cancels whatever is newest, often a
            // queued run. Pick the running one ourselves.
            $recent = $this->getBuddyService()->getExecutions($workspace, $project, $pipelineId, ['per_page' => 20])['executions'] ?? [];
            $running = array_values(array_filter($recent, fn ($e) => ($e['status'] ?? '') === 'INPROGRESS'));
            $queued = array_values(array_filter($recent, fn ($e) => ($e['status'] ?? '') === 'ENQUEUED'));

            if (count($running) > 1) {
                $ids = implode(', ', array_map(fn ($e) => '#' . $e['id'], $running));
                $output->writeln("<error>Several executions are running ({$ids}). Pick one with --execution=<id>.</error>");
                return self::FAILURE;
            }

            if ($running === []) {
                $output->writeln('<comment>No running execution found for this pipeline.</comment>');
                if ($queued !== []) {
                    $ids = implode(', ', array_map(fn ($e) => '#' . $e['id'], $queued));
                    $output->writeln("<comment>Queued: {$ids}. To cancel one: --execution={$queued[0]['id']}</comment>");
                }
                return self::SUCCESS;
            }

            $targetId = (int) $running[0]['id'];
        }

        $execution = $this->getBuddyService()->cancelExecution($workspace, $project, $pipelineId, $targetId);

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
