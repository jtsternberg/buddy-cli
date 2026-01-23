<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use Buddy\Exceptions\BuddyResponseException;
use BuddyCli\Application;
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
                        'trigger_mode' => 'MANUAL',
                        'last_execution_date' => '2024-01-15T10:00:00Z',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Test',
                        'last_execution_status' => 'FAILED',
                        'trigger_mode' => 'ON_EVERY_PUSH',
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
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'ref_name' => 'refs/heads/main']);

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
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'ref_name' => null]); // Wildcard pipeline

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

    public function testPipelinesRunWithBranch(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'ref_name' => null]); // Wildcard

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
            ->willReturn(['id' => 1, 'ref_name' => 'refs/heads/main']);

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

    public function testPipelinesRunHandlesApiError(): void
    {
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'ref_name' => 'refs/heads/main']);

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
            ->with('ws', 'proj', 1, ['per_page' => 1])
            ->willReturn(['executions' => []]);

        $command = $this->app->find('pipelines:retry');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'proj',
            'pipeline-id' => '1',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No executions found for this pipeline', $tester->getDisplay());
    }

    public function testPipelinesRetrySuccess(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['per_page' => 1])
            ->willReturn(['executions' => [['id' => 50]]]);

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
            ->with('ws', 'proj', 1, ['per_page' => 1])
            ->willReturn(['executions' => [['id' => 50]]]);

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
            ->with('ws', 'proj', 1, ['status' => 'INPROGRESS', 'per_page' => 1])
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

    public function testPipelinesCancelSuccess(): void
    {
        $this->mockBuddyService->method('getExecutions')
            ->with('ws', 'proj', 1, ['status' => 'INPROGRESS', 'per_page' => 1])
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
            ->with('ws', 'proj', 1, ['status' => 'INPROGRESS', 'per_page' => 1])
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
        $this->mockBuddyService->method('getPipeline')
            ->with('ws', 'proj', 1)
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'trigger_mode' => 'MANUAL', 'ref_name' => 'refs/heads/main']);

        $this->mockBuddyService->method('getPipelineActions')
            ->with('ws', 'proj', 1)
            ->willReturn(['actions' => [['name' => 'Build', 'type' => 'BUILD', 'docker_image_name' => 'php', 'execute_commands' => ['composer install']]]]);

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
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'trigger_mode' => 'MANUAL', 'ref_name' => 'refs/heads/main']);

        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn(['actions' => []]);

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
        $this->mockBuddyService->method('getPipeline')
            ->willReturn(['id' => 1, 'name' => 'Deploy', 'trigger_mode' => 'MANUAL', 'ref_name' => 'refs/heads/main']);

        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn(['actions' => [['name' => 'Build', 'type' => 'BUILD', 'docker_image_name' => 'php', 'execute_commands' => ['composer install']]]]);

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

        $this->assertStringContainsString('name: Deploy', $content);
        $this->assertStringContainsString('trigger_mode: MANUAL', $content);
        $this->assertStringContainsString('ref_name: refs/heads/main', $content);
        $this->assertStringContainsString('name: Build', $content);
        $this->assertStringContainsString('type: BUILD', $content);
        $this->assertStringContainsString('docker_image_name: php', $content);
        $this->assertStringContainsString('composer install', $content);
    }
}
