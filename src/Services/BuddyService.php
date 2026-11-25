<?php

declare(strict_types=1);

namespace BuddyCli\Services;

use BuddyCli\Api\ExtendedBuddy;
use BuddyCli\Exceptions\MissingScopeException;
use BuddyCli\Sdk\Exceptions\BuddyResponseException;

class BuddyService
{
    private ExtendedBuddy $buddy;
    private ConfigService $config;
    private bool $hasAttemptedRefresh = false;

    public function __construct(string $accessToken, ConfigService $config)
    {
        $this->config = $config;
        $this->buddy = new ExtendedBuddy([
            'accessToken' => $accessToken,
        ]);
    }

    // Workspace methods

    public function getWorkspaces(): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWorkspaces()->getWorkspaces()->getBody(),
            'WORKSPACE_READ'
        );
    }

    public function getWorkspace(string $domain): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWorkspaces()->getWorkspace($domain)->getBody(),
            'WORKSPACE_READ'
        );
    }

    // Project methods

    public function getProjects(string $workspace): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiProjects()->getProjects($workspace)->getBody(),
            'PROJECT_READ'
        );
    }

    public function getProject(string $workspace, string $projectName): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiProjects()->getProject($workspace, $projectName)->getBody(),
            'PROJECT_READ'
        );
    }

    // Pipeline methods

    public function getPipelines(string $workspace, string $projectName, array $filters = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipelines($workspace, $projectName, $filters)->getBody(),
            'PIPELINE_READ'
        );
    }

    public function getAllPipelines(string $workspace, string $projectName): array
    {
        $all     = [];
        $page    = 1;
        $perPage = 20;

        do {
            $response = $this->getPipelines($workspace, $projectName, ['page' => $page, 'per_page' => $perPage]);
            $batch    = $response['pipelines'] ?? [];
            $all      = array_merge($all, $batch);
            $page++;
        } while (count($batch) === $perPage);

        return array_merge($response, ['pipelines' => $all]);
    }

    public function getPipeline(string $workspace, string $projectName, int $pipelineId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipeline($workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_READ'
        );
    }

    public function deletePipeline(string $workspace, string $projectName, int $pipelineId): void
    {
        $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->deletePipeline($workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_MANAGE'
        );
    }

    public function getPipelineActions(string $workspace, string $projectName, int $pipelineId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipelineActions($workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_READ'
        );
    }

    public function getPipelineYaml(string $workspace, string $projectName, int $pipelineId): string
    {
        $response = $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelinesYaml()->getYaml($workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_READ'
        );

        $encoded = $response['yaml'] ?? null;
        if (!$encoded) {
            throw new \RuntimeException('Buddy API did not return YAML content.');
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new \RuntimeException('Failed to decode YAML content from Buddy API.');
        }

        return $decoded;
    }

    public function updatePipelineYaml(string $workspace, string $projectName, int $pipelineId, string $yaml): void
    {
        $payload = ['yaml' => base64_encode($yaml)];

        $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelinesYaml()->updateYaml($payload, $workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_WRITE'
        );
    }

    public function createPipeline(string $workspace, string $projectName, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->addPipeline($data, $workspace, $projectName)->getBody(),
            'PIPELINE_WRITE'
        );
    }

    public function updatePipeline(string $workspace, string $projectName, int $pipelineId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->editPipeline($data, $workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_WRITE'
        );
    }

    public function createPipelineAction(string $workspace, string $projectName, int $pipelineId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->addPipelineAction($data, $workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_WRITE'
        );
    }

    public function getPipelineAction(string $workspace, string $projectName, int $pipelineId, int $actionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipelineAction($workspace, $projectName, $pipelineId, $actionId)->getBody(),
            'PIPELINE_READ'
        );
    }

    public function updatePipelineAction(string $workspace, string $projectName, int $pipelineId, int $actionId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->editPipelineAction($data, $workspace, $projectName, $pipelineId, $actionId)->getBody(),
            'PIPELINE_WRITE'
        );
    }

    public function deletePipelineAction(string $workspace, string $projectName, int $pipelineId, int $actionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->deletePipelineAction($workspace, $projectName, $pipelineId, $actionId)->getBody(),
            'PIPELINE_MANAGE'
        );
    }

    // Execution methods

    public function getExecutions(string $workspace, string $projectName, int $pipelineId, array $filters = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getExecutions($workspace, $projectName, $pipelineId, $filters)->getBody(),
            'PIPELINE_READ'
        );
    }

    public function getExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getExecution($workspace, $projectName, $pipelineId, $executionId)->getBody(),
            'PIPELINE_READ'
        );
    }

    public function runExecution(string $workspace, string $projectName, int $pipelineId, array $data = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->runExecution($data, $workspace, $projectName, $pipelineId)->getBody(),
            'PIPELINE_RUN'
        );
    }

    public function retryExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->retryRelease($workspace, $projectName, $pipelineId, $executionId)->getBody(),
            'PIPELINE_RUN'
        );
    }

    public function cancelExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->cancelExecution($workspace, $projectName, $pipelineId, $executionId)->getBody(),
            'PIPELINE_RUN'
        );
    }

    /**
     * Get action details using the static action definition ID (integer).
     *
     * The $actionId is the same number every time that action runs. Does NOT return
     * log output for SSH_COMMAND type actions; use getActionExecutionByExecId() for those.
     */
    public function getActionExecution(string $workspace, string $projectName, int $pipelineId, int $executionId, int $actionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getActionExecution($workspace, $projectName, $pipelineId, $executionId, $actionId)->getBody(),
            'PIPELINE_READ'
        );
    }

    /**
     * Get action details using the per-run action_execution_id (hex string).
     *
     * The $actionExecutionId is unique to each individual run of an action — it's the
     * hex value visible in Buddy URLs as the actionExecutionId query param. Returns log
     * output for all action types including SSH_COMMAND.
     */
    public function getActionExecutionByExecId(string $workspace, string $projectName, int $pipelineId, int $executionId, string $actionExecutionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getActionExecutionByExecId($workspace, $projectName, $pipelineId, $executionId, $actionExecutionId)->getBody(),
            'PIPELINE_READ'
        );
    }

    // Variable methods

    /**
     * @param array<string, mixed> $filters Optional filters: projectName, pipelineId, actionId
     */
    public function getVariables(string $workspace, array $filters = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->getVariables($workspace, $filters)->getBody(),
            'VARIABLE_READ'
        );
    }

    public function getVariable(string $workspace, int $variableId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->getVariable($workspace, $variableId)->getBody(),
            'VARIABLE_READ'
        );
    }

    /**
     * @param array<string, mixed> $data Variable data
     */
    public function createVariable(string $workspace, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->addVariable($data, $workspace)->getBody(),
            'VARIABLE_WRITE'
        );
    }

    /**
     * @param array<string, mixed> $data Variable data to update
     */
    public function updateVariable(string $workspace, int $variableId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->editVariable($data, $workspace, $variableId)->getBody(),
            'VARIABLE_WRITE'
        );
    }

    public function deleteVariable(string $workspace, int $variableId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->deleteVariable($workspace, $variableId)->getBody(),
            'VARIABLE_MANAGE'
        );
    }

    // Webhook methods

    public function getWebhooks(string $workspace): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->getWebhooks($workspace)->getBody(),
            'WEBHOOK_READ'
        );
    }

    public function getWebhook(string $workspace, int $webhookId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->getWebhook($workspace, $webhookId)->getBody(),
            'WEBHOOK_READ'
        );
    }

    /**
     * @param array<string, mixed> $data Webhook data: target_url, events, project_filter, secret_key
     */
    public function createWebhook(string $workspace, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->addWebhook($data, $workspace)->getBody(),
            'WEBHOOK_WRITE'
        );
    }

    /**
     * @param array<string, mixed> $data Webhook data to update
     */
    public function updateWebhook(string $workspace, int $webhookId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->editWebhook($data, $workspace, $webhookId)->getBody(),
            'WEBHOOK_WRITE'
        );
    }

    public function deleteWebhook(string $workspace, int $webhookId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->deleteWebhook($workspace, $webhookId)->getBody(),
            'WEBHOOK_MANAGE'
        );
    }

    /**
     * Execute an API call with automatic token refresh on 401. A 403 names
     * the scope the call needs; the API is the only judge of whether the token
     * has it, since a recorded granted_scopes can outlive its token.
     */
    private function withAutoRefresh(callable $apiCall, string $scope): array
    {
        try {
            try {
                return $apiCall();
            } catch (BuddyResponseException $e) {
                if ($e->getStatusCode() === 401 && !$this->hasAttemptedRefresh && $this->canRefresh()) {
                    $this->hasAttemptedRefresh = true;

                    if ($this->refreshAccessToken()) {
                        return $apiCall();
                    }
                }

                throw $e;
            }
        } catch (BuddyResponseException $e) {
            if ($e->getStatusCode() === 403) {
                throw new MissingScopeException(sprintf(
                    'Buddy returned 403 Forbidden (%s). This call requires the %s scope (or a scope containing it); '
                    . 'check the token\'s scopes and your project permissions. To add the scope, run: buddy login --scopes=<scopes including %s>',
                    $e->getMessage(),
                    $scope,
                    $scope
                ), $scope, $e);
            }

            throw $e;
        }
    }

    /**
     * Check if we have the credentials needed to refresh the token.
     */
    private function canRefresh(): bool
    {
        return $this->config->get('refresh_token') !== null
            && $this->config->get('client_id') !== null
            && $this->config->get('client_secret') !== null;
    }

    /**
     * Attempt to refresh the access token.
     */
    private function refreshAccessToken(): bool
    {
        try {
            $oauth = new OAuthService(
                $this->config->get('client_id'),
                $this->config->get('client_secret')
            );

            $tokenData = $oauth->refreshToken($this->config->get('refresh_token'));

            // Save new tokens
            $this->config->set('token', $tokenData['access_token']);
            if (isset($tokenData['refresh_token'])) {
                $this->config->set('refresh_token', $tokenData['refresh_token']);
            }

            // Reinitialize Buddy client with new token
            $this->buddy = new ExtendedBuddy([
                'accessToken' => $tokenData['access_token'],
            ]);

            return true;
        } catch (\Exception $e) {
            // Refresh failed - user will need to re-authenticate
            return false;
        }
    }
}
