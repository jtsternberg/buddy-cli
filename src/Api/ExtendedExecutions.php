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
    /**
     * Get action details for a specific execution using the static action definition ID.
     *
     * Uses the /actions/:action_id endpoint where $actionId is the integer ID of the
     * action definition — the same number every time that action runs. Does NOT return
     * log output for SSH_COMMAND type actions; use getActionExecutionByExecId() for those.
     */
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

    /**
     * Get action details for a specific execution using the per-run action_execution_id.
     *
     * Uses the /action_executions/:action_execution_id endpoint where $actionExecutionId
     * is the hex string unique to each individual run of an action (visible in Buddy URLs
     * as the actionExecutionId query param). Returns log output for all action types
     * including SSH_COMMAND, which getActionExecution() does not support.
     */
    public function getActionExecutionByExecId(
        string $domain,
        string $projectName,
        int $pipelineId,
        int $executionId,
        string $actionExecutionId,
        ?string $accessToken = null
    ): BuddyResponse {
        return $this->getJson(
            $accessToken,
            '/workspaces/:domain/projects/:project_name/pipelines/:pipeline_id/executions/:execution_id/action_executions/:action_execution_id',
            [
                'domain' => $domain,
                'project_name' => $projectName,
                'pipeline_id' => $pipelineId,
                'execution_id' => $executionId,
                'action_execution_id' => $actionExecutionId,
            ]
        );
    }
}
