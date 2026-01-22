<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Auth;

use BuddyCli\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LogoutCommand extends Command
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('logout')
            ->setDescription('Clear stored authentication credentials');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->app->getConfigService();

        $config->remove('token');
        $config->remove('refresh_token');

        $output->writeln('<info>Logged out successfully.</info>');

        return self::SUCCESS;
    }
}
