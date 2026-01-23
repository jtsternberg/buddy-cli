<?php

declare(strict_types=1);

namespace BuddyCli;

use BuddyCli\Commands\Actions\CreateCommand as ActionsCreateCommand;
use BuddyCli\Commands\Actions\DeleteCommand as ActionsDeleteCommand;
use BuddyCli\Commands\Actions\ListCommand as ActionsListCommand;
use BuddyCli\Commands\Actions\ShowCommand as ActionsShowCommand;
use BuddyCli\Commands\Actions\UpdateCommand as ActionsUpdateCommand;
use BuddyCli\Commands\Auth\LoginCommand;
use BuddyCli\Commands\Auth\LogoutCommand;
use BuddyCli\Commands\Config\ClearCommand as ConfigClearCommand;
use BuddyCli\Commands\Config\SetCommand as ConfigSetCommand;
use BuddyCli\Commands\Config\ShowCommand as ConfigShowCommand;
use BuddyCli\Commands\Config\ValidateCommand as ConfigValidateCommand;
use BuddyCli\Commands\Executions\FailedCommand;
use BuddyCli\Commands\Executions\ListCommand as ExecutionsListCommand;
use BuddyCli\Commands\Executions\ShowCommand as ExecutionsShowCommand;
use BuddyCli\Commands\Pipelines\CancelCommand;
use BuddyCli\Commands\Pipelines\CreateCommand;
use BuddyCli\Commands\Pipelines\GetCommand;
use BuddyCli\Commands\Pipelines\ListCommand as PipelinesListCommand;
use BuddyCli\Commands\Pipelines\RetryCommand;
use BuddyCli\Commands\Pipelines\RunCommand;
use BuddyCli\Commands\Pipelines\ShowCommand as PipelinesShowCommand;
use BuddyCli\Commands\Pipelines\UpdateCommand;
use BuddyCli\Commands\Projects\ListCommand as ProjectsListCommand;
use BuddyCli\Commands\Projects\ShowCommand as ProjectsShowCommand;
use BuddyCli\Commands\Variables\DeleteCommand as VariablesDeleteCommand;
use BuddyCli\Commands\Variables\ListCommand as VariablesListCommand;
use BuddyCli\Commands\Variables\SetCommand as VariablesSetCommand;
use BuddyCli\Commands\Variables\ShowCommand as VariablesShowCommand;
use BuddyCli\Commands\Self\InstallCommand as SelfInstallCommand;
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
                    "No API token configured. Run 'buddy login' to authenticate, or set BUDDY_TOKEN environment variable."
                );
            }
            $this->buddyService = new BuddyService($token, $this->configService);
        }

        return $this->buddyService;
    }

    private function registerCommands(): void
    {
        // Auth commands
        $this->addCommand(new LoginCommand($this));
        $this->addCommand(new LogoutCommand($this));

        // Config commands
        $this->addCommand(new ConfigShowCommand($this));
        $this->addCommand(new ConfigSetCommand($this));
        $this->addCommand(new ConfigClearCommand($this));
        $this->addCommand(new ConfigValidateCommand($this));

        // Project commands
        $this->addCommand(new ProjectsListCommand($this));
        $this->addCommand(new ProjectsShowCommand($this));

        // Pipeline commands
        $this->addCommand(new PipelinesListCommand($this));
        $this->addCommand(new PipelinesShowCommand($this));
        $this->addCommand(new RunCommand($this));
        $this->addCommand(new RetryCommand($this));
        $this->addCommand(new CancelCommand($this));
        $this->addCommand(new GetCommand($this));
        $this->addCommand(new CreateCommand($this));
        $this->addCommand(new UpdateCommand($this));

        // Execution commands
        $this->addCommand(new ExecutionsListCommand($this));
        $this->addCommand(new ExecutionsShowCommand($this));
        $this->addCommand(new FailedCommand($this));

        // Action commands
        $this->addCommand(new ActionsListCommand($this));
        $this->addCommand(new ActionsShowCommand($this));
        $this->addCommand(new ActionsCreateCommand($this));
        $this->addCommand(new ActionsUpdateCommand($this));
        $this->addCommand(new ActionsDeleteCommand($this));

        // Variable commands
        $this->addCommand(new VariablesListCommand($this));
        $this->addCommand(new VariablesShowCommand($this));
        $this->addCommand(new VariablesSetCommand($this));
        $this->addCommand(new VariablesDeleteCommand($this));

        // Self commands
        $this->addCommand(new SelfInstallCommand($this));
    }
}
