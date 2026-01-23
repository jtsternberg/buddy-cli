<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AuthCommandsTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_TOKEN');
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');

        $this->app = new Application();
    }

    public function testLogoutClearsToken(): void
    {
        // Set a token first
        $setCommand = $this->app->find('config:set');
        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'token', 'value' => 'test-token-123']);

        // Run logout
        $command = $this->app->find('logout');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Logged out successfully.', $tester->getDisplay());

        // Verify token is removed
        $showCommand = $this->app->find('config:show');
        $showTester = new CommandTester($showCommand);
        $showTester->execute(['--json' => true]);

        $output = $showTester->getDisplay();
        $data = json_decode($output, true);
        $this->assertArrayNotHasKey('token', $data);
    }

    public function testLogoutClearsRefreshToken(): void
    {
        // Set both token and refresh_token
        $setCommand = $this->app->find('config:set');

        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'token', 'value' => 'test-token-123']);

        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'refresh_token', 'value' => 'test-refresh-456']);

        // Run logout
        $command = $this->app->find('logout');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Logged out successfully.', $tester->getDisplay());

        // Verify both tokens are removed
        $showCommand = $this->app->find('config:show');
        $showTester = new CommandTester($showCommand);
        $showTester->execute(['--json' => true]);

        $output = $showTester->getDisplay();
        $data = json_decode($output, true);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('refresh_token', $data);
    }

    public function testLogoutSucceedsEvenWithNoCredentials(): void
    {
        // Run logout with no existing credentials
        $command = $this->app->find('logout');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Logged out successfully.', $tester->getDisplay());
    }
}
