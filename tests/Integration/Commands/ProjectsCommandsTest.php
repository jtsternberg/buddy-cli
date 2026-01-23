<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ProjectsCommandsTest extends TestCase
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

        // Set a fake token so BuddyService can be created
        $this->setEnv('BUDDY_TOKEN', 'fake-token-for-testing');

        $this->app = new Application();

        // Create and inject mock BuddyService
        $this->mockBuddyService = $this->createMock(BuddyService::class);
        $this->injectMockBuddyService();
    }

    private function injectMockBuddyService(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('buddyService');
        $property->setValue($this->app, $this->mockBuddyService);
    }

    // projects:list tests

    public function testProjectsListRequiresWorkspace(): void
    {
        $command = $this->app->find('projects:list');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute([]);
    }

    public function testProjectsListWithWorkspaceOption(): void
    {
        $this->mockBuddyService->method('getProjects')
            ->with('my-workspace')
            ->willReturn([
                'projects' => [
                    ['name' => 'project1', 'display_name' => 'Project One', 'status' => 'ACTIVE'],
                    ['name' => 'project2', 'display_name' => 'Project Two', 'status' => 'ACTIVE'],
                ],
            ]);

        $command = $this->app->find('projects:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'my-workspace']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('project1', $output);
        $this->assertStringContainsString('Project One', $output);
        $this->assertStringContainsString('project2', $output);
    }

    public function testProjectsListEmptyResult(): void
    {
        $this->mockBuddyService->method('getProjects')
            ->willReturn(['projects' => []]);

        $command = $this->app->find('projects:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'empty-workspace']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No projects found', $tester->getDisplay());
    }

    public function testProjectsListJsonOutput(): void
    {
        $projects = [
            ['name' => 'proj1', 'display_name' => 'Proj 1', 'status' => 'ACTIVE'],
        ];
        $this->mockBuddyService->method('getProjects')
            ->willReturn(['projects' => $projects]);

        $command = $this->app->find('projects:list');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'my-ws', '--json' => true]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertCount(1, $data);
        $this->assertSame('proj1', $data[0]['name']);
    }

    // projects:show tests

    public function testProjectsShowRequiresWorkspace(): void
    {
        $command = $this->app->find('projects:show');
        $tester = new CommandTester($command);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $tester->execute(['project' => 'my-project']);
    }

    public function testProjectsShowRequiresProject(): void
    {
        $command = $this->app->find('projects:show');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'my-ws']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No project specified', $tester->getDisplay());
    }

    public function testProjectsShowWithArguments(): void
    {
        $this->mockBuddyService->method('getProject')
            ->with('my-ws', 'my-project')
            ->willReturn([
                'name' => 'my-project',
                'display_name' => 'My Project',
                'status' => 'ACTIVE',
                'http_repository' => 'https://github.com/example/repo',
                'create_date' => '2024-01-15T10:00:00Z',
            ]);

        $command = $this->app->find('projects:show');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'my-ws', 'project' => 'my-project']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('my-project', $output);
        $this->assertStringContainsString('My Project', $output);
        $this->assertStringContainsString('ACTIVE', $output);
    }

    public function testProjectsShowJsonOutput(): void
    {
        $projectData = [
            'name' => 'test-proj',
            'display_name' => 'Test Project',
            'status' => 'ACTIVE',
        ];
        $this->mockBuddyService->method('getProject')
            ->willReturn($projectData);

        $command = $this->app->find('projects:show');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'my-ws',
            'project' => 'test-proj',
            '--json' => true,
        ]);

        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame('test-proj', $data['name']);
    }
}
