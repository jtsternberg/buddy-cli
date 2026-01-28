<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AuthCommandsTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_TOKEN');
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
        $this->unsetEnv('BUDDY_CLIENT_ID');
        $this->unsetEnv('BUDDY_CLIENT_SECRET');

        $this->app = new Application();
    }

    // Login command tests

    public function testLoginRequiresCredentials(): void
    {
        $command = $this->app->find('login');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('OAuth credentials required', $tester->getDisplay());
        $this->assertStringContainsString('--client-id', $tester->getDisplay());
        $this->assertStringContainsString('BUDDY_CLIENT_ID', $tester->getDisplay());
    }

    public function testLoginRequiresBothCredentials(): void
    {
        // Only client_id provided
        $command = $this->app->find('login');
        $tester = new CommandTester($command);
        $tester->execute(['--client-id' => 'test-id']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('OAuth credentials required', $tester->getDisplay());
    }

    public function testLoginShowsCallbackUrlInError(): void
    {
        $command = $this->app->find('login');
        $tester = new CommandTester($command);
        $tester->execute(['--port' => '18089']);

        $output = $tester->getDisplay();
        // Error message should include callback URL hint
        $this->assertStringContainsString('http://127.0.0.1:18089/callback', $output);
    }

    public function testLoginNoBrowserOutputsUrl(): void
    {
        // Run login in background process and capture initial output
        $port = 18090 + rand(0, 100);
        $cmd = sprintf(
            'cd %s && timeout 2 php bin/buddy login --client-id=test-id --client-secret=test-secret --no-browser --port=%d 2>&1 || true',
            escapeshellarg(dirname(__DIR__, 3)),
            $port
        );

        $output = shell_exec($cmd);

        $this->assertStringContainsString('Open this URL in your browser', $output);
        $this->assertStringContainsString('https://api.buddy.works/oauth2/authorize', $output);
        $this->assertStringContainsString('client_id=test-id', $output);
        // Port is URL-encoded in redirect_uri
        $this->assertStringContainsString("127.0.0.1%3A{$port}", $output);
    }

    public function testLoginTestModeStartsServer(): void
    {
        // Run login --test in background
        $port = 18190 + rand(0, 100);
        $cmd = sprintf(
            'cd %s && timeout 2 php bin/buddy login --test --port=%d 2>&1 || true',
            escapeshellarg(dirname(__DIR__, 3)),
            $port
        );

        $output = shell_exec($cmd);

        $this->assertStringContainsString('Callback server running', $output);
        $this->assertStringContainsString("http://127.0.0.1:{$port}/callback", $output);
        $this->assertStringContainsString('curl', $output);
    }

    public function testLoginTestModeHandlesCallback(): void
    {
        $port = 18290 + rand(0, 100);

        // Start server in background
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cmd = sprintf(
            'cd %s && php bin/buddy login --test --port=%d',
            escapeshellarg(dirname(__DIR__, 3)),
            $port
        );

        $process = proc_open($cmd, $descriptors, $pipes);
        $this->assertIsResource($process);

        // Wait for server to start
        usleep(500000); // 500ms

        // Make HTTP request to callback
        $response = @file_get_contents("http://127.0.0.1:{$port}/callback?code=test&state=test");

        // Read output
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $this->assertStringContainsString('Request received', $output);
        $this->assertStringContainsString('Test complete', $output);
    }

    public function testLoginPortUnavailable(): void
    {
        // Bind to a port first
        $port = 18390 + rand(0, 100);
        $socket = @stream_socket_server("tcp://127.0.0.1:{$port}");

        if ($socket === false) {
            $this->markTestSkipped('Could not bind test socket');
        }

        try {
            // Try to run login on same port
            $cmd = sprintf(
                'cd %s && php bin/buddy login --client-id=test-id --client-secret=test-secret --port=%d 2>&1',
                escapeshellarg(dirname(__DIR__, 3)),
                $port
            );

            $output = shell_exec($cmd);

            $this->assertStringContainsString("Port {$port} is not available", $output);
        } finally {
            fclose($socket);
        }
    }

    // Logout command tests

    public function testLogoutClearsToken(): void
    {
        // Set a token first
        $setCommand = $this->app->find('config:set');
        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'token', 'value' => 'test-token-123']);

        // Run logout
        $command = $this->app->find('logout');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Logged out successfully.', $tester->getDisplay());

        // Verify token is removed
        $showCommand = $this->app->find('config:show');
        $showTester = new CommandTester($showCommand);
        $showTester->execute(['--json' => true]);

        $output = $showTester->getDisplay();
        $data = json_decode($output, true);
        $this->assertArrayNotHasKey('token', $data);
    }

    public function testLogoutClearsRefreshToken(): void
    {
        // Set both token and refresh_token
        $setCommand = $this->app->find('config:set');

        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'token', 'value' => 'test-token-123']);

        $setTester = new CommandTester($setCommand);
        $setTester->execute(['key' => 'refresh_token', 'value' => 'test-refresh-456']);

        // Run logout
        $command = $this->app->find('logout');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Logged out successfully.', $tester->getDisplay());

        // Verify both tokens are removed
        $showCommand = $this->app->find('config:show');
        $showTester = new CommandTester($showCommand);
        $showTester->execute(['--json' => true]);

        $output = $showTester->getDisplay();
        $data = json_decode($output, true);
        $this->assertArrayNotHasKey('token', $data);
        $this->assertArrayNotHasKey('refresh_token', $data);
    }

    public function testLogoutSucceedsEvenWithNoCredentials(): void
    {
        // Run logout with no existing credentials
        $command = $this->app->find('logout');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Logged out successfully.', $tester->getDisplay());
    }
}
