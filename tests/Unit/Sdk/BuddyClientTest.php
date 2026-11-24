<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Sdk;

use BuddyCli\Sdk\BuddyClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BuddyClientTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    private function clientReturning(Response $response): BuddyClient
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->history));

        $client = new BuddyClient();
        (new \ReflectionProperty(BuddyClient::class, 'guzzle'))->setValue($client, new Client(['handler' => $stack]));

        return $client;
    }

    public function testEmptyPostBodyIsSentAsJsonObject(): void
    {
        $client = $this->clientReturning(new Response(200, [], '{}'));

        $client->postJson('token', 'https://api.buddy.works/x', []);

        $this->assertSame('{}', (string) $this->history[0]['request']->getBody());
    }

    public function testNonEmptyPostBodyIsUnchanged(): void
    {
        $client = $this->clientReturning(new Response(200, [], '{}'));

        $client->postJson('token', 'https://api.buddy.works/x', ['comment' => 'hi']);

        $this->assertSame('{"comment":"hi"}', (string) $this->history[0]['request']->getBody());
    }
}
