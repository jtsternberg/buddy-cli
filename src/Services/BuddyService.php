<?php

declare(strict_types=1);

namespace BuddyCli\Services;

use Buddy\Buddy;
use Buddy\BuddyResponse;

class BuddyService
{
    private Buddy $buddy;

    public function __construct(string $accessToken)
    {
        $this->buddy = new Buddy([
            'accessToken' => $accessToken,
        ]);
    }

    // Workspace methods

    public function getWorkspaces(): array
    {
        return $this->buddy->getApiWorkspaces()->getWorkspaces()->getBody();
    }

    public function getWorkspace(string $domain): array
    {
        return $this->buddy->getApiWorkspaces()->getWorkspace($domain)->getBody();
    }

    // Project methods

    public function getProjects(string $workspace): array
    {
        return $this->buddy->getApiProjects()->getProjects($workspace)->getBody();
    }

    public function getProject(string $workspace, string $projectName): array
    {
        return $this->buddy->getApiProjects()->getProject($workspace, $projectName)->getBody();
    }

    // Pipeline methods

    public function getPipelines(string $workspace, string $projectName): array
    {
        return $this->buddy->getApiPipelines()->getPipelines($workspace, $projectName)->getBody();
    }

    public function getPipeline(string $workspace, string $projectName, int $pipelineId): array
    {
        return $this->buddy->getApiPipelines()->getPipeline($workspace, $projectName, $pipelineId)->getBody();
    }

    public function getPipelineActions(string $workspace, string $projectName, int $pipelineId): array
    {
        return $this->buddy->getApiPipelines()->getPipelineActions($workspace, $projectName, $pipelineId)->getBody();
    }

    // Execution methods

    public function getExecutions(string $workspace, string $projectName, int $pipelineId, array $filters = []): array
    {
        return $this->buddy->getApiExecutions()->getExecutions($workspace, $projectName, $pipelineId, $filters)->getBody();
    }

    public function getExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->buddy->getApiExecutions()->getExecution($workspace, $projectName, $pipelineId, $executionId)->getBody();
    }

    public function runExecution(string $workspace, string $projectName, int $pipelineId, array $data = []): array
    {
        return $this->buddy->getApiExecutions()->runExecution($data, $workspace, $projectName, $pipelineId)->getBody();
    }

    public function retryExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->buddy->getApiExecutions()->retryRelease($workspace, $projectName, $pipelineId, $executionId)->getBody();
    }

    public function cancelExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->buddy->getApiExecutions()->cancelExecution($workspace, $projectName, $pipelineId, $executionId)->getBody();
    }
}
