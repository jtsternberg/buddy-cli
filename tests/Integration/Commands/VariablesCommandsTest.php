<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class VariablesCommandsTest extends TestCase
{
    private Application $app;
    private BuddyService $mockBuddyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_TOKEN');
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
        $this->setEnv('BUDDY_TOKEN', 'fake-token');

        $this->app = new Application();
        $this->mockBuddyService = $this->createMock(BuddyService::class);
        $this->injectMockBuddyService();
    }

    private function injectMockBuddyService(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('buddyService');
        $property->setValue($this->app, $this->mockBuddyService);
    }

    // vars:set scope validation tests

    public function testVarsSetRejectsMultipleScopes(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'my-project',
            '--pipeline' => '123',
            'key' => 'TEST_KEY',
            'value' => 'test-value',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Only one scope allowed', $output);
        $this->assertStringContainsString('--project OR --pipeline', $output);
    }

    public function testVarsSetActionRequiresPipeline(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--action' => '456',
            'key' => 'TEST_KEY',
            'value' => 'test-value',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('--action requires --pipeline', $output);
    }

    public function testVarsSetRejectsProjectWithAction(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'my-project',
            '--pipeline' => '123',
            '--action' => '456',
            'key' => 'TEST_KEY',
            'value' => 'test-value',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Only one scope allowed', $output);
    }

    public function testVarsSetWithPipelineScopeCreatesVariable(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $this->mockBuddyService->method('createVariable')
            ->willReturn(['id' => 42, 'key' => 'NODE_OPTIONS']);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--pipeline' => '123',
            'key' => 'NODE_OPTIONS',
            'value' => '--max-old-space-size=4096',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created variable: NODE_OPTIONS', $output);
        $this->assertStringContainsString('ID: 42', $output);
    }

    public function testVarsSetWithActionScopeCreatesVariable(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $this->mockBuddyService->method('createVariable')
            ->willReturn(['id' => 43, 'key' => 'DEBUG']);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--pipeline' => '123',
            '--action' => '456',
            'key' => 'DEBUG',
            'value' => 'true',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created variable: DEBUG', $output);
        $this->assertStringContainsString('ID: 43', $output);
    }

    public function testVarsSetWorkspaceScopeWhenNoScopeGiven(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $this->mockBuddyService->method('createVariable')
            ->willReturn(['id' => 44, 'key' => 'GLOBAL_VAR']);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'GLOBAL_VAR',
            'value' => 'hello',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Created variable: GLOBAL_VAR', $tester->getDisplay());
    }

    public function testVarsSetUpdatesExistingVariable(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => [
                ['id' => 99, 'key' => 'EXISTING_KEY'],
            ]]);

        $this->mockBuddyService->method('updateVariable')
            ->willReturn(['id' => 99, 'key' => 'EXISTING_KEY']);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'EXISTING_KEY',
            'value' => 'new-value',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Updated variable: EXISTING_KEY', $tester->getDisplay());
    }

    public function testVarsSetJsonOutput(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $result = ['id' => 50, 'key' => 'MY_KEY', 'value' => 'my_value'];
        $this->mockBuddyService->method('createVariable')
            ->willReturn($result);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'MY_KEY',
            'value' => 'my_value',
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(50, $data['id']);
        $this->assertSame('MY_KEY', $data['key']);
    }
}
