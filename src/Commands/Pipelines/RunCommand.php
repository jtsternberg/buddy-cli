<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use Buddy\Exceptions\BuddyResponseException;
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
            ->addOption('branch', 'b', InputOption::VALUE_REQUIRED, 'Branch name (required for wildcard pipelines)')
            ->addOption('revision', 'r', InputOption::VALUE_REQUIRED, 'Git revision (commit SHA)')
            ->addOption('tag', 't', InputOption::VALUE_REQUIRED, 'Tag name')
            ->addOption('comment', 'c', InputOption::VALUE_REQUIRED, 'Execution comment')
            ->addOption('wait', null, InputOption::VALUE_NONE, 'Wait for execution to complete')
            ->setHelp(<<<'HELP'
Triggers a new execution of the specified pipeline.

For pipelines with wildcard ref patterns (no fixed branch), you must specify
<info>--branch</info>, <info>--tag</info>, or <info>--revision</info>.

Options:
  -b, --branch     Branch name to run against
  -r, --revision   Specific commit SHA to run
  -t, --tag        Tag name to run against
  -c, --comment    Comment to attach to this execution
      --wait       Wait for execution to complete before returning

Examples:
  buddy pipelines:run 12345 --project=my-project
  buddy pipelines:run 12345 --branch=feature/new-feature
  buddy pipelines:run 12345 --revision=abc123 --comment="Hotfix deploy"
  buddy pipelines:run 12345 --wait
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

        $data = [];
        if ($branch = $input->getOption('branch')) {
            $data['branch'] = ['name' => $branch];
        }
        if ($revision = $input->getOption('revision')) {
            $data['to_revision'] = ['revision' => $revision];
        }
        if ($tag = $input->getOption('tag')) {
            $data['tag'] = ['name' => $tag];
        }
        if ($comment = $input->getOption('comment')) {
            $data['comment'] = $comment;
        }

        // Check if pipeline uses wildcards and requires branch/tag/revision
        $pipeline = $this->getBuddyService()->getPipeline($workspace, $project, $pipelineId);
        $hasRef = !empty($data['branch']) || !empty($data['tag']) || !empty($data['to_revision']);
        if (empty($pipeline['ref_name']) && !$hasRef) {
            $output->writeln('<error>This pipeline uses wildcards. Specify --branch, --tag, or --revision.</error>');
            return self::FAILURE;
        }

        try {
            $execution = $this->getBuddyService()->runExecution($workspace, $project, $pipelineId, $data);
        } catch (BuddyResponseException $e) {
            $message = $e->getMessage();
            $code = $e->getStatusCode();
            switch ($code) {
                case 400:
                    $message = "[$code] Invalid request: $message";
                    break;
                case 401:
                    $message = "[$code] Unauthorized: $message";
                    break;
            }

            $output->writeln("<error>{$message}</error>");
            $output->writeln('');
            $this->getApplication()->find('help')->run(
                new \Symfony\Component\Console\Input\ArrayInput(['command_name' => $this->getName()]),
                $output
            );
            return self::FAILURE;
        }

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
