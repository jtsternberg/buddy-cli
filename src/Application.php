<?php

declare(strict_types=1);

namespace BuddyCli;

use BuddyCli\Commands\Config\ClearCommand as ConfigClearCommand;
use BuddyCli\Commands\Config\SetCommand as ConfigSetCommand;
use BuddyCli\Commands\Config\ShowCommand as ConfigShowCommand;
use BuddyCli\Commands\Executions\FailedCommand;
use BuddyCli\Commands\Executions\ListCommand as ExecutionsListCommand;
use BuddyCli\Commands\Executions\ShowCommand as ExecutionsShowCommand;
use BuddyCli\Commands\Pipelines\CancelCommand;
use BuddyCli\Commands\Pipelines\ListCommand as PipelinesListCommand;
use BuddyCli\Commands\Pipelines\RetryCommand;
use BuddyCli\Commands\Pipelines\RunCommand;
use BuddyCli\Commands\Pipelines\ShowCommand as PipelinesShowCommand;
use BuddyCli\Commands\Projects\ListCommand as ProjectsListCommand;
use BuddyCli\Commands\Projects\ShowCommand as ProjectsShowCommand;
use BuddyCli\Services\BuddyService;
use BuddyCli\Services\ConfigService;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public const VERSION = '1.0.0';

    private ConfigService $configService;
    private ?BuddyService $buddyService = null;

    public function __construct()
    {
        parent::__construct('Buddy CLI', self::VERSION);

        $this->configService = new ConfigService();

        $this->registerCommands();
    }

    public function getConfigService(): ConfigService
    {
        return $this->configService;
    }

    public function getBuddyService(): BuddyService
    {
        if ($this->buddyService === null) {
            $token = $this->configService->get('token');
            if ($token === null) {
                throw new \RuntimeException(
                    "No API token configured. Set BUDDY_TOKEN environment variable or run 'buddy config:set token <your-token>'"
                );
            }
            $this->buddyService = new BuddyService($token);
        }

        return $this->buddyService;
    }

    private function registerCommands(): void
    {
        // Config commands
        $this->add(new ConfigShowCommand($this));
        $this->add(new ConfigSetCommand($this));
        $this->add(new ConfigClearCommand($this));

        // Project commands
        $this->add(new ProjectsListCommand($this));
        $this->add(new ProjectsShowCommand($this));

        // Pipeline commands
        $this->add(new PipelinesListCommand($this));
        $this->add(new PipelinesShowCommand($this));
        $this->add(new RunCommand($this));
        $this->add(new RetryCommand($this));
        $this->add(new CancelCommand($this));

        // Execution commands
        $this->add(new ExecutionsListCommand($this));
        $this->add(new ExecutionsShowCommand($this));
        $this->add(new FailedCommand($this));
    }
}
