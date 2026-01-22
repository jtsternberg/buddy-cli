<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Config;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:show')
            ->setDescription('Show current configuration');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->getConfigService()->all();

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $config);
            return self::SUCCESS;
        }

        if (empty($config)) {
            $output->writeln('<comment>No configuration set.</comment>');
            $output->writeln('');
            $output->writeln('Set configuration with:');
            $output->writeln('  buddy config:set token <your-api-token>');
            $output->writeln('  buddy config:set workspace <workspace-name>');
            $output->writeln('  buddy config:set project <project-name>');
            return self::SUCCESS;
        }

        // Mask the token for display
        $displayConfig = [];
        foreach ($config as $key => $value) {
            if ($key === 'token' && strlen($value) > 8) {
                $displayConfig[$key] = substr($value, 0, 4) . '...' . substr($value, -4);
            } else {
                $displayConfig[$key] = $value;
            }
        }

        TableFormatter::keyValue($output, $displayConfig, 'Current Configuration');

        $configPath = $this->getConfigService()->getConfigFilePath();
        if ($configPath !== null) {
            $output->writeln('');
            $output->writeln("<comment>Config file:</comment> {$configPath}");
        }

        return self::SUCCESS;
    }
}
