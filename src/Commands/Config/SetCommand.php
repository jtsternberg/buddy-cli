<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Config;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:set')
            ->setDescription('Set a configuration value')
            ->addArgument('key', InputArgument::REQUIRED, 'Configuration key (token, workspace, project)')
            ->addArgument('value', InputArgument::REQUIRED, 'Configuration value')
            ->setHelp(<<<'HELP'
Store a configuration value in the local config file (~/.buddy-cli.json).

Available keys:
  token           API access token (from OAuth or personal access token)
  workspace       Default workspace domain
  project         Default project name
  default_format  Output format: table (default), json, or yaml
  client_id       OAuth client ID for login command
  client_secret   OAuth client secret for login command

Examples:
  buddy config:set workspace my-workspace
  buddy config:set project my-project
  buddy config:set default_format json
  buddy config:set client_id abc123
  buddy config:set client_secret xyz789
HELP);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        $validKeys = ['token', 'workspace', 'project', 'default_format', 'client_id', 'client_secret'];
        if (!in_array($key, $validKeys, true)) {
            $output->writeln("<error>Invalid key '{$key}'. Valid keys: " . implode(', ', $validKeys) . '</error>');
            return self::FAILURE;
        }

        $this->getConfigService()->set($key, $value);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, ['success' => true, 'key' => $key]);
            return self::SUCCESS;
        }

        $output->writeln("<info>Set {$key} successfully.</info>");
        return self::SUCCESS;
    }
}
