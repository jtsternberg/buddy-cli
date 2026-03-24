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
            ->with('ws', 'proj', 1, $this->callback(fn ($f) => $f['per_page'] === 10))
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
            ->with('ws', 'proj', 1, $this->callback(fn ($f) => $f['status'] === 'FAILED'))
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

    public function testExecutionsShowSummaryOutput(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn([
                'id' => 100,
                'status' => 'FAILED',
                'branch' => ['name' => 'main'],
                'to_revision' => ['revision' => 'abc123def456'],
                'creator' => ['name' => 'John Doe'],
                'start_date' => '2024-01-15T10:00:00Z',
                'finish_date' => '2024-01-15T10:05:00Z',
                'action_executions' => [
                    [
                        'action' => ['id' => 1, 'name' => 'Build', 'type' => 'BUILD'],
                        'status' => 'SUCCESSFUL',
                        'start_date' => '2024-01-15T10:00:00Z',
                        'finish_date' => '2024-01-15T10:02:00Z',
                    ],
                    [
                        'action' => ['id' => 2, 'name' => 'Deploy', 'type' => 'DEPLOY'],
                        'status' => 'FAILED',
                        'start_date' => '2024-01-15T10:02:00Z',
                        'finish_date' => '2024-01-15T10:05:00Z',
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
            '--summary' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Execution #100', $output);
        $this->assertStringContainsString('[X] FAILED', $output);
        $this->assertStringContainsString('Actions: 1/2 passed', $output);
        $this->assertStringContainsString('[OK] Build', $output);
        $this->assertStringContainsString('[X] Deploy', $output);
        $this->assertStringContainsString('FAILED ACTIONS:', $output);
        $this->assertStringContainsString('Deploy (DEPLOY)', $output);
    }

    // executions:failed tests

    public function testExecutionsFailedRequiresWorkspace(): void
    {
        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', '--pipeline' => '1', 'execution-id' => '100']);
    }

    public function testExecutionsFailedRequiresProject(): void
    {
        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', '--pipeline' => '1', 'execution-id' => '100']);
    }

    public function testExecutionsFailedRequiresPipeline(): void
    {
        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline ID is required');
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'execution-id' => '100']);
    }

    public function testExecutionsFailedNoFailures(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'SUCCESSFUL',
                'action_executions' => [
                    ['action' => ['id' => 1, 'name' => 'Build'], 'status' => 'SUCCESSFUL'],
                ],
            ]);

        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No failed actions', $tester->getDisplay());
    }

    public function testExecutionsFailedShowsFailedActions(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'FAILED',
                'action_executions' => [
                    ['action' => ['id' => 1, 'name' => 'Build'], 'action_execution_id' => 'aaa111', 'status' => 'SUCCESSFUL'],
                    [
                        'action' => ['id' => 2, 'name' => 'Deploy', 'type' => 'DEPLOY'],
                        'action_execution_id' => 'bbb222',
                        'status' => 'FAILED',
                        'start_date' => '2024-01-15T10:00:00Z',
                        'finish_date' => '2024-01-15T10:05:00Z',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturn(['log' => ['Deploying...', 'Error: connection refused']]);

        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Failed Action: Deploy', $output);
        $this->assertStringContainsString('Error: connection refused', $output);
    }

    public function testExecutionsFailedAnalyzeOutput(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'FAILED',
                'action_executions' => [
                    [
                        'action' => ['id' => 1, 'name' => 'Build', 'type' => 'BUILD'],
                        'action_execution_id' => 'aaa111',
                        'status' => 'FAILED',
                    ],
                    [
                        'action' => ['id' => 2, 'name' => 'Deploy', 'type' => 'DEPLOY'],
                        'action_execution_id' => 'bbb222',
                        'status' => 'FAILED',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturnCallback(function ($ws, $proj, $pipe, $exec, $actionExecId) {
                if ($actionExecId === 'aaa111') {
                    return ['log' => ['Compiling...', 'Error: compilation failed because of missing deps', 'exit code 1']];
                }
                return ['log' => ['Deploying...', 'npm ERR! network timeout']];
            });

        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            '--analyze' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('ERROR SUMMARY', $output);
        $this->assertStringContainsString('Error (', $output);
        $this->assertStringContainsString('Exit Code (', $output);
        $this->assertStringContainsString('NPM Error (', $output);
        $this->assertStringContainsString('FAILED ACTIONS:', $output);
        $this->assertStringContainsString('Build (BUILD)', $output);
        $this->assertStringContainsString('Deploy (DEPLOY)', $output);
    }

    public function testExecutionsFailedAnalyzeDetectsHeapOverflow(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'FAILED',
                'action_executions' => [
                    [
                        'action' => ['id' => 1, 'name' => 'Run NPM Build', 'type' => 'BUILD'],
                        'action_execution_id' => 'aaa111',
                        'status' => 'FAILED',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturn(['log' => [
                'npm ci',
                'vite build',
                'rendering chunks...',
                'FATAL ERROR: Ineffective mark-compacts near heap limit Allocation failed - JavaScript heap out of memory',
                '',
            ]]);

        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            '--analyze' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('ERROR SUMMARY', $output);
        // Should detect specific heap/GC patterns, not generic "Error"
        $this->assertMatchesRegularExpression('/(?:Heap Overflow|GC Exhaustion|JavaScript Heap Overflow)/', $output);
        $this->assertStringContainsString('Run NPM Build', $output);
    }

    public function testExecutionsFailedAnalyzeUnidentifiedShowsRelevantLines(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'FAILED',
                'action_executions' => [
                    [
                        'action' => ['id' => 1, 'name' => 'Mystery Action', 'type' => 'BUILD'],
                        'action_execution_id' => 'aaa111',
                        'status' => 'FAILED',
                    ],
                ],
            ]);

        // Log with no recognizable patterns but some keyword-bearing lines
        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturn(['log' => [
                'starting process...',
                'step 1 ok',
                'step 2 ok',
                'step 3 ok',
                'something went wrong with memory allocation',
                'step 4 ok',
                'step 5 ok',
                'done',
            ]]);

        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            '--analyze' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Unidentified', $output);
        // Should find the keyword-bearing line rather than just last 5 lines
        $this->assertStringContainsString('memory allocation', $output);
    }

    // Hash-to-integer execution ID resolution tests

    public function testExecutionsShowResolvesHashExecutionId(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn([
                'executions' => [
                    [
                        'id' => 4099,
                        'url' => 'https://api.buddy.works/workspaces/ws/projects/proj/pipelines/1/executions/4099',
                        'html_url' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/1/execution/69c2d8c162305ac4bd6107fb',
                        'status' => 'FAILED',
                    ],
                    [
                        'id' => 4098,
                        'url' => 'https://api.buddy.works/workspaces/ws/projects/proj/pipelines/1/executions/4098',
                        'html_url' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/1/execution/abc123def456',
                        'status' => 'SUCCESSFUL',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 4099)
            ->willReturn([
                'id' => 4099,
                'status' => 'FAILED',
                'branch' => ['name' => 'main'],
                'to_revision' => ['revision' => 'abc123def456'],
                'creator' => ['name' => 'Test'],
                'action_executions' => [],
            ]);

        $command = $this->app->find('executions:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '69c2d8c162305ac4bd6107fb',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Resolving execution hash', $output);
        $this->assertStringContainsString('Resolved to execution #4099', $output);
        $this->assertStringContainsString('4099', $output);
    }

    public function testExecutionsShowNumericIdPassesThroughDirectly(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn([
                'id' => 100,
                'status' => 'SUCCESSFUL',
                'branch' => ['name' => 'main'],
                'to_revision' => ['revision' => 'abc123'],
                'creator' => ['name' => 'Test'],
                'action_executions' => [],
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
        // Should NOT show "Resolving execution hash" for numeric IDs
        $this->assertStringNotContainsString('Resolving execution hash', $tester->getDisplay());
    }

    public function testExecutionsShowUnresolvableHashThrowsError(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => [
                [
                    'id' => 100,
                    'url' => 'https://api.buddy.works/workspaces/ws/projects/proj/pipelines/1/executions/100',
                    'status' => 'SUCCESSFUL',
                ],
            ]]);

        $command = $this->app->find('executions:show');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not resolve execution hash');
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => 'deadbeef12345678',
        ]);
    }

    public function testExecutionsFailedResolvesHashExecutionId(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn([
                'executions' => [
                    [
                        'id' => 4099,
                        'url' => 'https://api.buddy.works/workspaces/ws/projects/proj/pipelines/1/executions/4099',
                        'html_url' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/1/execution/69c2d8c162305ac4bd6107fb',
                        'status' => 'FAILED',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 4099)
            ->willReturn([
                'id' => 4099,
                'status' => 'FAILED',
                'action_executions' => [],
            ]);

        $command = $this->app->find('executions:failed');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '69c2d8c162305ac4bd6107fb',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Resolved to execution #4099', $output);
    }

    public function testExecutionsFailedJsonOutput(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'FAILED',
                'action_executions' => [
                    ['action' => ['id' => 1, 'name' => 'Build'], 'status' => 'SUCCESSFUL'],
                    ['action' => ['id' => 2, 'name' => 'Deploy'], 'status' => 'FAILED'],
                ],
            ]);

        $command = $this->app->find('executions:failed');
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
        $this->assertCount(1, $data);
        $this->assertSame('Deploy', $data[0]['action']['name']);
    }

    // executions:actions tests

    public function testExecutionsActionsRequiresWorkspace(): void
    {
        $command = $this->app->find('executions:actions');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', '--pipeline' => '1', 'execution-id' => '100']);
    }

    public function testExecutionsActionsRequiresProject(): void
    {
        $command = $this->app->find('executions:actions');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', '--pipeline' => '1', 'execution-id' => '100']);
    }

    public function testExecutionsActionsRequiresPipeline(): void
    {
        $command = $this->app->find('executions:actions');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline ID is required');
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'execution-id' => '100']);
    }

    public function testExecutionsActionsListsActionExecutions(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn([
                'id' => 100,
                'action_executions' => [
                    [
                        'action' => ['name' => 'Build'],
                        'status' => 'SUCCESSFUL',
                        'start_date' => '2024-01-15T10:00:00Z',
                        'finish_date' => '2024-01-15T10:02:00Z',
                        'action_execution_id' => 'aaa111bbb222',
                    ],
                    [
                        'action' => ['name' => 'Deploy'],
                        'status' => 'FAILED',
                        'start_date' => '2024-01-15T10:02:00Z',
                        'finish_date' => '2024-01-15T10:05:00Z',
                        'action_execution_id' => 'ccc333ddd444',
                    ],
                ],
            ]);

        $command = $this->app->find('executions:actions');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Build', $output);
        $this->assertStringContainsString('Deploy', $output);
        $this->assertStringContainsString('SUCCESSFUL', $output);
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('aaa111bbb222', $output);
        $this->assertStringContainsString('ccc333ddd444', $output);
    }

    public function testExecutionsActionsEmptyActions(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn(['id' => 100, 'action_executions' => []]);

        $command = $this->app->find('executions:actions');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No action executions found', $tester->getDisplay());
    }

    public function testExecutionsActionsJsonOutput(): void
    {
        $actionExecutions = [
            [
                'action' => ['name' => 'Build'],
                'status' => 'SUCCESSFUL',
                'action_execution_id' => 'aaa111',
            ],
        ];
        $this->mockBuddyService->method('getExecution')
            ->willReturn(['id' => 100, 'action_executions' => $actionExecutions]);

        $command = $this->app->find('executions:actions');
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
        $this->assertCount(1, $data);
        $this->assertSame('aaa111', $data[0]['action_execution_id']);
    }

    public function testExecutionsActionsResolvesHashExecutionId(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn([
                'executions' => [
                    [
                        'id' => 191,
                        'url' => 'https://api.buddy.works/workspaces/ws/projects/proj/pipelines/1/executions/191',
                        'html_url' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/1/execution/69c2e5efe09152558bd745e5',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 191)
            ->willReturn([
                'id' => 191,
                'action_executions' => [
                    [
                        'action' => ['name' => 'Build'],
                        'status' => 'SUCCESSFUL',
                        'action_execution_id' => 'aaa111',
                    ],
                ],
            ]);

        $command = $this->app->find('executions:actions');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '69c2e5efe09152558bd745e5',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Resolved to execution #191', $output);
        $this->assertStringContainsString('Build', $output);
    }

    // executions:action-logs tests

    public function testExecutionsActionLogsRequiresWorkspace(): void
    {
        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', '--pipeline' => '1', 'execution-id' => '100', 'action-execution-id' => 'aaa111']);
    }

    public function testExecutionsActionLogsRequiresProject(): void
    {
        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', '--pipeline' => '1', 'execution-id' => '100', 'action-execution-id' => 'aaa111']);
    }

    public function testExecutionsActionLogsRequiresPipeline(): void
    {
        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline ID is required');
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'execution-id' => '100', 'action-execution-id' => 'aaa111']);
    }

    public function testExecutionsActionLogsDisplaysLogs(): void
    {
        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->with('ws', 'proj', 1, 100, 'aaa111bbb222')
            ->willReturn([
                'action' => ['name' => 'DB Migration'],
                'status' => 'SUCCESSFUL',
                'start_date' => '2024-01-15T10:00:00Z',
                'finish_date' => '2024-01-15T10:00:03Z',
                'log' => [
                    'Resolving 167.71.250.215...',
                    'Connection established. Executing commands...',
                    'Migrations have been run successfully.',
                ],
            ]);

        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            'action-execution-id' => 'aaa111bbb222',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('DB Migration', $output);
        $this->assertStringContainsString('SUCCESSFUL', $output);
        $this->assertStringContainsString('Migrations have been run successfully.', $output);
    }

    public function testExecutionsActionLogsEmptyLogs(): void
    {
        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturn([
                'action' => ['name' => 'Notify'],
                'status' => 'SUCCESSFUL',
                'start_date' => '2024-01-15T10:00:00Z',
                'finish_date' => '2024-01-15T10:00:01Z',
                'log' => [],
            ]);

        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            'action-execution-id' => 'aaa111',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No log output available', $tester->getDisplay());
    }

    public function testExecutionsActionLogsJsonOutput(): void
    {
        $actionExecution = [
            'action' => ['name' => 'Build'],
            'status' => 'SUCCESSFUL',
            'action_execution_id' => 'aaa111',
            'log' => ['Building...', 'Done.'],
        ];
        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturn($actionExecution);

        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            'action-execution-id' => 'aaa111',
            '--json' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame('Build', $data['action']['name']);
        $this->assertSame(['Building...', 'Done.'], $data['log']);
    }

    public function testExecutionsActionLogsResolvesHashExecutionId(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn([
                'executions' => [
                    [
                        'id' => 191,
                        'url' => 'https://api.buddy.works/workspaces/ws/projects/proj/pipelines/1/executions/191',
                        'html_url' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/1/execution/69c2e5efe09152558bd745e5',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->with('ws', 'proj', 1, 191, 'ccc333ddd444')
            ->willReturn([
                'action' => ['name' => 'Deploy'],
                'status' => 'SUCCESSFUL',
                'start_date' => '2024-01-15T10:00:00Z',
                'finish_date' => '2024-01-15T10:00:10Z',
                'log' => ['Deploying...', 'Done.'],
            ]);

        $command = $this->app->find('executions:action-logs');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '69c2e5efe09152558bd745e5',
            'action-execution-id' => 'ccc333ddd444',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Resolved to execution #191', $output);
        $this->assertStringContainsString('Deploy', $output);
        $this->assertStringContainsString('Deploying...', $output);
    }

    // executions:show --logs tests (verifying the new API method)

    public function testExecutionsShowLogsUsesActionExecutionId(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn([
                'id' => 100,
                'status' => 'SUCCESSFUL',
                'branch' => ['name' => 'main'],
                'to_revision' => ['revision' => 'abc123def456'],
                'creator' => ['name' => 'Test'],
                'action_executions' => [
                    [
                        'action' => ['name' => 'DB Migration'],
                        'action_execution_id' => 'aaa111',
                        'status' => 'SUCCESSFUL',
                        'start_date' => '2024-01-15T10:00:00Z',
                        'finish_date' => '2024-01-15T10:00:03Z',
                    ],
                    [
                        'action' => ['name' => 'Restart Docker'],
                        'action_execution_id' => 'bbb222',
                        'status' => 'SUCCESSFUL',
                        'start_date' => '2024-01-15T10:00:03Z',
                        'finish_date' => '2024-01-15T10:00:16Z',
                    ],
                ],
            ]);

        $this->mockBuddyService->method('getActionExecutionByExecId')
            ->willReturnCallback(function ($ws, $proj, $pipe, $exec, $actionExecId) {
                if ($actionExecId === 'aaa111') {
                    return ['log' => ['Running migrations...', 'Migrations complete.']];
                }
                return ['log' => ['Restarting containers...', 'All containers healthy.']];
            });

        $command = $this->app->find('executions:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--pipeline' => '1',
            'execution-id' => '100',
            '--logs' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Logs: DB Migration', $output);
        $this->assertStringContainsString('Running migrations...', $output);
        $this->assertStringContainsString('Logs: Restart Docker', $output);
        $this->assertStringContainsString('All containers healthy.', $output);
    }

    public function testExecutionsShowLogsSkipsActionsWithoutExecutionId(): void
    {
        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 100,
                'status' => 'SUCCESSFUL',
                'branch' => ['name' => 'main'],
                'to_revision' => ['revision' => 'abc123'],
                'creator' => ['name' => 'Test'],
                'action_executions' => [
                    [
                        'action' => ['name' => 'Build'],
                        // No action_execution_id
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
            '--logs' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        // Should not crash, just skip logs for actions without execution ID
        $this->assertStringNotContainsString('Logs:', $tester->getDisplay());
    }
}
