<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:delete')
            ->setDescription('Delete a pipeline')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation')
            ->setHelp(<<<'HELP'
Permanently deletes a pipeline, including its actions and execution history.

Prompts with the pipeline's name and ID and deletes only on "y" or "yes".
With <info>--no-interaction</info> it refuses unless <info>--force</info> is given.

Examples:
  buddy pipelines:delete 12345 --project=my-project
  buddy pipelines:delete 12345 --project=my-project --force --json
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $rawId = (string) $input->getArgument('pipeline-id');

        // (int) 'deploy' is 0; never let a typo reach a delete call.
        if (!ctype_digit($rawId)) {
            $output->writeln("<error>Pipeline ID must be numeric, got '{$rawId}'. Use 'buddy pipelines:list' to find it.</error>");
            return self::FAILURE;
        }
        $pipelineId = (int) $rawId;

        try {
            $pipeline = $this->getBuddyService()->getPipeline($workspace, $project, $pipelineId);
        } catch (\Exception $e) {
            $output->writeln("<error>Pipeline not found: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        $name = $pipeline['name'] ?? "ID {$pipelineId}";

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $output->writeln("<error>Refusing to delete pipeline '{$name}' (ID: {$pipelineId}) without confirmation. Pass --force to delete non-interactively.</error>");
                return self::FAILURE;
            }

            // Prompt on stderr so --json stdout stays parseable.
            $promptOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $question = new ConfirmationQuestion(
                "Delete pipeline '{$name}' (ID: {$pipelineId})? This cannot be undone. [y/N] ",
                false,
                '/^y(es)?$/i'
            );

            if (!$this->getHelper('question')->ask($input, $promptOutput, $question)) {
                $promptOutput->writeln('<comment>Cancelled.</comment>');
                return self::SUCCESS;
            }
        }

        try {
            $this->getBuddyService()->deletePipeline($workspace, $project, $pipelineId);
        } catch (\Exception $e) {
            $output->writeln("<error>Delete failed: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, ['deleted' => true, 'id' => $pipelineId, 'name' => $pipeline['name'] ?? null]);
            return self::SUCCESS;
        }

        $output->writeln("<info>Deleted pipeline: {$name} (ID: {$pipelineId})</info>");
        return self::SUCCESS;
    }
}
