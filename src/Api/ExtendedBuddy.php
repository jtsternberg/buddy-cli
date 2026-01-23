<?php

declare(strict_types=1);

namespace BuddyCli\Api;

use Buddy\Buddy;
use Buddy\BuddyClient;

/**
 * Extended Buddy client with additional API methods.
 */
class ExtendedBuddy extends Buddy
{
    private ExtendedExecutions $extendedExecutions;
    private VariablesApi $variables;

    /**
     * @param mixed[] $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        // Access parent's private client via reflection
        $reflection = new \ReflectionClass(Buddy::class);
        $clientProperty = $reflection->getProperty('client');
        /** @var BuddyClient $client */
        $client = $clientProperty->getValue($this);

        $this->extendedExecutions = new ExtendedExecutions($client, $config);
        $this->variables = new VariablesApi($client, $config);
    }

    public function getApiExecutions(): ExtendedExecutions
    {
        return $this->extendedExecutions;
    }

    public function getApiVariables(): VariablesApi
    {
        return $this->variables;
    }
}
