<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Webhooks;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('webhooks:show')
            ->setDescription('Show webhook details')
            ->addArgument('webhook-id', InputArgument::REQUIRED, 'Webhook ID')
            ->setHelp(<<<'HELP'
Displays detailed information about a specific webhook.

Shows ID, target URL, events, project filter, and secret key hash.

Example:
  buddy webhooks:show 12345
HELP);

        $this->addWorkspaceOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $webhookId = (int) $input->getArgument('webhook-id');

        $webhook = $this->getBuddyService()->getWebhook($workspace, $webhookId);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $webhook);
            return self::SUCCESS;
        }

        $events = $webhook['events'] ?? [];
        $projectFilter = $webhook['project_filter'] ?? null;

        $data = [
            'ID' => $webhook['id'] ?? '-',
            'Target URL' => $webhook['target_url'] ?? '-',
            'Events' => !empty($events) ? implode(', ', $events) : 'None',
            'Project Filter' => $projectFilter ?? 'All projects',
        ];

        if (isset($webhook['secret_key'])) {
            $data['Secret Key'] = '(configured)';
        }

        TableFormatter::keyValue($output, $data, 'Webhook Details');

        return self::SUCCESS;
    }
}
