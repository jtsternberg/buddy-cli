<?php

declare(strict_types=1);

namespace BuddyCli\Api;

use BuddyCli\Sdk\Buddy;

/**
 * Extended Buddy client with additional API methods.
 */
class ExtendedBuddy extends Buddy
{
    private ExtendedExecutions $extendedExecutions;
    private VariablesApi $variables;
    private PipelinesYamlApi $pipelinesYaml;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->extendedExecutions = new ExtendedExecutions($this->client, $config);
        $this->variables = new VariablesApi($this->client, $config);
        $this->pipelinesYaml = new PipelinesYamlApi($this->client, $config);
    }

    public function getApiExecutions(): ExtendedExecutions
    {
        return $this->extendedExecutions;
    }

    public function getApiVariables(): VariablesApi
    {
        return $this->variables;
    }

    public function getApiPipelinesYaml(): PipelinesYamlApi
    {
        return $this->pipelinesYaml;
    }
}
