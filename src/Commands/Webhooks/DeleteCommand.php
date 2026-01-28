<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Webhooks;

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
            ->setName('webhooks:delete')
            ->setDescription('Delete a webhook')
            ->addArgument('webhook-id', InputArgument::REQUIRED, 'Webhook ID')
            ->setHelp(<<<'HELP'
Deletes a webhook by ID.

Prompts for confirmation unless <info>--force</info> is specified.

Options:
  -f, --force   Skip confirmation prompt

Examples:
  buddy webhooks:delete 12345
  buddy webhooks:delete 12345 --force
HELP);

        $this->addWorkspaceOption();
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $webhookId = (int) $input->getArgument('webhook-id');

        // Get webhook details for confirmation
        try {
            $webhook = $this->getBuddyService()->getWebhook($workspace, $webhookId);
        } catch (\Exception $e) {
            $output->writeln("<error>Webhook not found: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        $url = $webhook['target_url'] ?? "ID {$webhookId}";

        // Confirm deletion unless --force
        if (!$input->getOption('force') && $input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Are you sure you want to delete webhook '{$url}'? [y/N] ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Cancelled.</comment>');
                return self::SUCCESS;
            }
        }

        try {
            $this->getBuddyService()->deleteWebhook($workspace, $webhookId);
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to delete webhook: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, ['success' => true, 'deleted' => $webhookId]);
            return self::SUCCESS;
        }

        $output->writeln("<info>Deleted webhook: {$url}</info>");
        return self::SUCCESS;
    }
}
