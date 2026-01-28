<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Webhooks;

use Buddy\Apis\Webhooks;
use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('webhooks:update')
            ->setDescription('Update an existing webhook')
            ->addArgument('webhook-id', InputArgument::REQUIRED, 'Webhook ID')
            ->setHelp(<<<'HELP'
Updates an existing webhook.

At least one option must be provided to update.

Options:
  --url           New target URL
  --events        New comma-separated event types (replaces existing)
  --project       Filter to specific project (use empty string to clear)
  --secret        New secret key for signing

Examples:
  buddy webhooks:update 12345 --url=https://example.com/new-hook
  buddy webhooks:update 12345 --events=PUSH,EXECUTION_FAILED
  buddy webhooks:update 12345 --project=new-project
  buddy webhooks:update 12345 --project="" # Clear project filter
HELP);

        $this->addWorkspaceOption();
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'New target URL');
        $this->addOption('events', null, InputOption::VALUE_REQUIRED, 'Comma-separated event types');
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'Filter to specific project');
        $this->addOption('secret', null, InputOption::VALUE_REQUIRED, 'Secret key for signing');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $webhookId = (int) $input->getArgument('webhook-id');

        $data = [];

        $url = $input->getOption('url');
        if ($url !== null) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $output->writeln('<error>Invalid URL format</error>');
                return self::FAILURE;
            }
            $data['target_url'] = $url;
        }

        $events = $input->getOption('events');
        if ($events !== null) {
            $eventList = array_map('trim', explode(',', $events));
            $validEvents = [
                Webhooks::EVENT_PUSH,
                Webhooks::EVENT_EXECUTION_STARTED,
                Webhooks::EVENT_EXECUTION_SUCCESSFUL,
                Webhooks::EVENT_EXECUTION_FAILED,
                Webhooks::EVENT_EXECUTION_FINISHED,
            ];

            foreach ($eventList as $event) {
                if (!in_array($event, $validEvents, true)) {
                    $output->writeln("<error>Invalid event type: {$event}</error>");
                    $output->writeln('Valid events: ' . implode(', ', $validEvents));
                    return self::FAILURE;
                }
            }
            $data['events'] = $eventList;
        }

        if ($input->getOption('project') !== null) {
            $project = $input->getOption('project');
            $data['project_filter'] = $project === '' ? null : $project;
        }

        if ($input->getOption('secret') !== null) {
            $data['secret_key'] = $input->getOption('secret');
        }

        if (empty($data)) {
            $output->writeln('<error>At least one option must be provided to update</error>');
            return self::FAILURE;
        }

        try {
            $result = $this->getBuddyService()->updateWebhook($workspace, $webhookId, $data);
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to update webhook: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $result);
            return self::SUCCESS;
        }

        $output->writeln("<info>Updated webhook (ID: {$result['id']})</info>");
        return self::SUCCESS;
    }
}
