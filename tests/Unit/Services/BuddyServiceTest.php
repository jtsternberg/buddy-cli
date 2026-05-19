<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Services;

use Buddy\Apis\Pipelines;
use Buddy\Apis\Projects;
use Buddy\Apis\Workspaces;
use Buddy\BuddyResponse;
use Buddy\Exceptions\BuddyResponseException;
use BuddyCli\Api\ExtendedBuddy;
use BuddyCli\Api\ExtendedExecutions;
use BuddyCli\Api\PipelinesYamlApi;
use BuddyCli\Services\BuddyService;
use BuddyCli\Services\ConfigService;
use BuddyCli\Tests\TestCase;

class BuddyServiceTest extends TestCase
{
    private BuddyService $service;
    private ConfigService $config;
    private ExtendedBuddy $mockBuddy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_TOKEN');

        $this->config = new ConfigService();
        $this->service = new BuddyService('test-token', $this->config);

        // Create mock for the buddy client
        $this->mockBuddy = $this->createMock(ExtendedBuddy::class);
        $this->injectMockBuddy();
    }

    private function injectMockBuddy(): void
    {
        $reflection = new \ReflectionClass(BuddyService::class);
        $property = $reflection->getProperty('buddy');
        $property->setValue($this->service, $this->mockBuddy);
    }

    private function createMockResponse(array $body): BuddyResponse
    {
        $response = $this->createMock(BuddyResponse::class);
        $response->method('getBody')->willReturn($body);
        return $response;
    }

    public function testGetWorkspaces(): void
    {
        $expected = ['workspaces' => [['domain' => 'my-ws']]];

        $workspacesApi = $this->createMock(Workspaces::class);
        $workspacesApi->method('getWorkspaces')
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiWorkspaces')
            ->willReturn($workspacesApi);

        $result = $this->service->getWorkspaces();
        $this->assertSame($expected, $result);
    }

    public function testGetWorkspace(): void
    {
        $expected = ['domain' => 'my-ws', 'name' => 'My Workspace'];

        $workspacesApi = $this->createMock(Workspaces::class);
        $workspacesApi->method('getWorkspace')
            ->with('my-ws')
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiWorkspaces')
            ->willReturn($workspacesApi);

        $result = $this->service->getWorkspace('my-ws');
        $this->assertSame($expected, $result);
    }

    public function testGetProjects(): void
    {
        $expected = ['projects' => [['name' => 'project1']]];

        $projectsApi = $this->createMock(Projects::class);
        $projectsApi->method('getProjects')
            ->with('my-ws')
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiProjects')
            ->willReturn($projectsApi);

        $result = $this->service->getProjects('my-ws');
        $this->assertSame($expected, $result);
    }

    public function testGetProject(): void
    {
        $expected = ['name' => 'project1', 'display_name' => 'Project One'];

        $projectsApi = $this->createMock(Projects::class);
        $projectsApi->method('getProject')
            ->with('my-ws', 'project1')
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiProjects')
            ->willReturn($projectsApi);

        $result = $this->service->getProject('my-ws', 'project1');
        $this->assertSame($expected, $result);
    }

    public function testGetPipelines(): void
    {
        $expected = ['pipelines' => [['id' => 1, 'name' => 'Deploy']]];

        $pipelinesApi = $this->createMock(Pipelines::class);
        $pipelinesApi->method('getPipelines')
            ->with('my-ws', 'project1', [])
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiPipelines')
            ->willReturn($pipelinesApi);

        $result = $this->service->getPipelines('my-ws', 'project1');
        $this->assertSame($expected, $result);
    }

    public function testGetPipelinesPassesFiltersToSdk(): void
    {
        $filters = ['page' => 2, 'per_page' => 10, 'on_every_push' => true];
        $expected = ['pipelines' => [['id' => 5, 'name' => 'Test']]];

        $pipelinesApi = $this->createMock(Pipelines::class);
        $pipelinesApi->method('getPipelines')
            ->with('my-ws', 'project1', $filters)
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiPipelines')
            ->willReturn($pipelinesApi);

        $result = $this->service->getPipelines('my-ws', 'project1', $filters);
        $this->assertSame($expected, $result);
    }

    public function testGetAllPipelinesMergesMultiplePages(): void
    {
        $page1 = array_fill(0, 20, ['id' => 1]);
        $page2 = array_fill(0, 20, ['id' => 2]);
        $page3 = array_fill(0, 5, ['id' => 3]);

        $pipelinesApi = $this->createMock(Pipelines::class);
        $pipelinesApi->expects($this->exactly(3))
            ->method('getPipelines')
            ->willReturnOnConsecutiveCalls(
                $this->createMockResponse(['pipelines' => $page1]),
                $this->createMockResponse(['pipelines' => $page2]),
                $this->createMockResponse(['pipelines' => $page3])
            );

        $this->mockBuddy->method('getApiPipelines')
            ->willReturn($pipelinesApi);

        $result = $this->service->getAllPipelines('my-ws', 'project1');

        $this->assertCount(45, $result['pipelines']);
    }

    public function testGetPipeline(): void
    {
        $expected = ['id' => 1, 'name' => 'Deploy', 'status' => 'ACTIVE'];

        $pipelinesApi = $this->createMock(Pipelines::class);
        $pipelinesApi->method('getPipeline')
            ->with('my-ws', 'project1', 1)
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiPipelines')
            ->willReturn($pipelinesApi);

        $result = $this->service->getPipeline('my-ws', 'project1', 1);
        $this->assertSame($expected, $result);
    }

    public function testGetPipelineYamlDecodes(): void
    {
        $yaml = "pipeline: Deploy\n";
        $encoded = base64_encode($yaml);

        $pipelinesYamlApi = $this->createMock(PipelinesYamlApi::class);
        $pipelinesYamlApi->method('getYaml')
            ->with('my-ws', 'project1', 1)
            ->willReturn($this->createMockResponse(['yaml' => $encoded]));

        $this->mockBuddy->method('getApiPipelinesYaml')
            ->willReturn($pipelinesYamlApi);

        $result = $this->service->getPipelineYaml('my-ws', 'project1', 1);
        $this->assertSame($yaml, $result);
    }

    public function testGetPipelineYamlThrowsWhenYamlKeyMissing(): void
    {
        $pipelinesYamlApi = $this->createMock(PipelinesYamlApi::class);
        $pipelinesYamlApi->method('getYaml')
            ->with('my-ws', 'project1', 1)
            ->willReturn($this->createMockResponse([]));

        $this->mockBuddy->method('getApiPipelinesYaml')
            ->willReturn($pipelinesYamlApi);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Buddy API did not return YAML content.');
        $this->service->getPipelineYaml('my-ws', 'project1', 1);
    }

    public function testGetPipelineYamlThrowsOnInvalidBase64(): void
    {
        $pipelinesYamlApi = $this->createMock(PipelinesYamlApi::class);
        $pipelinesYamlApi->method('getYaml')
            ->with('my-ws', 'project1', 1)
            ->willReturn($this->createMockResponse(['yaml' => '!!!invalid!!!']));

        $this->mockBuddy->method('getApiPipelinesYaml')
            ->willReturn($pipelinesYamlApi);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode YAML content from Buddy API.');
        $this->service->getPipelineYaml('my-ws', 'project1', 1);
    }

    public function testUpdatePipelineYamlEncodes(): void
    {
        $yaml = "pipeline: Deploy\n";
        $encoded = base64_encode($yaml);

        $pipelinesYamlApi = $this->createMock(PipelinesYamlApi::class);
        $pipelinesYamlApi->expects($this->once())
            ->method('updateYaml')
            ->with(['yaml' => $encoded], 'my-ws', 'project1', 1)
            ->willReturn($this->createMockResponse([]));

        $this->mockBuddy->method('getApiPipelinesYaml')
            ->willReturn($pipelinesYamlApi);

        $this->service->updatePipelineYaml('my-ws', 'project1', 1, $yaml);
    }

    public function testGetExecutions(): void
    {
        $expected = ['executions' => [['id' => 100, 'status' => 'SUCCESSFUL']]];

        $executionsApi = $this->createMock(ExtendedExecutions::class);
        $executionsApi->method('getExecutions')
            ->with('my-ws', 'project1', 1, [])
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiExecutions')
            ->willReturn($executionsApi);

        $result = $this->service->getExecutions('my-ws', 'project1', 1);
        $this->assertSame($expected, $result);
    }

    public function testRunExecution(): void
    {
        $expected = ['id' => 101, 'status' => 'INPROGRESS'];

        $executionsApi = $this->createMock(ExtendedExecutions::class);
        $executionsApi->method('runExecution')
            ->with([], 'my-ws', 'project1', 1)
            ->willReturn($this->createMockResponse($expected));

        $this->mockBuddy->method('getApiExecutions')
            ->willReturn($executionsApi);

        $result = $this->service->runExecution('my-ws', 'project1', 1);
        $this->assertSame($expected, $result);
    }

    public function testAutoRefreshNotAttemptedWithoutCredentials(): void
    {
        // No refresh_token, client_id, or client_secret configured
        $exception = new BuddyResponseException(401, [], '{"error":"Unauthorized"}');

        $workspacesApi = $this->createMock(Workspaces::class);
        $workspacesApi->method('getWorkspaces')
            ->willThrowException($exception);

        $this->mockBuddy->method('getApiWorkspaces')
            ->willReturn($workspacesApi);

        $this->expectException(BuddyResponseException::class);
        $this->service->getWorkspaces();
    }

    public function testNon401ExceptionIsRethrown(): void
    {
        $exception = new BuddyResponseException(404, [], '{"error":"Not Found"}');

        $workspacesApi = $this->createMock(Workspaces::class);
        $workspacesApi->method('getWorkspaces')
            ->willThrowException($exception);

        $this->mockBuddy->method('getApiWorkspaces')
            ->willReturn($workspacesApi);

        $this->expectException(BuddyResponseException::class);
        $this->service->getWorkspaces();
    }
}
