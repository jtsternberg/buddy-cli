<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Webhooks;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('webhooks:list')
            ->setDescription('List webhooks')
            ->setHelp(<<<'HELP'
Lists all webhooks configured in the workspace.

Output columns:
  ID       Webhook identifier
  URL      Target URL for webhook payloads
  Events   Subscribed event types
  Active   Whether the webhook is enabled

Examples:
  buddy webhooks:list
  buddy webhooks:list --workspace=my-workspace
  buddy webhooks:list --json
HELP);

        $this->addWorkspaceOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);

        $response = $this->getBuddyService()->getWebhooks($workspace);
        $webhooks = $response['webhooks'] ?? [];

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $webhooks);
            return self::SUCCESS;
        }

        if (empty($webhooks)) {
            $output->writeln('<comment>No webhooks found.</comment>');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($webhooks as $webhook) {
            $events = $webhook['events'] ?? [];
            $rows[] = [
                $webhook['id'] ?? '-',
                $this->truncateUrl($webhook['target_url'] ?? '-'),
                implode(', ', $events),
            ];
        }

        TableFormatter::render($output, ['ID', 'URL', 'Events'], $rows);

        return self::SUCCESS;
    }

    private function truncateUrl(string $url, int $maxLength = 50): string
    {
        if (strlen($url) <= $maxLength) {
            return $url;
        }
        return substr($url, 0, $maxLength - 3) . '...';
    }
}
