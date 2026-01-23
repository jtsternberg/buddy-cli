<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ActionsCommandsTest extends TestCase
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

    // actions:list tests

    public function testActionsListRequiresWorkspace(): void
    {
        $command = $this->app->find('actions:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['--project' => 'proj', '--pipeline' => '1']);
    }

    public function testActionsListRequiresProject(): void
    {
        $command = $this->app->find('actions:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $tester->execute(['--workspace' => 'ws', '--pipeline' => '1']);
    }

    public function testActionsListRequiresPipeline(): void
    {
        $command = $this->app->find('actions:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline ID is required');
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj']);
    }

    public function testActionsListWithActions(): void
    {
        $this->mockBuddyService->method('getPipelineActions')
            ->with('ws', 'proj', 1)
            ->willReturn([
                'actions' => [
                    [
                        'id' => 1,
                        'name' => 'Build',
                        'type' => 'BUILD',
                        'trigger_time' => 'ON_EVERY_EXECUTION',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Deploy',
                        'type' => 'SFTP',
                        'trigger_time' => 'ON_EVERY_EXECUTION',
                    ],
                ],
            ]);

        $command = $this->app->find('actions:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Build', $output);
        $this->assertStringContainsString('Deploy', $output);
        $this->assertStringContainsString('BUILD', $output);
        $this->assertStringContainsString('SFTP', $output);
    }

    public function testActionsListEmpty(): void
    {
        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn(['actions' => []]);

        $command = $this->app->find('actions:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No actions found', $tester->getDisplay());
    }

    public function testActionsListJsonOutput(): void
    {
        $actions = [
            ['id' => 1, 'name' => 'Build', 'type' => 'BUILD'],
        ];
        $this->mockBuddyService->method('getPipelineActions')
            ->willReturn(['actions' => $actions]);

        $command = $this->app->find('actions:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', '--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertCount(1, $data);
        $this->assertSame('Build', $data[0]['name']);
    }

    // actions:show tests

    public function testActionsShowDisplaysDetails(): void
    {
        $this->mockBuddyService->method('getPipelineAction')
            ->with('ws', 'proj', 1, 10)
            ->willReturn([
                'id' => 10,
                'name' => 'Build App',
                'type' => 'BUILD',
                'trigger_time' => 'ON_EVERY_EXECUTION',
                'docker_image_name' => 'php',
                'docker_image_tag' => '8.2',
                'execute_commands' => ['composer install', 'phpunit'],
            ]);

        $command = $this->app->find('actions:show');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Build App', $output);
        $this->assertStringContainsString('BUILD', $output);
        $this->assertStringContainsString('php:8.2', $output);
        $this->assertStringContainsString('composer install', $output);
    }

    public function testActionsShowYamlOutput(): void
    {
        $this->mockBuddyService->method('getPipelineAction')
            ->willReturn([
                'id' => 10,
                'name' => 'Build App',
                'type' => 'BUILD',
                'trigger_time' => 'ON_EVERY_EXECUTION',
                'docker_image_name' => 'php',
            ]);

        $command = $this->app->find('actions:show');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10', '--yaml' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Build App', $output);
        $this->assertStringContainsString('type: BUILD', $output);
    }

    public function testActionsShowJsonOutput(): void
    {
        $action = ['id' => 10, 'name' => 'Build App', 'type' => 'BUILD'];
        $this->mockBuddyService->method('getPipelineAction')
            ->willReturn($action);

        $command = $this->app->find('actions:show');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10', '--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame('Build App', $data['name']);
    }

    // actions:create tests

    public function testActionsCreateFromYaml(): void
    {
        $yamlContent = <<<YAML
name: New Action
type: BUILD
docker_image_name: php
docker_image_tag: '8.2'
execute_commands:
  - composer install
YAML;
        $yamlFile = $this->writeTempFile('action.yaml', $yamlContent);

        $this->mockBuddyService->method('createPipelineAction')
            ->with('ws', 'proj', 1, $this->callback(function ($data) {
                return $data['name'] === 'New Action' && $data['type'] === 'BUILD';
            }))
            ->willReturn(['id' => 20, 'name' => 'New Action']);

        $command = $this->app->find('actions:create');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'file' => $yamlFile]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Created action: New Action', $tester->getDisplay());
    }

    public function testActionsCreateRequiresName(): void
    {
        $yamlContent = "type: BUILD\n";
        $yamlFile = $this->writeTempFile('action.yaml', $yamlContent);

        $command = $this->app->find('actions:create');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'file' => $yamlFile]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Action name is required', $tester->getDisplay());
    }

    public function testActionsCreateRequiresType(): void
    {
        $yamlContent = "name: Test Action\n";
        $yamlFile = $this->writeTempFile('action.yaml', $yamlContent);

        $command = $this->app->find('actions:create');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'file' => $yamlFile]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Action type is required', $tester->getDisplay());
    }

    public function testActionsCreateFileNotFound(): void
    {
        $command = $this->app->find('actions:create');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'file' => '/nonexistent.yaml']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File not found', $tester->getDisplay());
    }

    // actions:update tests

    public function testActionsUpdateFromYaml(): void
    {
        $yamlContent = <<<YAML
name: Updated Action
execute_commands:
  - npm install
  - npm test
YAML;
        $yamlFile = $this->writeTempFile('action.yaml', $yamlContent);

        $this->mockBuddyService->method('updatePipelineAction')
            ->with('ws', 'proj', 1, 10, $this->callback(function ($data) {
                return $data['name'] === 'Updated Action';
            }))
            ->willReturn(['id' => 10, 'name' => 'Updated Action']);

        $command = $this->app->find('actions:update');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10', 'file' => $yamlFile]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Updated action: Updated Action', $tester->getDisplay());
    }

    public function testActionsUpdateFileNotFound(): void
    {
        $command = $this->app->find('actions:update');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10', 'file' => '/nonexistent.yaml']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('File not found', $tester->getDisplay());
    }

    // actions:delete tests

    public function testActionsDeleteWithForce(): void
    {
        $this->mockBuddyService->method('getPipelineAction')
            ->with('ws', 'proj', 1, 10)
            ->willReturn(['id' => 10, 'name' => 'Build Action']);

        $this->mockBuddyService->method('deletePipelineAction')
            ->with('ws', 'proj', 1, 10)
            ->willReturn([]);

        $command = $this->app->find('actions:delete');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10', '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Deleted action: Build Action', $tester->getDisplay());
    }

    public function testActionsDeleteJsonOutput(): void
    {
        $this->mockBuddyService->method('getPipelineAction')
            ->willReturn(['id' => 10, 'name' => 'Build Action']);

        $this->mockBuddyService->method('deletePipelineAction')
            ->willReturn([]);

        $command = $this->app->find('actions:delete');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '10', '--force' => true, '--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertTrue($data['deleted']);
        $this->assertSame(10, $data['id']);
    }

    public function testActionsDeleteActionNotFound(): void
    {
        $this->mockBuddyService->method('getPipelineAction')
            ->willThrowException(new \Exception('Action not found'));

        $command = $this->app->find('actions:delete');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', '--project' => 'proj', '--pipeline' => '1', 'action-id' => '999', '--force' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Action not found', $tester->getDisplay());
    }
}
