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
            ->addArgument('value', InputArgument::REQUIRED, 'Configuration value');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        $validKeys = ['token', 'workspace', 'project', 'default_format'];
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
