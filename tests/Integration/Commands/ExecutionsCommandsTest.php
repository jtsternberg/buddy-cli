<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExecutionsCommandsTest extends TestCase
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

    // executions:list tests

    public function testExecutionsListRequiresWorkspace(): void
    {
        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', '--pipeline' => '1']);
    }

    public function testExecutionsListRequiresProject(): void
    {
        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', '--pipeline' => '1']);
    }

    public function testExecutionsListRequiresPipeline(): void
    {
        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline ID is required');
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj']);
    }

    public function testExecutionsListWithExecutions(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, $this->callback(fn($f) => $f['per_page'] === 10))
            ->willReturn([
                'executions' => [
                    [
                        'id' => 100,
                        'status' => 'SUCCESSFUL',
                        'branch' => ['name' => 'main'],
                        'creator' => ['name' => 'John Doe'],
                        'start_date' => '2024-01-15T10:00:00Z',
                        'finish_date' => '2024-01-15T10:05:00Z',
                    ],
                    [
                        'id' => 99,
                        'status' => 'FAILED',
                        'branch' => ['name' => 'feature'],
                        'creator' => ['name' => 'Jane Doe'],
                    ],
                ],
            ]);

        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('SUCCESSFUL', $output);
        $this->assertStringContainsString('main', $output);
    }

    public function testExecutionsListEmpty(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => []]);

        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No executions found', $tester->getDisplay());
    }

    public function testExecutionsListWithStatusFilter(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, $this->callback(fn($f) => $f['status'] === 'FAILED'))
            ->willReturn(['executions' => []]);

        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            '--status' => 'failed',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecutionsListJsonOutput(): void
    {
        $executions = [
            ['id' => 100, 'status' => 'SUCCESSFUL'],
        ];
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => $executions]);

        $command = $this->app->find('executions:list');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            '--json' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(100, $data[0]['id']);
    }

    // executions:show tests

    public function testExecutionsShowWithDetails(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn([
                'id' => 100,
                'status' => 'SUCCESSFUL',
                'branch' => ['name' => 'main'],
                'to_revision' => ['revision' => 'abc123def456'],
                'creator' => ['name' => 'John Doe'],
                'start_date' => '2024-01-15T10:00:00Z',
                'finish_date' => '2024-01-15T10:05:00Z',
                'comment' => 'Deploy to prod',
                'action_executions' => [
                    [
                        'action' => ['id' => 1, 'name' => 'Build'],
                        'status' => 'SUCCESSFUL',
                        'start_date' => '2024-01-15T10:00:00Z',
                        'finish_date' => '2024-01-15T10:02:00Z',
                    ],
                ],
            ]);

        $command = $this->app->find('executions:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('SUCCESSFUL', $output);
        $this->assertStringContainsString('main', $output);
        $this->assertStringContainsString('abc123de', $output); // Truncated revision
        $this->assertStringContainsString('Build', $output);
    }

    public function testExecutionsShowJsonOutput(): void
    {
        $execution = [
            'id' => 100,
            'status' => 'SUCCESSFUL',
            'branch' => ['name' => 'main'],
        ];
        $this->mockBuddyService->method('getExecution')
            ->willReturn($execution);

        $command = $this->app->find('executions:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            '--json' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(100, $data['id']);
    }
}
