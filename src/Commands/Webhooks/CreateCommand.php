<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Webhooks;

use Buddy\Apis\Webhooks;
use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('webhooks:create')
            ->setDescription('Create a new webhook')
            ->setHelp(<<<'HELP'
Creates a new webhook in the workspace.

Options:
  --url           Target URL (required)
  --events        Comma-separated event types (required)
  --project       Filter to specific project name
  --secret        Secret key for payload signing

Available events:
  PUSH                   Code pushed to repository
  EXECUTION_STARTED      Pipeline execution started
  EXECUTION_SUCCESSFUL   Pipeline execution succeeded
  EXECUTION_FAILED       Pipeline execution failed
  EXECUTION_FINISHED     Pipeline execution finished (any status)

Examples:
  buddy webhooks:create --url=https://example.com/hook --events=PUSH
  buddy webhooks:create --url=https://example.com/hook --events=EXECUTION_FAILED,EXECUTION_SUCCESSFUL
  buddy webhooks:create --url=https://example.com/hook --events=PUSH --project=my-project
  buddy webhooks:create --url=https://example.com/hook --events=PUSH --secret=mysecret
HELP);

        $this->addWorkspaceOption();
        $this->addOption('url', null, InputOption::VALUE_REQUIRED, 'Target URL for webhook');
        $this->addOption('events', null, InputOption::VALUE_REQUIRED, 'Comma-separated event types');
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'Filter to specific project');
        $this->addOption('secret', null, InputOption::VALUE_REQUIRED, 'Secret key for signing');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $url = $input->getOption('url');
        $events = $input->getOption('events');

        if ($url === null) {
            $output->writeln('<error>--url is required</error>');
            return self::FAILURE;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $output->writeln('<error>Invalid URL format</error>');
            return self::FAILURE;
        }

        if ($events === null) {
            $output->writeln('<error>--events is required</error>');
            return self::FAILURE;
        }

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

        $data = [
            'target_url' => $url,
            'events' => $eventList,
        ];

        if ($input->getOption('project') !== null) {
            $data['project_filter'] = $input->getOption('project');
        }

        if ($input->getOption('secret') !== null) {
            $data['secret_key'] = $input->getOption('secret');
        }

        try {
            $result = $this->getBuddyService()->createWebhook($workspace, $data);
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create webhook: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $result);
            return self::SUCCESS;
        }

        $output->writeln("<info>Created webhook (ID: {$result['id']})</info>");
        return self::SUCCESS;
    }
}
