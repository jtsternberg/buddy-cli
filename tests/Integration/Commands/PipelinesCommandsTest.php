<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Sdk\Exceptions\BuddyResponseException;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PipelinesCommandsTest extends TestCase
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

    // pipelines:list tests

    public function testPipelinesListRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj']);
    }

    public function testPipelinesListRequiresProject(): void
    {
        $command = $this->app->find('pipelines:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws']);
    }

    public function testPipelinesListWithPipelines(): void
    {
        $this->mockBuddyService->method('getPipelines')
            ->with('my-ws', 'my-proj')
            ->willReturn([
                'pipelines' => [
                    [
                        'id' => 1,
                        'name' => 'Deploy',
                        'last_execution_status' => 'SUCCESSFUL',
                        'refs' => ['refs/heads/main'],
                    ],
                    [
                        'id' => 2,
                        'name' => 'Test',
                        'last_execution_status' => 'FAILED',
                        'refs' => [],
                        'events' => [['type' => 'PUSH', 'refs' => ['refs/heads/main']]],
                    ],
                ],
            ]);

        $command = $this->app->find('pipelines:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'my-ws', '--project' => 'my-proj']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Deploy', $output);
        $this->assertStringContainsString('Test', $output);
        $this->assertStringContainsString('SUCCESSFUL', $output);
        $this->assertStringContainsString('MANUAL', $output);
        $this->assertStringContainsString('PUSH', $output);
        // The API returns no last-run date on pipelines.
        $this->assertStringNotContainsString('Last Run', $output);
    }

    public function testPipelinesListEmpty(): void
    {
        $this->mockBuddyService->method('getPipelines')
            ->willReturn(['pipelines' => []]);

        $command = $this->app->find('pipelines:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No pipelines found', $tester->getDisplay());
    }

    public function testPipelinesListJsonOutput(): void
    {
        $pipelines = [
            ['id' => 1, 'name' => 'Deploy'],
        ];
        $this->mockBuddyService->method('getPipelines')
            ->willReturn(['pipelines' => $pipelines]);

        $command = $this->app->find('pipelines:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertCount(1, $data);
        $this->assertSame('Deploy', $data[0]['name']);
    }

    // pipelines:run tests

    public function testPipelinesRunSuccess(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'refs' => ['refs/heads/main']]);

        $this->mockBuddyService->method('runExecution')
            ->with('ws', 'proj', 1, [])
            ->willReturn([
                'id' => 100,
                'status' => 'INPROGRESS',
                'branch' => ['name' => 'main'],
            ]);

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('INPROGRESS', $output);
    }

    public function testPipelinesRunWildcardRequiresBranch(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'refs' => ['refs/heads/*']]); // Wildcard pipeline

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('wildcards', $tester->getDisplay());
    }

    public function testPipelinesRunCodelessPipelineNeedsNoBranch(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Ops', 'refs' => []]);

        $this->mockBuddyService->expects($this->once())
            ->method('runExecution')
            ->with('ws', 'proj', 1, [])
            ->willReturn(['id' => 102, 'status' => 'INPROGRESS']);

        $tester = new CommandTester($this->app->find('pipelines:run'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesRunWithBranch(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/*']]); // Wildcard

        $this->mockBuddyService->method('runExecution')
            ->with('ws', 'proj', 1, ['branch' => ['name' => 'feature/test']])
            ->willReturn([
                'id' => 101,
                'status' => 'ENQUEUED',
                'branch' => ['name' => 'feature/test'],
            ]);

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--branch' => 'feature/test',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('101', $tester->getDisplay());
    }

    public function testPipelinesRunJsonOutput(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/main']]);

        $execution = ['id' => 100, 'status' => 'INPROGRESS'];
        $this->mockBuddyService->method('runExecution')
            ->willReturn($execution);

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--json' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(100, $data['id']);
    }

    public function testPipelinesRunFollowPrintsActionProgress(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/main']]);

        $this->mockBuddyService->method('runExecution')
            ->willReturn(['id' => 200, 'status' => 'INPROGRESS']);

        $this->mockBuddyService->expects($this->exactly(2))
            ->method('getExecution')
            ->willReturnOnConsecutiveCalls(
                [
                    'id' => 200,
                    'status' => 'INPROGRESS',
                    'action_executions' => [
                        ['action' => ['name' => 'Run PHPUnit'], 'status' => 'INPROGRESS', 'start_date' => '2024-01-15T10:00:00Z'],
                    ],
                ],
                [
                    'id' => 200,
                    'status' => 'SUCCESSFUL',
                    'action_executions' => [
                        ['action' => ['name' => 'Run PHPUnit'], 'status' => 'SUCCESSFUL', 'start_date' => '2024-01-15T10:00:00Z', 'finish_date' => '2024-01-15T10:00:04Z'],
                    ],
                ]
            );

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--follow' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Run PHPUnit', $output);
        $this->assertStringContainsString('running...', $output);
        $this->assertStringContainsString('4s', $output);
    }

    public function testPipelinesRunFollowPrintsFailedAndReturnsFailure(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/main']]);

        $this->mockBuddyService->method('runExecution')
            ->willReturn(['id' => 201, 'status' => 'INPROGRESS']);

        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 201,
                'status' => 'FAILED',
                'action_executions' => [
                    ['id' => 'a1', 'action' => ['name' => 'Run PHPUnit'], 'status' => 'FAILED', 'start_date' => '2024-01-15T10:00:00Z', 'finish_date' => '2024-01-15T10:00:09Z'],
                ],
            ]);

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--follow' => true,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Run PHPUnit', $output);
        $this->assertStringContainsString('9s', $output);
    }

    public function testPipelinesRunFollowRendersSkippedAction(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/main']]);

        $this->mockBuddyService->method('runExecution')
            ->willReturn(['id' => 202, 'status' => 'INPROGRESS']);

        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 202,
                'status' => 'SUCCESSFUL',
                'action_executions' => [
                    ['id' => 'b1', 'action' => ['name' => 'Deploy to staging'], 'status' => 'SKIPPED'],
                ],
            ]);

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--follow' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Deploy to staging', $output);
        $this->assertStringContainsString('skipped', $output);
    }

    public function testPipelinesRunFollowTracksDuplicateActionNamesIndependently(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/main']]);

        $this->mockBuddyService->method('runExecution')
            ->willReturn(['id' => 203, 'status' => 'INPROGRESS']);

        $this->mockBuddyService->method('getExecution')
            ->willReturn([
                'id' => 203,
                'status' => 'SUCCESSFUL',
                'action_executions' => [
                    ['id' => 'c1', 'action' => ['name' => 'Run tests'], 'status' => 'SUCCESSFUL', 'start_date' => '2024-01-15T10:00:00Z', 'finish_date' => '2024-01-15T10:00:02Z'],
                    ['id' => 'c2', 'action' => ['name' => 'Run tests'], 'status' => 'FAILED',     'start_date' => '2024-01-15T10:00:00Z', 'finish_date' => '2024-01-15T10:00:05Z'],
                ],
            ]);

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--follow' => true,
        ]);

        $output = $tester->getDisplay();
        // Both same-named actions should appear as separate lines (different ids => not collapsed).
        $this->assertSame(2, substr_count($output, 'Run tests'));
    }

    public function testPipelinesRunHandlesApiError(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'refs' => ['refs/heads/main']]);

        $this->mockBuddyService->method('runExecution')
            ->willThrowException(new BuddyResponseException(400, [], '{"error":"Pipeline is disabled"}'));

        $command = $this->app->find('pipelines:run');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('400', $tester->getDisplay());
    }

    // pipelines:retry tests

    public function testPipelinesRetryRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['pipeline-id' => '1', '--project' => 'proj']);
    }

    public function testPipelinesRetryRequiresProject(): void
    {
        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['pipeline-id' => '1', '--workspace' => 'ws']);
    }

    public function testPipelinesRetryNoExecutions(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn(['executions' => []]);

        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No failed or terminated execution', $tester->getDisplay());
    }

    public function testPipelinesRetrySkipsNewerQueuedAndRunningRuns(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => [
                ['id' => 18, 'status' => 'ENQUEUED'],
                ['id' => 17, 'status' => 'TERMINATED'],
                ['id' => 16, 'status' => 'INPROGRESS'],
            ]]);

        $this->mockBuddyService->expects($this->once())
            ->method('retryExecution')
            ->with('ws', 'proj', 1, 17)
            ->willReturn(['id' => 19, 'status' => 'INPROGRESS']);

        $tester = new CommandTester($this->app->find('pipelines:retry'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesRetryIgnoresSuccessfulRuns(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => [['id' => 14, 'status' => 'SUCCESSFUL']]]);
        $this->mockBuddyService->expects($this->never())->method('retryExecution');

        $tester = new CommandTester($this->app->find('pipelines:retry'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testPipelinesRetrySuccess(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn(['executions' => [['id' => 50, 'status' => 'FAILED']]]);

        $this->mockBuddyService->method('retryExecution')
            ->with('ws', 'proj', 1, 50)
            ->willReturn([
                'id' => 51,
                'status' => 'INPROGRESS',
            ]);

        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('51', $output);
        $this->assertStringContainsString('INPROGRESS', $output);
    }

    public function testPipelinesRetryJsonOutput(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn(['executions' => [['id' => 50, 'status' => 'FAILED']]]);

        $execution = ['id' => 51, 'status' => 'INPROGRESS'];
        $this->mockBuddyService->method('retryExecution')
            ->with('ws', 'proj', 1, 50)
            ->willReturn($execution);

        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--json' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(51, $data['id']);
        $this->assertSame('INPROGRESS', $data['status']);
    }

    public function testPipelinesRetryResourceNotFoundSuggestsBranchRun(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn(['executions' => [
                [
                    'id' => 50,
                    'status' => 'FAILED',
                    'branch' => ['name' => 'jt/feature/my-branch'],
                ],
            ]]);

        $this->mockBuddyService->method('retryExecution')
            ->willThrowException(new \RuntimeException('Resource not found'));

        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Retry failed: Resource not found', $output);
        $this->assertStringContainsString('Last execution #50', $output);
        $this->assertStringContainsString('FAILED', $output);
        $this->assertStringContainsString('jt/feature/my-branch', $output);
        $this->assertStringContainsString('pipelines:run 1 --branch=jt/feature/my-branch', $output);
    }

    public function testPipelinesRetryGenericErrorDoesNotSuggestBranch(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn(['executions' => [
                [
                    'id' => 50,
                    'status' => 'FAILED',
                    'branch' => ['name' => 'main'],
                ],
            ]]);

        $this->mockBuddyService->method('retryExecution')
            ->willThrowException(new \RuntimeException('Internal server error'));

        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Retry failed: Internal server error', $output);
        // Should NOT suggest branch run for non-"not found" errors
        $this->assertStringNotContainsString('pipelines:run', $output);
    }

    // pipelines:cancel tests

    public function testPipelinesCancelRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:cancel');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['pipeline-id' => '1', '--project' => 'proj']);
    }

    public function testPipelinesCancelRequiresProject(): void
    {
        $command = $this->app->find('pipelines:cancel');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['pipeline-id' => '1', '--workspace' => 'ws']);
    }

    public function testPipelinesCancelNoRunningExecution(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn(['executions' => []]);

        $command = $this->app->find('pipelines:cancel');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No running execution found', $tester->getDisplay());
    }

    public function testPipelinesCancelSkipsNewerQueuedRun(): void
    {
        // Buddy ignores a status filter on this endpoint and lists newest first.
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => [
                ['id' => 18, 'status' => 'ENQUEUED'],
                ['id' => 17, 'status' => 'TERMINATED'],
                ['id' => 16, 'status' => 'INPROGRESS'],
            ]]);

        $this->mockBuddyService->expects($this->once())
            ->method('cancelExecution')
            ->with('ws', 'proj', 1, 16)
            ->willReturn(['id' => 16, 'status' => 'TERMINATING']);

        $tester = new CommandTester($this->app->find('pipelines:cancel'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesCancelExecutionOptionTargetsThatRun(): void
    {
        $this->mockBuddyService->expects($this->never())->method('getExecutions');
        $this->mockBuddyService->expects($this->once())
            ->method('cancelExecution')
            ->with('ws', 'proj', 1, 18)
            ->willReturn(['id' => 18, 'status' => 'TERMINATED']);

        $tester = new CommandTester($this->app->find('pipelines:cancel'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1', '--execution' => '18']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesCancelRejectsNonNumericExecution(): void
    {
        $this->mockBuddyService->expects($this->never())->method('cancelExecution');

        $tester = new CommandTester($this->app->find('pipelines:cancel'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1', '--execution' => 'latest']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testPipelinesCancelRefusesWhenSeveralRunsAreRunning(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => [
                ['id' => 21, 'status' => 'INPROGRESS'],
                ['id' => 20, 'status' => 'INPROGRESS'],
            ]]);
        $this->mockBuddyService->expects($this->never())->method('cancelExecution');

        $tester = new CommandTester($this->app->find('pipelines:cancel'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('#21', $tester->getDisplay());
        $this->assertStringContainsString('#20', $tester->getDisplay());
        $this->assertStringContainsString('--execution', $tester->getDisplay());
    }

    public function testPipelinesCancelNamesQueuedRunsWhenNoneRunning(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->willReturn(['executions' => [['id' => 18, 'status' => 'ENQUEUED']]]);
        $this->mockBuddyService->expects($this->never())->method('cancelExecution');

        $tester = new CommandTester($this->app->find('pipelines:cancel'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No running execution', $tester->getDisplay());
        $this->assertStringContainsString('#18', $tester->getDisplay());
        $this->assertStringContainsString('--execution=18', $tester->getDisplay());
    }

    public function testPipelinesCancelSuccess(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn([
                'executions' => [
                    ['id' => 100, 'status' => 'INPROGRESS'],
                ],
            ]);

        $this->mockBuddyService->method('cancelExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn([
                'id' => 100,
                'status' => 'TERMINATED',
            ]);

        $command = $this->app->find('pipelines:cancel');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('100', $output);
        $this->assertStringContainsString('TERMINATED', $output);
    }

    public function testPipelinesCancelJsonOutput(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 20])
            ->willReturn([
                'executions' => [
                    ['id' => 100, 'status' => 'INPROGRESS'],
                ],
            ]);

        $cancelledExecution = ['id' => 100, 'status' => 'TERMINATED'];
        $this->mockBuddyService->method('cancelExecution')
            ->with('ws', 'proj', 1, 100)
            ->willReturn($cancelledExecution);

        $command = $this->app->find('pipelines:cancel');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(100, $data['id']);
        $this->assertSame('TERMINATED', $data['status']);
    }

    // pipelines:get tests

    public function testPipelinesGetRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:get');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', 'pipeline-id' => '1']);
    }

    public function testPipelinesGetRequiresProject(): void
    {
        $command = $this->app->find('pipelines:get');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', 'pipeline-id' => '1']);
    }

    public function testPipelinesGetSuccess(): void
    {
        $this->mockBuddyService->method('getPipelineYaml')
            ->with('ws', 'proj', 1)
            ->willReturn("pipeline: Deploy\n");

        $command = $this->app->find('pipelines:get');
        $tester = new CommandTester($command);

        $originalDir = getcwd();
        chdir($this->tempDir);
        try {
            $tester->execute([
                '--workspace' => 'ws',
                '--project' => 'proj',
                'pipeline-id' => '1',
            ]);
        } finally {
            chdir($originalDir);
        }

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Saved pipeline config to pipeline-1.yaml', $tester->getDisplay());
        $this->assertFileExists($this->tempDir . '/pipeline-1.yaml');
    }

    public function testPipelinesGetCustomOutput(): void
    {
        $this->mockBuddyService->method('getPipelineYaml')
            ->willReturn("pipeline: Deploy\n");

        $command = $this->app->find('pipelines:get');
        $tester = new CommandTester($command);

        $customPath = $this->tempDir . '/custom-output.yaml';
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--output' => $customPath,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Saved pipeline config to {$customPath}", $tester->getDisplay());
        $this->assertFileExists($customPath);
    }

    public function testPipelinesGetYamlContent(): void
    {
        $this->mockBuddyService->method('getPipelineYaml')
            ->willReturn("pipeline: Deploy\nactions:\n  - action: Build\n    type: BUILD\n");

        $command = $this->app->find('pipelines:get');
        $tester = new CommandTester($command);

        $outputPath = $this->tempDir . '/test-pipeline.yaml';
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--output' => $outputPath,
        ]);

        $this->assertFileExists($outputPath);
        $content = file_get_contents($outputPath);

        $this->assertStringContainsString('pipeline: Deploy', $content);
        $this->assertStringContainsString('action: Build', $content);
        $this->assertStringContainsString('type: BUILD', $content);
    }

    // pipelines:show tests

    public function testPipelinesShowRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', 'pipeline-id' => '1']);
    }

    public function testPipelinesShowRequiresProject(): void
    {
        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', 'pipeline-id' => '1']);
    }

    public function testPipelinesShowDisplaysPipelineDetails(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->with('ws', 'proj', 1)
            ->willReturn([
                'id' => 1,
                'name' => 'Deploy Production',
                'last_execution_status' => 'SUCCESSFUL',
                'refs' => ['refs/heads/main'],
                'create_date' => '2023-06-01T09:00:00Z',
            ]);

        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 1])
            ->willReturn(['executions' => [['id' => 5, 'start_date' => '2024-01-15T10:00:00Z']]]);

        $this->mockBuddyService->method('getPipelineActions')
            ->with('ws', 'proj', 1)
            ->willReturn(['actions' => []]);

        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Deploy Production', $output);
        $this->assertStringContainsString('SUCCESSFUL', $output);
        $this->assertStringContainsString('MANUAL', $output);
        $this->assertStringContainsString('refs/heads/main', $output);
        $this->assertDoesNotMatchRegularExpression('/Last Run\s+-/', $output);
    }

    public function testPipelinesShowDisplaysPushTriggerAndItsRefs(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn([
                'id' => 2,
                'name' => 'Staging',
                'refs' => [],
                'events' => [['type' => 'PUSH', 'refs' => ['refs/heads/main']]],
            ]);
        $this->mockBuddyService->method('getPipelineActions')->willReturn(['actions' => []]);

        $tester = new CommandTester($this->app->find('pipelines:show'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '2']);

        $this->assertMatchesRegularExpression('/Trigger\s+PUSH/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('#Branch/Ref\s+refs/heads/main#', $tester->getDisplay());
    }

    public function testPipelinesShowDisplaysActions(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Deploy']);

        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn([
                'actions' => [
                    [
                        'id' => 10,
                        'name' => 'Build Application',
                        'type' => 'BUILD',
                        'trigger_conditions' => [],
                    ],
                    [
                        'id' => 20,
                        'name' => 'Deploy to Server',
                        'type' => 'SFTP',
                        'trigger_conditions' => [
                            ['trigger_condition' => 'VAR_IS', 'trigger_variable_key' => 'ENV', 'trigger_variable_value' => 'prod'],
                        ],
                    ],
                ],
            ]);

        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Actions:', $output);
        $this->assertStringContainsString('Build Application', $output);
        $this->assertStringContainsString('BUILD', $output);
        $this->assertStringContainsString('Deploy to Server', $output);
        $this->assertStringContainsString('SFTP', $output);
        $this->assertStringContainsString('VAR_IS:ENV=prod', $output);
    }

    public function testPipelinesShowJsonOutput(): void
    {
        $pipeline = [
            'id' => 1,
            'name' => 'Deploy',
            'refs' => ['refs/heads/main'],
        ];
        $this->mockBuddyService->method('getPipeline')
            ->willReturn($pipeline);

        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn(['actions' => []]);

        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(1, $data['id']);
        $this->assertSame('Deploy', $data['name']);
        $this->assertSame(['refs/heads/main'], $data['refs']);
    }

    public function testPipelinesShowYamlOutput(): void
    {
        $this->mockBuddyService->method('getPipelineYaml')
            ->willReturn("pipeline: Deploy\nactions:\n  - action: Build\n    type: BUILD\n");

        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--yaml' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('pipeline: Deploy', $output);
        $this->assertStringContainsString('action: Build', $output);
        $this->assertStringContainsString('type: BUILD', $output);
    }

    public function testPipelinesShowNoActions(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Empty Pipeline']);

        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn(['actions' => []]);

        $command = $this->app->find('pipelines:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Empty Pipeline', $output);
        // Should not show Actions section when empty
        $this->assertStringNotContainsString('Actions:', $output);
    }

    // pipelines:create tests

    public function testPipelinesCreateRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', 'file' => 'test.yaml']);
    }

    public function testPipelinesCreateRequiresProject(): void
    {
        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', 'file' => 'test.yaml']);
    }

    public function testPipelinesCreateFileNotFound(): void
    {
        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'file' => '/nonexistent/file.yaml',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File not found', $tester->getDisplay());
    }

    public function testPipelinesCreateRequiresName(): void
    {
        $yamlFile = $this->writeTempFile('pipeline.yaml', "trigger_mode: MANUAL\n");

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'file' => $yamlFile,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Pipeline name is required', $tester->getDisplay());
    }

    public function testPipelinesCreateSuccess(): void
    {
        $yaml = <<<'YAML'
- pipeline: "Test Pipeline"
  refs:
    - refs/heads/main
YAML;
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->expects($this->once())
            ->method('createPipeline')
            ->with('ws', 'proj', [
                'name' => 'Test Pipeline',
                'refs' => ['refs/heads/main'],
            ])
            ->willReturn(['id' => 99, 'name' => 'Test Pipeline']);

        $this->mockBuddyService->expects($this->once())
            ->method('updatePipelineYaml')
            ->with('ws', 'proj', 99, $yaml);

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'file' => $yamlFile,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created pipeline: Test Pipeline', $output);
        $this->assertStringContainsString('ID: 99', $output);
    }

    public function testPipelinesCreateMapsLegacyRefNameToRefs(): void
    {
        $yaml = "name: Legacy\ntrigger_mode: MANUAL\nref_name: refs/heads/main\n";
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->expects($this->once())
            ->method('createPipeline')
            ->with('ws', 'proj', ['name' => 'Legacy', 'refs' => ['refs/heads/main']])
            ->willReturn(['id' => 98, 'name' => 'Legacy']);
        $this->mockBuddyService->method('updatePipelineYaml');

        $tester = new CommandTester($this->app->find('pipelines:create'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'file' => $yamlFile]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesCreateWithActions(): void
    {
        $yaml = <<<'YAML'
name: "Pipeline with Actions"
trigger_mode: MANUAL
ref_name: refs/heads/main
actions:
  - name: "Build"
    type: BUILD
    docker_image_name: php
    execute_commands:
      - composer install
  - name: "Deploy"
    type: SFTP
YAML;
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->expects($this->once())
            ->method('createPipeline')
            ->willReturn(['id' => 100, 'name' => 'Pipeline with Actions']);

        $this->mockBuddyService->expects($this->once())
            ->method('updatePipelineYaml')
            ->with('ws', 'proj', 100, $yaml);

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'file' => $yamlFile,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created pipeline: Pipeline with Actions', $output);
    }

    public function testPipelinesCreateHandlesApiError(): void
    {
        $yaml = "name: Test\n";
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->method('createPipeline')
            ->willThrowException(new BuddyResponseException(400, [], '{"error":"Invalid config"}'));

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'file' => $yamlFile,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Import failed', $tester->getDisplay());
    }

    public function testPipelinesCreateFromFileJsonOutput(): void
    {
        $yaml = <<<'YAML'
name: "Test Pipeline"
trigger_mode: MANUAL
ref_name: refs/heads/main
YAML;
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->method('createPipeline')
            ->willReturn(['id' => 42, 'name' => 'Test Pipeline', 'trigger_mode' => 'MANUAL']);

        $this->mockBuddyService->method('updatePipelineYaml');

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'file' => $yamlFile,
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(42, $data['id']);
        $this->assertSame('Test Pipeline', $data['name']);
    }

    // pipelines:create flag-based tests

    public function testPipelinesCreateFromFlagsRequiresName(): void
    {
        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Pipeline name is required', $tester->getDisplay());
    }

    public function testPipelinesCreateFromFlagsSuccess(): void
    {
        $this->mockBuddyService->expects($this->once())
            ->method('createPipeline')
            ->with('ws', 'proj', [
                'name' => 'My Pipeline',
                'refs' => ['refs/heads/main'],
            ])
            ->willReturn(['id' => 55, 'name' => 'My Pipeline', 'refs' => ['refs/heads/main']]);

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'My Pipeline',
            '--on' => 'MANUAL',
            '--refs' => 'refs/heads/main',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created pipeline: My Pipeline', $output);
        $this->assertStringContainsString('ID: 55', $output);
    }

    public function testPipelinesCreateFromFlagsNameOnly(): void
    {
        $this->mockBuddyService->expects($this->once())
            ->method('createPipeline')
            ->with('ws', 'proj', ['name' => 'Simple Pipeline'])
            ->willReturn(['id' => 10, 'name' => 'Simple Pipeline']);

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'Simple Pipeline',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Created pipeline: Simple Pipeline', $tester->getDisplay());
        $this->assertStringContainsString('ID: 10', $tester->getDisplay());
    }

    public function testPipelinesCreateFromFlagsOnEveryPushSendsPushEvent(): void
    {
        $this->mockBuddyService->expects($this->once())
            ->method('createPipeline')
            ->with('ws', 'proj', [
                'name' => 'My Pipeline',
                'events' => [['type' => 'PUSH', 'refs' => ['refs/heads/main']]],
            ])
            ->willReturn(['id' => 20, 'name' => 'My Pipeline']);

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'My Pipeline',
            '--on' => 'on_every_push',
            '--refs' => 'refs/heads/main',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesCreateFromFlagsOnEveryPushRequiresRefs(): void
    {
        $this->mockBuddyService->expects($this->never())->method('createPipeline');

        $tester = new CommandTester($this->app->find('pipelines:create'));
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'My Pipeline',
            '--on' => 'ON_EVERY_PUSH',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('--refs', $tester->getDisplay());
    }

    public function testPipelinesCreateFromFlagsScheduledPointsToYaml(): void
    {
        $this->mockBuddyService->expects($this->never())->method('createPipeline');

        $tester = new CommandTester($this->app->find('pipelines:create'));
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'My Pipeline',
            '--on' => 'SCHEDULED',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('YAML', $tester->getDisplay());
    }

    public function testPipelinesCreateFromFlagsRejectsUnknownTrigger(): void
    {
        $this->mockBuddyService->expects($this->never())->method('createPipeline');

        $tester = new CommandTester($this->app->find('pipelines:create'));
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'My Pipeline',
            '--on' => 'SOMETIMES',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('MANUAL, ON_EVERY_PUSH', $tester->getDisplay());
    }

    public function testPipelinesCreateFromFlagsJsonOutput(): void
    {
        $pipeline = ['id' => 77, 'name' => 'CI Pipeline', 'events' => [['type' => 'PUSH', 'refs' => ['refs/heads/main']]]];
        $this->mockBuddyService->method('createPipeline')
            ->willReturn($pipeline);

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'CI Pipeline',
            '--on' => 'ON_EVERY_PUSH',
            '--refs' => 'refs/heads/main',
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(77, $data['id']);
        $this->assertSame('CI Pipeline', $data['name']);
        $this->assertSame('PUSH', $data['events'][0]['type']);
    }

    public function testPipelinesCreateFromFlagsHandlesApiError(): void
    {
        $this->mockBuddyService->method('createPipeline')
            ->willThrowException(new BuddyResponseException(422, [], '{"error":"Validation error"}'));

        $command = $this->app->find('pipelines:create');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            '--name' => 'Bad Pipeline',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to create pipeline', $tester->getDisplay());
    }

    // pipelines:update tests

    public function testPipelinesUpdateRequiresWorkspace(): void
    {
        $command = $this->app->find('pipelines:update');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', 'pipeline-id' => '1', 'file' => 'test.yaml']);
    }

    public function testPipelinesUpdateRequiresProject(): void
    {
        $command = $this->app->find('pipelines:update');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', 'pipeline-id' => '1', 'file' => 'test.yaml']);
    }

    public function testPipelinesUpdateFileNotFound(): void
    {
        $command = $this->app->find('pipelines:update');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            'file' => '/nonexistent/file.yaml',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File not found', $tester->getDisplay());
    }

    public function testPipelinesUpdateSuccess(): void
    {
        $yaml = <<<'YAML'
name: "Updated Pipeline"
trigger_mode: ON_EVERY_PUSH
ref_name: refs/heads/develop
YAML;
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->expects($this->once())
            ->method('updatePipelineYaml')
            ->with('ws', 'proj', 123, $yaml);

        $command = $this->app->find('pipelines:update');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '123',
            'file' => $yamlFile,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Updated pipeline #123', $output);
    }

    public function testPipelinesUpdateWithActionsInYaml(): void
    {
        $yaml = <<<'YAML'
name: "Pipeline"
actions:
  - name: "Build"
    type: BUILD
YAML;
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->expects($this->once())
            ->method('updatePipelineYaml')
            ->with('ws', 'proj', 1, $yaml);

        $command = $this->app->find('pipelines:update');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            'file' => $yamlFile,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testPipelinesUpdateHandlesApiError(): void
    {
        $yaml = "name: Test\n";
        $yamlFile = $this->writeTempFile('pipeline.yaml', $yaml);

        $this->mockBuddyService->method('updatePipelineYaml')
            ->willThrowException(new BuddyResponseException(404, [], '{"error":"Pipeline not found"}'));

        $command = $this->app->find('pipelines:update');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '999',
            'file' => $yamlFile,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Update failed', $tester->getDisplay());
    }

    // pipelines:settings tests

    public function testPipelinesSettingsDisplaysSettings(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->with('ws', 'proj', 1)
            ->willReturn([
                'id' => 1,
                'name' => 'Deploy',
                'refs' => ['refs/heads/main'],
                'priority' => 'HIGH',
                'variables' => [
                    ['key' => 'ENV', 'type' => 'VAR', 'settable' => true, 'description' => 'Environment'],
                ],
            ]);

        $command = $this->app->find('pipelines:settings');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Pipeline Settings: Deploy', $output);
        $this->assertStringContainsString('MANUAL', $output);
        $this->assertStringContainsString('refs/heads/main', $output);
        $this->assertStringContainsString('Variables:', $output);
        $this->assertStringContainsString('ENV', $output);
    }

    public function testPipelinesSettingsYamlOutput(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn([
                'id' => 1,
                'name' => 'Deploy',
                'refs' => ['refs/heads/main'],
                'variables' => [
                    ['key' => 'ENV', 'value' => 'prod', 'type' => 'VAR', 'settable' => true],
                ],
            ]);

        $command = $this->app->find('pipelines:settings');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--yaml' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('name: Deploy', $output);
        $this->assertStringContainsString('- refs/heads/main', $output);
        $this->assertStringNotContainsString('trigger_mode', $output);
        $this->assertStringNotContainsString('ref_name', $output);
        $this->assertStringContainsString('variables:', $output);
        $this->assertStringContainsString('key: ENV', $output);
    }

    public function testPipelinesSettingsUpdate(): void
    {
        $yaml = <<<'YAML'
name: "Updated"
refs:
  - refs/heads/main
YAML;
        $yamlFile = $this->writeTempFile('settings.yaml', $yaml);

        $this->mockBuddyService->expects($this->once())
            ->method('updatePipeline')
            ->with('ws', 'proj', 1, [
                'name' => 'Updated',
                'refs' => ['refs/heads/main'],
            ])
            ->willReturn(['id' => 1, 'name' => 'Updated']);

        $command = $this->app->find('pipelines:settings');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
            '--update' => $yamlFile,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Updated pipeline settings', $tester->getDisplay());
    }

    // pipelines:delete tests

    private function stubPipelineForDelete(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->with('ws', 'proj', 42)
            ->willReturn(['id' => 42, 'name' => 'Throwaway']);
    }

    public function testPipelinesDeleteConfirmedDeletes(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->expects($this->once())
            ->method('deletePipeline')
            ->with('ws', 'proj', 42);

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->setInputs(['yes']);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString("Delete pipeline 'Throwaway' (ID: 42)?", $tester->getDisplay());
        $this->assertStringContainsString('Deleted pipeline: Throwaway (ID: 42)', $tester->getDisplay());
    }

    public function testPipelinesDeleteDeclinedDoesNotDelete(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->expects($this->never())->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->setInputs(['n']);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Cancelled.', $tester->getDisplay());
    }

    public function testPipelinesDeleteOnlyAcceptsYes(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->expects($this->never())->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->setInputs(['yeah']);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42']);

        $this->assertStringContainsString('Cancelled.', $tester->getDisplay());
    }

    public function testPipelinesDeleteNonInteractiveWithoutForceRefuses(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->expects($this->never())->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->execute(
            ['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42'],
            ['interactive' => false]
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('--force', $tester->getDisplay());
    }

    public function testPipelinesDeleteForceSkipsPrompt(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->expects($this->once())
            ->method('deletePipeline')
            ->with('ws', 'proj', 42);

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->execute(
            ['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42', '--force' => true],
            ['interactive' => false]
        );

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringNotContainsString('Delete pipeline', $tester->getDisplay());
    }

    public function testPipelinesDeleteJsonOutput(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '42',
            '--force' => true,
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(
            ['deleted' => true, 'id' => 42, 'name' => 'Throwaway'],
            json_decode($tester->getDisplay(), true)
        );
    }

    public function testPipelinesDeleteJsonPromptStaysOffStdout(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->setInputs(['yes']);
        $tester->execute(
            ['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42', '--json' => true],
            ['capture_stderr_separately' => true]
        );

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertSame(
            ['deleted' => true, 'id' => 42, 'name' => 'Throwaway'],
            json_decode($tester->getDisplay(), true)
        );
        $this->assertStringContainsString('Delete pipeline', $tester->getErrorOutput());
    }

    public function testPipelinesDeleteRejectsNonNumericId(): void
    {
        $this->mockBuddyService->expects($this->never())->method('getPipeline');
        $this->mockBuddyService->expects($this->never())->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->execute(
            ['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => 'deploy', '--force' => true]
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('numeric', $tester->getDisplay());
    }

    public function testPipelinesDeleteNotFoundFails(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willThrowException(new BuddyResponseException(404, [], '{"errors":[{"message":"Pipeline not found"}]}'));
        $this->mockBuddyService->expects($this->never())->method('deletePipeline');

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->execute(
            ['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42', '--force' => true]
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Pipeline not found', $tester->getDisplay());
    }

    public function testPipelinesDeleteApiErrorFails(): void
    {
        $this->stubPipelineForDelete();
        $this->mockBuddyService->method('deletePipeline')
            ->willThrowException(new BuddyResponseException(403, [], '{"errors":[{"message":"Forbidden"}]}'));

        $tester = new CommandTester($this->app->find('pipelines:delete'));
        $tester->execute(
            ['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '42', '--force' => true]
        );

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Delete failed', $tester->getDisplay());
    }

    public function testPipelinesSettingsUpdateWarnsThatTriggerModeIsIgnored(): void
    {
        $yamlFile = $this->writeTempFile('settings.yaml', "name: Updated\ntrigger_mode: ON_EVERY_PUSH\n");

        $this->mockBuddyService->expects($this->once())
            ->method('updatePipeline')
            ->with('ws', 'proj', 1, ['name' => 'Updated'])
            ->willReturn(['id' => 1, 'name' => 'Updated']);

        $tester = new CommandTester($this->app->find('pipelines:settings'));
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', 'pipeline-id' => '1', '--update' => $yamlFile]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('trigger_mode is ignored', $tester->getDisplay());
    }
}
