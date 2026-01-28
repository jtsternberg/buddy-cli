<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Services\ConfigService;
use BuddyCli\Tests\TestCase;

class ApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_TOKEN');
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
    }

    public function testApplicationHasCorrectNameAndVersion(): void
    {
        $app = new Application();

        $this->assertSame('Buddy CLI', $app->getName());
        $this->assertSame(Application::VERSION, $app->getVersion());
    }

    public function testApplicationRegistersCommands(): void
    {
        $app = new Application();

        // Check various command groups are registered
        $this->assertTrue($app->has('login'), 'login command missing');
        $this->assertTrue($app->has('logout'), 'logout command missing');
        $this->assertTrue($app->has('config:show'), 'config:show command missing');
        $this->assertTrue($app->has('config:set'), 'config:set command missing');
        $this->assertTrue($app->has('projects:list'), 'projects:list command missing');
        $this->assertTrue($app->has('pipelines:list'), 'pipelines:list command missing');
        $this->assertTrue($app->has('pipelines:run'), 'pipelines:run command missing');
        $this->assertTrue($app->has('executions:list'), 'executions:list command missing');
        $this->assertTrue($app->has('actions:list'), 'actions:list command missing');
        $this->assertTrue($app->has('vars:list'), 'vars:list command missing');
        $this->assertTrue($app->has('webhooks:list'), 'webhooks:list command missing');
        $this->assertTrue($app->has('self:install'), 'self:install command missing');
    }

    public function testGetConfigServiceReturnsConfigService(): void
    {
        $app = new Application();

        $configService = $app->getConfigService();

        $this->assertInstanceOf(ConfigService::class, $configService);
    }

    public function testGetConfigServiceReturnsSameInstance(): void
    {
        $app = new Application();

        $service1 = $app->getConfigService();
        $service2 = $app->getConfigService();

        $this->assertSame($service1, $service2);
    }

    public function testGetBuddyServiceThrowsWithoutToken(): void
    {
        $app = new Application();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No API token configured');

        $app->getBuddyService();
    }

    public function testGetBuddyServiceWithToken(): void
    {
        $this->setEnv('BUDDY_TOKEN', 'test-token-123');
        $app = new Application();

        $buddyService = $app->getBuddyService();

        $this->assertInstanceOf(BuddyService::class, $buddyService);
    }

    public function testGetBuddyServiceWithConfigToken(): void
    {
        $app = new Application();
        $app->getConfigService()->set('token', 'config-token-456');

        $buddyService = $app->getBuddyService();

        $this->assertInstanceOf(BuddyService::class, $buddyService);
    }

    public function testGetBuddyServiceReturnsSameInstance(): void
    {
        $this->setEnv('BUDDY_TOKEN', 'test-token');
        $app = new Application();

        $service1 = $app->getBuddyService();
        $service2 = $app->getBuddyService();

        $this->assertSame($service1, $service2);
    }

    public function testCommandCountIsReasonable(): void
    {
        $app = new Application();

        // Should have a good number of commands registered
        $commands = $app->all();
        // Filter out built-in Symfony commands (help, list, completion)
        $customCommands = array_filter($commands, fn ($name) => !in_array($name, ['help', 'list', 'completion', '_complete']), ARRAY_FILTER_USE_KEY);

        // Should have at least 25 commands
        $this->assertGreaterThanOrEqual(25, count($customCommands));
    }
}
