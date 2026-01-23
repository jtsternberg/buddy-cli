<?php

declare(strict_types=1);

namespace BuddyCli\Api;

use Buddy\Apis\Api;
use Buddy\BuddyResponse;

/**
 * Variables API for managing environment variables in Buddy.
 */
class VariablesApi extends Api
{
    public const TYPE_VAR = 'VAR';
    public const TYPE_SSH_KEY = 'SSH_KEY';
    public const TYPE_SSH_PUBLIC_KEY = 'SSH_PUBLIC_KEY';

    /**
     * @param array<string, mixed> $filters Optional filters: projectName, pipelineId, actionId
     */
    public function getVariables(string $domain, array $filters = [], ?string $accessToken = null): BuddyResponse
    {
        return $this->getJson($accessToken, '/workspaces/:domain/variables', [
            'domain' => $domain,
        ], $filters);
    }

    public function getVariable(string $domain, int $variableId, ?string $accessToken = null): BuddyResponse
    {
        return $this->getJson($accessToken, '/workspaces/:domain/variables/:variable_id', [
            'domain' => $domain,
            'variable_id' => $variableId,
        ]);
    }

    /**
     * @param array<string, mixed> $data Variable data: key, value, type, description, settable, encrypted, project, pipeline, action
     */
    public function addVariable(array $data, string $domain, ?string $accessToken = null): BuddyResponse
    {
        return $this->postJson($accessToken, $data, '/workspaces/:domain/variables', [
            'domain' => $domain,
        ]);
    }

    /**
     * @param array<string, mixed> $data Variable data to update
     */
    public function editVariable(array $data, string $domain, int $variableId, ?string $accessToken = null): BuddyResponse
    {
        return $this->patchJson($accessToken, $data, '/workspaces/:domain/variables/:variable_id', [
            'domain' => $domain,
            'variable_id' => $variableId,
        ]);
    }

    public function deleteVariable(string $domain, int $variableId, ?string $accessToken = null): BuddyResponse
    {
        return $this->deleteJson($accessToken, null, '/workspaces/:domain/variables/:variable_id', [
            'domain' => $domain,
            'variable_id' => $variableId,
        ]);
    }
}
