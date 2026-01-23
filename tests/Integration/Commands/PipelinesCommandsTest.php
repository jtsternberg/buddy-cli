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
}
