<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Auth;

use BuddyCli\Application;
use BuddyCli\Services\ConfigService;
use BuddyCli\Services\OAuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LoginCommand extends Command
{
    private const DEFAULT_PORT = 8085;
    private const PORT_RANGE = 100;

    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('login')
            ->setDescription('Authenticate with Buddy via OAuth')
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'OAuth client ID')
            ->addOption('client-secret', null, InputOption::VALUE_REQUIRED, 'OAuth client secret')
            ->addOption('no-browser', null, InputOption::VALUE_NONE, 'Print URL instead of opening browser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->app->getConfigService();

        // Get OAuth credentials
        $clientId = $input->getOption('client-id')
            ?? $config->get('client_id')
            ?? getenv('BUDDY_CLIENT_ID') ?: null;

        $clientSecret = $input->getOption('client-secret')
            ?? $config->get('client_secret')
            ?? getenv('BUDDY_CLIENT_SECRET') ?: null;

        if ($clientId === null || $clientSecret === null) {
            $output->writeln('<error>OAuth credentials required.</error>');
            $output->writeln('');
            $output->writeln('Provide credentials via:');
            $output->writeln('  --client-id and --client-secret options');
            $output->writeln('  BUDDY_CLIENT_ID and BUDDY_CLIENT_SECRET env vars');
            $output->writeln('  buddy config:set client_id <id> && buddy config:set client_secret <secret>');
            $output->writeln('');
            $output->writeln('Create an OAuth app at: https://app.buddy.works/my-apps');
            $output->writeln('Set callback URL to: http://127.0.0.1');
            return self::FAILURE;
        }

        // Find available port
        $port = $this->findAvailablePort();
        if ($port === null) {
            $output->writeln('<error>Could not find available port for callback server.</error>');
            return self::FAILURE;
        }

        $oauth = new OAuthService($clientId, $clientSecret);
        $state = $oauth->generateState();
        $redirectUri = "http://127.0.0.1:{$port}/callback";
        $authorizeUrl = $oauth->getAuthorizeUrl($redirectUri, $state);

        // Start callback server
        $socket = $this->createCallbackServer($port);
        if ($socket === false) {
            $output->writeln('<error>Could not start callback server.</error>');
            return self::FAILURE;
        }

        // Open browser or print URL
        if ($input->getOption('no-browser')) {
            $output->writeln('Open this URL in your browser:');
            $output->writeln($authorizeUrl);
        } else {
            $output->writeln('Opening browser to authenticate with Buddy...');
            $this->openBrowser($authorizeUrl);
        }

        $output->writeln('Waiting for authorization...');

        // Wait for callback
        $result = $this->waitForCallback($socket, $state, $output);
        fclose($socket);

        if ($result === null) {
            $output->writeln('<error>Authorization failed or timed out.</error>');
            return self::FAILURE;
        }

        // Exchange code for token
        try {
            $output->writeln('Exchanging code for token...');
            $tokenData = $oauth->exchangeCodeForToken($result['code'], $redirectUri);

            // Save token
            $config->set('token', $tokenData['access_token']);

            // Save refresh token if provided
            if (isset($tokenData['refresh_token'])) {
                $config->set('refresh_token', $tokenData['refresh_token']);
            }

            $output->writeln('');
            $output->writeln('<info>✓ Logged in successfully!</info>');
            $output->writeln('Token saved to ' . $config->getConfigFilePath());

            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to get access token: ' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }
    }

    private function findAvailablePort(): ?int
    {
        for ($port = self::DEFAULT_PORT; $port < self::DEFAULT_PORT + self::PORT_RANGE; $port++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if ($socket === false) {
                return $port; // Port is available
            }
            fclose($socket);
        }
        return null;
    }

    /**
     * @return resource|false
     */
    private function createCallbackServer(int $port)
    {
        $socket = @stream_socket_server(
            "tcp://127.0.0.1:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
        );

        return $socket;
    }

    private function waitForCallback($socket, string $expectedState, OutputInterface $output): ?array
    {
        // Set timeout
        stream_set_timeout($socket, 300); // 5 minute timeout

        $client = @stream_socket_accept($socket, 300);
        if ($client === false) {
            return null;
        }

        // Read request
        $request = '';
        while (($line = fgets($client)) !== false) {
            $request .= $line;
            if (trim($line) === '') {
                break;
            }
        }

        // Parse request
        if (!preg_match('/GET\s+([^\s]+)/', $request, $matches)) {
            $this->sendResponse($client, 400, 'Bad Request');
            fclose($client);
            return null;
        }

        $uri = $matches[1];
        $parts = parse_url($uri);
        parse_str($parts['query'] ?? '', $params);

        // Check for error
        if (isset($params['error'])) {
            $errorDesc = $params['error_description'] ?? $params['error'];
            $this->sendResponse($client, 400, "Authorization failed: {$errorDesc}");
            fclose($client);
            $output->writeln("<error>Authorization denied: {$errorDesc}</error>");
            return null;
        }

        // Validate state
        if (!isset($params['state']) || $params['state'] !== $expectedState) {
            $this->sendResponse($client, 400, 'Invalid state parameter');
            fclose($client);
            return null;
        }

        // Check for code
        if (!isset($params['code'])) {
            $this->sendResponse($client, 400, 'No authorization code received');
            fclose($client);
            return null;
        }

        // Send success response
        $this->sendResponse($client, 200, $this->getSuccessHtml());
        fclose($client);

        return ['code' => $params['code']];
    }

    /**
     * @param resource $client
     */
    private function sendResponse($client, int $status, string $body): void
    {
        $statusText = $status === 200 ? 'OK' : 'Bad Request';
        $contentType = str_contains($body, '<html>') ? 'text/html' : 'text/plain';

        $response = "HTTP/1.1 {$status} {$statusText}\r\n";
        $response .= "Content-Type: {$contentType}; charset=utf-8\r\n";
        $response .= "Content-Length: " . strlen($body) . "\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= $body;

        fwrite($client, $response);
    }

    private function getSuccessHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Buddy CLI - Authorization Successful</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        p { opacity: 0.9; }
        .checkmark {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="checkmark">✓</div>
        <h1>Authorization Successful</h1>
        <p>You can close this window and return to the terminal.</p>
    </div>
</body>
</html>
HTML;
    }

    private function openBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => 'open',
            'Windows' => 'start',
            default => 'xdg-open',
        };

        exec("{$command} " . escapeshellarg($url) . " > /dev/null 2>&1 &");
    }
}
