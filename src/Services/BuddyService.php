<?php

declare(strict_types=1);

namespace BuddyCli\Services;

use Buddy\Exceptions\BuddyResponseException;
use BuddyCli\Api\ExtendedBuddy;

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
            fn () => $this->buddy->getApiWorkspaces()->getWorkspaces()->getBody()
        );
    }

    public function getWorkspace(string $domain): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWorkspaces()->getWorkspace($domain)->getBody()
        );
    }

    // Project methods

    public function getProjects(string $workspace): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiProjects()->getProjects($workspace)->getBody()
        );
    }

    public function getProject(string $workspace, string $projectName): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiProjects()->getProject($workspace, $projectName)->getBody()
        );
    }

    // Pipeline methods

    public function getPipelines(string $workspace, string $projectName): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipelines($workspace, $projectName)->getBody()
        );
    }

    public function getPipeline(string $workspace, string $projectName, int $pipelineId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipeline($workspace, $projectName, $pipelineId)->getBody()
        );
    }

    public function getPipelineActions(string $workspace, string $projectName, int $pipelineId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipelineActions($workspace, $projectName, $pipelineId)->getBody()
        );
    }

    public function createPipeline(string $workspace, string $projectName, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->addPipeline($data, $workspace, $projectName)->getBody()
        );
    }

    public function updatePipeline(string $workspace, string $projectName, int $pipelineId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->editPipeline($data, $workspace, $projectName, $pipelineId)->getBody()
        );
    }

    public function createPipelineAction(string $workspace, string $projectName, int $pipelineId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->addPipelineAction($data, $workspace, $projectName, $pipelineId)->getBody()
        );
    }

    public function getPipelineAction(string $workspace, string $projectName, int $pipelineId, int $actionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->getPipelineAction($workspace, $projectName, $pipelineId, $actionId)->getBody()
        );
    }

    public function updatePipelineAction(string $workspace, string $projectName, int $pipelineId, int $actionId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->editPipelineAction($data, $workspace, $projectName, $pipelineId, $actionId)->getBody()
        );
    }

    public function deletePipelineAction(string $workspace, string $projectName, int $pipelineId, int $actionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiPipelines()->deletePipelineAction($workspace, $projectName, $pipelineId, $actionId)->getBody()
        );
    }

    // Execution methods

    public function getExecutions(string $workspace, string $projectName, int $pipelineId, array $filters = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getExecutions($workspace, $projectName, $pipelineId, $filters)->getBody()
        );
    }

    public function getExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getExecution($workspace, $projectName, $pipelineId, $executionId)->getBody()
        );
    }

    public function runExecution(string $workspace, string $projectName, int $pipelineId, array $data = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->runExecution($data, $workspace, $projectName, $pipelineId)->getBody()
        );
    }

    public function retryExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->retryRelease($workspace, $projectName, $pipelineId, $executionId)->getBody()
        );
    }

    public function cancelExecution(string $workspace, string $projectName, int $pipelineId, int $executionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->cancelExecution($workspace, $projectName, $pipelineId, $executionId)->getBody()
        );
    }

    public function getActionExecution(string $workspace, string $projectName, int $pipelineId, int $executionId, int $actionId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiExecutions()->getActionExecution($workspace, $projectName, $pipelineId, $executionId, $actionId)->getBody()
        );
    }

    // Variable methods

    /**
     * @param array<string, mixed> $filters Optional filters: projectName, pipelineId, actionId
     */
    public function getVariables(string $workspace, array $filters = []): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->getVariables($workspace, $filters)->getBody()
        );
    }

    public function getVariable(string $workspace, int $variableId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->getVariable($workspace, $variableId)->getBody()
        );
    }

    /**
     * @param array<string, mixed> $data Variable data
     */
    public function createVariable(string $workspace, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->addVariable($data, $workspace)->getBody()
        );
    }

    /**
     * @param array<string, mixed> $data Variable data to update
     */
    public function updateVariable(string $workspace, int $variableId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->editVariable($data, $workspace, $variableId)->getBody()
        );
    }

    public function deleteVariable(string $workspace, int $variableId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiVariables()->deleteVariable($workspace, $variableId)->getBody()
        );
    }

    // Webhook methods

    public function getWebhooks(string $workspace): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->getWebhooks($workspace)->getBody()
        );
    }

    public function getWebhook(string $workspace, int $webhookId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->getWebhook($workspace, $webhookId)->getBody()
        );
    }

    /**
     * @param array<string, mixed> $data Webhook data: target_url, events, project_filter, secret_key
     */
    public function createWebhook(string $workspace, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->addWebhook($data, $workspace)->getBody()
        );
    }

    /**
     * @param array<string, mixed> $data Webhook data to update
     */
    public function updateWebhook(string $workspace, int $webhookId, array $data): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->editWebhook($data, $workspace, $webhookId)->getBody()
        );
    }

    public function deleteWebhook(string $workspace, int $webhookId): array
    {
        return $this->withAutoRefresh(
            fn () => $this->buddy->getApiWebhooks()->deleteWebhook($workspace, $webhookId)->getBody()
        );
    }

    /**
     * Execute an API call with automatic token refresh on 401.
     */
    private function withAutoRefresh(callable $apiCall): array
    {
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
