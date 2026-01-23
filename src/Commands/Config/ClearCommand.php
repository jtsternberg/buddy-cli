<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Config;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:clear')
            ->setDescription('Clear all configuration')
            ->setHelp(<<<'HELP'
<fg=red>⚠ DESTRUCTIVE:</> Removes all stored configuration including authentication.

This deletes the config file (~/.buddy-cli.json) containing:
  - API token (you will need to re-authenticate)
  - Workspace and project defaults
  - OAuth credentials
  - All other saved settings

Use <info>config:show</info> to review current config before clearing.

Example:
  buddy config:clear
HELP);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getConfigService()->clear();

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, ['success' => true]);
            return self::SUCCESS;
        }

        $output->writeln('<info>Configuration cleared.</info>');
        return self::SUCCESS;
    }
}
