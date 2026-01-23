<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigCommandsTest extends TestCase
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

    // config:show tests

    public function testConfigShowEmptyConfig(): void
    {
        $command = $this->app->find('config:show');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No configuration set', $tester->getDisplay());
    }

    public function testConfigShowWithConfig(): void
    {
        // Set some config first
        $setCommand = $this->app->find('config:set');
        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'workspace', 'value' => 'my-workspace']);

        $command = $this->app->find('config:show');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('workspace', $tester->getDisplay());
        $this->assertStringContainsString('my-workspace', $tester->getDisplay());
    }

    public function testConfigShowMasksToken(): void
    {
        $setCommand = $this->app->find('config:set');
        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'token', 'value' => 'abcd1234efgh5678']);

        $command = $this->app->find('config:show');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        $this->assertStringNotContainsString('abcd1234efgh5678', $output);
        $this->assertStringContainsString('abcd...5678', $output);
    }

    public function testConfigShowJsonOutput(): void
    {
        $setCommand = $this->app->find('config:set');
        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'workspace', 'value' => 'test-ws']);

        $command = $this->app->find('config:show');
        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame('test-ws', $data['workspace']);
    }

    // config:set tests

    public function testConfigSetValidKey(): void
    {
        $command = $this->app->find('config:set');
        $tester = new CommandTester($command);

        $tester->execute(['key' => 'workspace', 'value' => 'new-workspace']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Set workspace successfully', $tester->getDisplay());
    }

    public function testConfigSetInvalidKey(): void
    {
        $command = $this->app->find('config:set');
        $tester = new CommandTester($command);

        $tester->execute(['key' => 'invalid_key', 'value' => 'some-value']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid key', $tester->getDisplay());
    }

    public function testConfigSetJsonOutput(): void
    {
        $command = $this->app->find('config:set');
        $tester = new CommandTester($command);

        $tester->execute(['key' => 'project', 'value' => 'my-project', '--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertSame('project', $data['key']);
    }

    public function testConfigSetAllValidKeys(): void
    {
        $command = $this->app->find('config:set');
        $validKeys = ['token', 'workspace', 'project', 'default_format', 'client_id', 'client_secret'];

        foreach ($validKeys as $key) {
            $tester = new CommandTester($command);
            $tester->execute(['key' => $key, 'value' => "test-{$key}"]);
            $this->assertSame(0, $tester->getStatusCode(), "Failed for key: {$key}");
        }
    }

    // config:clear tests

    public function testConfigClear(): void
    {
        // Set some config first
        $setCommand = $this->app->find('config:set');
        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'workspace', 'value' => 'to-clear']);

        $command = $this->app->find('config:clear');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration cleared', $tester->getDisplay());

        // Verify config is empty
        $showCommand = $this->app->find('config:show');
        $showTester = new CommandTester($showCommand);
        $showTester->execute([]);
        $this->assertStringContainsString('No configuration set', $showTester->getDisplay());
    }

    public function testConfigClearJsonOutput(): void
    {
        $command = $this->app->find('config:clear');
        $tester = new CommandTester($command);

        $tester->execute(['--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
    }
}
