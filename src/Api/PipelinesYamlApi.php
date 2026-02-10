<?php

declare(strict_types=1);

namespace BuddyCli\Api;

use Buddy\Apis\Api;
use Buddy\BuddyResponse;

/**
 * Pipelines YAML API for native Buddy pipeline configs.
 */
class PipelinesYamlApi extends Api
{
    public function getYaml(
        string $domain,
        string $projectName,
        int $pipelineId,
        ?string $accessToken = null
    ): BuddyResponse {
        return $this->getJson(
            $accessToken,
            '/workspaces/:domain/projects/:project_name/pipelines/:pipeline_id/yaml',
            [
                'domain' => $domain,
                'project_name' => $projectName,
                'pipeline_id' => $pipelineId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data Expected to include base64 "yaml" field.
     */
    public function updateYaml(
        array $data,
        string $domain,
        string $projectName,
        int $pipelineId,
        ?string $accessToken = null
    ): BuddyResponse {
        return $this->patchJson(
            $accessToken,
            $data,
            '/workspaces/:domain/projects/:project_name/pipelines/:pipeline_id/yaml',
            [
                'domain' => $domain,
                'project_name' => $projectName,
                'pipeline_id' => $pipelineId,
            ]
        );
    }
}
