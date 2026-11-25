<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Commands\Auth\LoginCommand;
use BuddyCli\Services\OAuthService;
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
        $this->unsetEnv('BUDDY_SCOPES');

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
            'cd %s && timeout 2 php bin/buddy.php login --client-id=test-id --client-secret=test-secret --no-browser --port=%d 2>&1 || true',
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
            'cd %s && timeout 2 php bin/buddy.php login --test --port=%d 2>&1 || true',
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
            'cd %s && php bin/buddy.php login --test --port=%d',
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
                'cd %s && php bin/buddy.php login --client-id=test-id --client-secret=test-secret --port=%d 2>&1',
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
    // Scopes

    public function testLoginRejectsUnknownScope(): void
    {
        $tester = new CommandTester($this->app->find('login'));
        $tester->execute(['--client-id' => 'id', '--client-secret' => 'secret', '--no-browser' => true, '--scopes' => 'PIPELINE_READ,BOGUS']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown scope: BOGUS', $tester->getDisplay());
    }

    public function testLoginRejectsUnknownScopeFromEnv(): void
    {
        $this->setEnv('BUDDY_SCOPES', 'NOPE');

        $tester = new CommandTester($this->app->find('login'));
        $tester->execute(['--client-id' => 'id', '--client-secret' => 'secret', '--no-browser' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown scope: NOPE', $tester->getDisplay());
    }

    public function testLoginNoBrowserRequestsGivenScopes(): void
    {
        $port = 18490 + rand(0, 100);
        $cmd = sprintf(
            'cd %s && timeout 2 php bin/buddy.php login --client-id=test-id --client-secret=test-secret --no-browser --port=%d --scopes="PIPELINE_READ WORKSPACE_READ" 2>&1 || true',
            escapeshellarg(dirname(__DIR__, 3)),
            $port
        );

        $output = shell_exec($cmd);

        $this->assertStringContainsString('scope=PIPELINE_READ+WORKSPACE_READ&', $output);
    }

    public function testPasteLoginExchangesCodeAndRecordsScopes(): void
    {
        $oauth = $this->fakeOAuth();
        $tester = $this->pasteTester($oauth);
        $tester->setInputs(['http://127.0.0.1:8085/callback?code=the-code&state=fixed-state']);

        $tester->execute(['--client-id' => 'id', '--client-secret' => 'secret', '--paste' => true, '--scopes' => 'pipeline_read,WORKSPACE_READ']);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('scope=PIPELINE_READ+WORKSPACE_READ', $tester->getDisplay());
        $this->assertSame(['the-code', 'http://127.0.0.1:8085/callback'], $oauth->exchanged);

        $config = $this->app->getConfigService();
        $this->assertSame('access-1', $config->get('token'));
        $this->assertSame('refresh-1', $config->get('refresh_token'));
        $this->assertSame('PIPELINE_READ,WORKSPACE_READ', $config->get('granted_scopes'));
    }

    public function testPasteLoginRecordsDefaultScopesWithoutOverride(): void
    {
        $oauth = $this->fakeOAuth();
        $tester = $this->pasteTester($oauth);
        $tester->setInputs(['http://127.0.0.1:8085/callback?code=c&state=fixed-state']);

        $tester->execute(['--client-id' => 'id', '--client-secret' => 'secret', '--paste' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame(implode(',', $oauth->getDefaultScopes()), $this->app->getConfigService()->get('granted_scopes'));
    }

    public function testPasteLoginRejectsStateMismatch(): void
    {
        $oauth = $this->fakeOAuth();
        $tester = $this->pasteTester($oauth);
        $tester->setInputs(['http://127.0.0.1:8085/callback?code=c&state=forged']);

        $tester->execute(['--client-id' => 'id', '--client-secret' => 'secret', '--paste' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('state', $tester->getDisplay());
        $this->assertNull($oauth->exchanged);
        $this->assertNull($this->app->getConfigService()->get('token'));
    }

    public function testPasteLoginDoesNotNeedCallbackPort(): void
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:18595');
        if ($socket === false) {
            $this->markTestSkipped('Could not bind test socket');
        }

        try {
            $tester = $this->pasteTester($this->fakeOAuth());
            $tester->setInputs(['http://127.0.0.1:18595/callback?code=c&state=fixed-state']);
            $tester->execute(['--client-id' => 'id', '--client-secret' => 'secret', '--paste' => true, '--port' => '18595']);

            $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        } finally {
            fclose($socket);
        }
    }

    public function testLogoutClearsGrantedScopes(): void
    {
        $config = $this->app->getConfigService();
        $config->set('token', 't');
        $config->set('granted_scopes', 'PIPELINE_READ');

        (new CommandTester($this->app->find('logout')))->execute([]);

        $this->assertNull($config->get('granted_scopes'));
    }

    private function fakeOAuth(): OAuthService
    {
        return new class ('id', 'secret') extends OAuthService {
            public ?array $exchanged = null;

            public function generateState(): string
            {
                return 'fixed-state';
            }

            public function exchangeCodeForToken(string $code, string $redirectUri): array
            {
                $this->exchanged = [$code, $redirectUri];
                return ['access_token' => 'access-1', 'refresh_token' => 'refresh-1'];
            }
        };
    }

    private function pasteTester(OAuthService $oauth): CommandTester
    {
        $command = new class ($this->app, $oauth) extends LoginCommand {
            public function __construct(Application $app, private OAuthService $fake)
            {
                parent::__construct($app);
            }

            protected function createOAuthService(string $clientId, string $clientSecret): OAuthService
            {
                return $this->fake;
            }
        };
        $this->app->addCommand($command);

        return new CommandTester($command);
    }
}
