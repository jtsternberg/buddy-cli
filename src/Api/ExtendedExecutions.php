<?php

declare(strict_types=1);

namespace BuddyCli\Api;

use Buddy\Apis\Executions;
use Buddy\BuddyResponse;

/**
 * Extended Executions API with action logs support.
 */
class ExtendedExecutions extends Executions
{
    public function getActionExecution(
        string $domain,
        string $projectName,
        int $pipelineId,
        int $executionId,
        int $actionId,
        ?string $accessToken = null
    ): BuddyResponse {
        return $this->getJson(
            $accessToken,
            '/workspaces/:domain/projects/:project_name/pipelines/:pipeline_id/executions/:execution_id/actions/:action_id',
            [
                'domain' => $domain,
                'project_name' => $projectName,
                'pipeline_id' => $pipelineId,
                'execution_id' => $executionId,
                'action_id' => $actionId,
            ]
        );
    }
}
