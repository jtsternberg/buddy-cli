<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Api;

use BuddyCli\Api\ExtendedBuddy;
use BuddyCli\Api\ExtendedExecutions;
use BuddyCli\Api\VariablesApi;
use BuddyCli\Tests\TestCase;

class ExtendedBuddyTest extends TestCase
{
    public function testGetApiExecutionsReturnsExtendedExecutions(): void
    {
        $buddy = new ExtendedBuddy(['accessToken' => 'test-token']);

        $executions = $buddy->getApiExecutions();

        $this->assertInstanceOf(ExtendedExecutions::class, $executions);
    }

    public function testGetApiExecutionsReturnsSameInstance(): void
    {
        $buddy = new ExtendedBuddy(['accessToken' => 'test-token']);

        $executions1 = $buddy->getApiExecutions();
        $executions2 = $buddy->getApiExecutions();

        $this->assertSame($executions1, $executions2);
    }

    public function testGetApiVariablesReturnsVariablesApi(): void
    {
        $buddy = new ExtendedBuddy(['accessToken' => 'test-token']);

        $variables = $buddy->getApiVariables();

        $this->assertInstanceOf(VariablesApi::class, $variables);
    }

    public function testGetApiVariablesReturnsSameInstance(): void
    {
        $buddy = new ExtendedBuddy(['accessToken' => 'test-token']);

        $vars1 = $buddy->getApiVariables();
        $vars2 = $buddy->getApiVariables();

        $this->assertSame($vars1, $vars2);
    }

    public function testParentApisStillAvailable(): void
    {
        $buddy = new ExtendedBuddy(['accessToken' => 'test-token']);

        // Parent Buddy class APIs should still be accessible
        $this->assertNotNull($buddy->getApiPipelines());
        $this->assertNotNull($buddy->getApiProjects());
        $this->assertNotNull($buddy->getApiWorkspaces());
        $this->assertNotNull($buddy->getApiWebhooks());
    }
}
