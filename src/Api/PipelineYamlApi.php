<?php

declare(strict_types=1);

namespace BuddyCli\Api;

use Buddy\Apis\Api;
use Buddy\BuddyResponse;

/**
 * Pipeline YAML API for exporting/importing pipeline configurations.
 */
class PipelineYamlApi extends Api
{
    public function exportYaml(string $domain, string $projectName, int $pipelineId, ?string $accessToken = null): BuddyResponse
    {
        return $this->getJson($accessToken, '/workspaces/:domain/projects/:project_name/pipelines/:pipeline_id/yaml', [
            'domain' => $domain,
            'project_name' => $projectName,
            'pipeline_id' => $pipelineId,
        ]);
    }

    /**
     * @param array<string, mixed> $data Must contain 'yaml' key with base64-encoded YAML content
     */
    public function importYaml(array $data, string $domain, string $projectName, int $pipelineId, ?string $accessToken = null): BuddyResponse
    {
        return $this->patchJson($accessToken, $data, '/workspaces/:domain/projects/:project_name/pipelines/:pipeline_id/yaml', [
            'domain' => $domain,
            'project_name' => $projectName,
            'pipeline_id' => $pipelineId,
        ]);
    }
}
