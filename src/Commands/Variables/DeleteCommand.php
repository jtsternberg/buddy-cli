<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Variables;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DeleteCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('vars:delete')
            ->setDescription('Delete an environment variable')
            ->addArgument('variable-id', InputArgument::REQUIRED, 'Variable ID')
            ->setHelp(<<<'HELP'
Deletes an environment variable by ID.

Prompts for confirmation unless <info>--force</info> is specified.

Options:
  -f, --force   Skip confirmation prompt

Examples:
  buddy vars:delete 12345
  buddy vars:delete 12345 --force
HELP);

        $this->addWorkspaceOption();
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $variableId = (int) $input->getArgument('variable-id');

        // Get variable details for confirmation
        try {
            $variable = $this->getBuddyService()->getVariable($workspace, $variableId);
        } catch (\Exception $e) {
            $output->writeln("<error>Variable not found: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        $key = $variable['key'] ?? "ID {$variableId}";

        // Confirm deletion unless --force
        if (!$input->getOption('force') && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Are you sure you want to delete variable '{$key}'? [y/N] ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Cancelled.</comment>');
                return self::SUCCESS;
            }
        }

        try {
            $this->getBuddyService()->deleteVariable($workspace, $variableId);
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to delete variable: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, ['success' => true, 'deleted' => $key]);
            return self::SUCCESS;
        }

        $output->writeln("<info>Deleted variable: {$key}</info>");
        return self::SUCCESS;
    }
}
