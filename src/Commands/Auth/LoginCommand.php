<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Auth;

use BuddyCli\Application;
use BuddyCli\Services\OAuthService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LoginCommand extends Command
{
    private const DEFAULT_PORT = 8085;

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
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port for callback server', (string) self::DEFAULT_PORT)
            ->addOption('no-browser', null, InputOption::VALUE_NONE, 'Print URL instead of opening browser')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Test callback server without OAuth (for verifying setup)')
            ->setHelp(<<<'HELP'
Authenticate with Buddy via OAuth browser flow.

<comment>Setup Requirements:</comment>
1. Create an OAuth app at https://app.buddy.works/my-apps
2. Set callback URL to: http://127.0.0.1:8085/callback
3. Provide credentials via options, env vars, or config:set

<comment>Credential Sources (checked in order):</comment>
  --client-id/--client-secret options
  BUDDY_CLIENT_ID/BUDDY_CLIENT_SECRET environment variables
  Values stored with config:set client_id/client_secret

Options:
      --client-id      OAuth client ID
      --client-secret  OAuth client secret
      --port           Local server port for callback (default: 8085)
      --no-browser     Print auth URL instead of opening browser (for SSH/headless)
      --test           Verify callback server works without full OAuth flow

Examples:
  buddy login
  buddy login --client-id=abc --client-secret=xyz
  buddy login --port=9000
  buddy login --no-browser
  buddy login --test
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->app->getConfigService();
        $port = (int) $input->getOption('port');

        // Test mode - just start server and wait for any request
        if ($input->getOption('test')) {
            return $this->runTestServer($port, $output);
        }

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
            $output->writeln("Set callback URL to: http://127.0.0.1:{$port}/callback");
            return self::FAILURE;
        }

        // Check if port is available
        if (!$this->isPortAvailable($port)) {
            $output->writeln("<error>Port {$port} is not available. Try --port=PORT with a different port.</error>");
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

            return self::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to get access token: ' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }
    }

    private function isPortAvailable(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($socket === false) {
            return true; // Port is available
        }
        fclose($socket);
        return false;
    }

    private function runTestServer(int $port, OutputInterface $output): int
    {
        if (!$this->isPortAvailable($port)) {
            $output->writeln("<error>Port {$port} is not available.</error>");
            return self::FAILURE;
        }

        $socket = $this->createCallbackServer($port);
        if ($socket === false) {
            $output->writeln('<error>Could not start callback server.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Callback server running!</info>');
        $output->writeln('');
        $output->writeln("URL: <comment>http://127.0.0.1:{$port}/callback</comment>");
        $output->writeln('');
        $output->writeln('Test with:');
        $output->writeln("  curl \"http://127.0.0.1:{$port}/callback?code=test&state=test\"");
        $output->writeln('');
        $output->writeln('Or open in browser:');
        $output->writeln("  http://127.0.0.1:{$port}/callback?code=test&state=test");
        $output->writeln('');
        $output->writeln('Waiting for request (Ctrl+C to stop)...');

        $client = @stream_socket_accept($socket, 300);
        if ($client === false) {
            $output->writeln('<comment>Timeout - no request received.</comment>');
            fclose($socket);
            return self::SUCCESS;
        }

        // Read request
        $request = '';
        while (($line = fgets($client)) !== false) {
            $request .= $line;
            if (trim($line) === '') {
                break;
            }
        }

        $output->writeln('<info>Request received!</info>');

        // Parse and display
        if (preg_match('/GET\s+([^\s]+)/', $request, $matches)) {
            $uri = $matches[1];
            $parts = parse_url($uri);
            parse_str($parts['query'] ?? '', $params);
            $output->writeln('Path: ' . ($parts['path'] ?? '/'));
            $output->writeln('Params: ' . json_encode($params));
        }

        // Send success response
        $this->sendResponse($client, 200, $this->getSuccessHtml());
        fclose($client);
        fclose($socket);

        $output->writeln('');
        $output->writeln('<info>Test complete! The callback URL is working.</info>');
        $output->writeln("Register this callback URL in Buddy: http://127.0.0.1:{$port}/callback");

        return self::SUCCESS;
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
        *,:after,:before {
            --tw-border-spacing-x: 0;
            --tw-border-spacing-y: 0;
            --tw-translate-x: 0;
            --tw-translate-y: 0;
            --tw-rotate: 0;
            --tw-skew-x: 0;
            --tw-skew-y: 0;
            --tw-scale-x: 1;
            --tw-scale-y: 1;
            --tw-pan-x: ;
            --tw-pan-y: ;
            --tw-pinch-zoom: ;
            --tw-scroll-snap-strictness: proximity;
            --tw-gradient-from-position: ;
            --tw-gradient-via-position: ;
            --tw-gradient-to-position: ;
            --tw-ordinal: ;
            --tw-slashed-zero: ;
            --tw-numeric-figure: ;
            --tw-numeric-spacing: ;
            --tw-numeric-fraction: ;
            --tw-ring-inset: ;
            --tw-ring-offset-width: 0px;
            --tw-ring-offset-color: #fff;
            --tw-ring-color: rgba(59,130,246,.5);
            --tw-ring-offset-shadow: 0 0 #0000;
            --tw-ring-shadow: 0 0 #0000;
            --tw-shadow: 0 0 #0000;
            --tw-shadow-colored: 0 0 #0000;
            --tw-blur: ;
            --tw-brightness: ;
            --tw-contrast: ;
            --tw-grayscale: ;
            --tw-hue-rotate: ;
            --tw-invert: ;
            --tw-saturate: ;
            --tw-sepia: ;
            --tw-drop-shadow: ;
            --tw-backdrop-blur: ;
            --tw-backdrop-brightness: ;
            --tw-backdrop-contrast: ;
            --tw-backdrop-grayscale: ;
            --tw-backdrop-hue-rotate: ;
            --tw-backdrop-invert: ;
            --tw-backdrop-opacity: ;
            --tw-backdrop-saturate: ;
            --tw-backdrop-sepia: ;
            --tw-contain-size: ;
            --tw-contain-layout: ;
            --tw-contain-paint: ;
            --tw-contain-style: ;
        }

        body {
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f7fa;
            --tw-text-opacity: 1;
            color: rgb(29 33 48 / var(--tw-text-opacity, 1));
        }
        .container {
            text-align: center;
            padding: 3rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        p {
            opacity: 0.7;
            font-size: 0.95rem;
            margin: 0;
        }
        .checkmark {
            display: inline-block;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            line-height: 1;
            font-size: 1.2rem;
            line-height: 64px;
            margin-right: 11px;
            line-height: 1.9;
            font-weight: 600;
            --tw-bg-opacity: 1;
            background-color: rgb(191 255 90/var(--tw-bg-opacity,1));
            color: rgb(29 33 48 / var(--tw-text-opacity, 1));
            transition-property: box-shadow;
            transition-timing-function: cubic-bezier(.4,0,.2,1);
            transition-duration: .15s;
            --tw-shadow: 0px 1px 0px 0px hsla(0, 0%, 100%, .72) inset, 0px -2px 0px 0px rgba(29, 33, 48, .12) inset, 0px 1px 1px -1px rgba(29, 33, 48, .04), 0px 4px 4px -2px rgba(29, 33, 48, .04);
            --tw-shadow-colored: inset 0px 1px 0px 0px var(--tw-shadow-color), inset 0px -2px 0px 0px var(--tw-shadow-color), 0px 1px 1px -1px var(--tw-shadow-color), 0px 4px 4px -2px var(--tw-shadow-color);
            box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">

        <svg width="175" height="40" viewBox="0 0 175 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fillRule="evenodd" clipRule="evenodd" d="M15.3365 0.727699C17.0132 -0.242566 19.0812 -0.242566 20.7579 0.727699C24.1672 2.69394 29.9688 6.04628 33.3781 8.01252C35.0548 8.98279 36.0944 10.7746 36.0944 12.7152V27.2848C36.0944 29.2254 35.0548 31.0172 33.3781 31.9875C29.9688 33.9537 24.1672 37.3061 20.7579 39.2723C19.0812 40.2426 17.0132 40.2426 15.3365 39.2723C11.9271 37.3061 6.11455 33.9537 2.71638 31.9875C1.02848 31.0172 0 29.2254 0 27.2848V12.7152C0 10.7746 1.02848 8.98279 2.71638 8.01252C6.11455 6.04628 11.9271 2.69394 15.3365 0.727699Z" fill="#1A86FD" />
            <mask id="mask0_1814_24353" style={{ maskType: 'luminance' }} maskUnits="userSpaceOnUse" x="0" y="0" width="37" height="40">
            <path d="M15.3365 0.727699C17.0132 -0.242566 19.0812 -0.242566 20.7579 0.727699C24.1672 2.69394 29.9688 6.04628 33.3781 8.01252C35.0548 8.98279 36.0944 10.7746 36.0944 12.7152V27.2848C36.0944 29.2254 35.0548 31.0172 33.3781 31.9875C29.9688 33.9537 24.1672 37.3061 20.7579 39.2723C19.0812 40.2426 17.0132 40.2426 15.3365 39.2723C11.9271 37.3061 6.11455 33.9537 2.71638 31.9875C1.02848 31.0172 0 29.2254 0 27.2848V12.7152C0 10.7746 1.02848 8.98279 2.71638 8.01252C6.11455 6.04628 11.9271 2.69394 15.3365 0.727699Z" fill="white" />
            </mask>
            <g mask="url(#mask0_1814_24353)">
            <path fillRule="evenodd" clipRule="evenodd" d="M-0.186768 30.5781L9.41251 20.1542L18.0859 40.9971L-0.186768 30.5781Z" fill="#1A67FD" />
            <path fillRule="evenodd" clipRule="evenodd" d="M9.41251 20.1541L-0.058483 9.65967L-0.186768 30.578L9.41251 20.1541Z" fill="#0DA7FE" />
            <path fillRule="evenodd" clipRule="evenodd" d="M9.41234 20.1542L19.8292 -4.87781L-0.0586548 9.65976L9.41234 20.1542Z" fill="#05BBFF" />
            <path fillRule="evenodd" clipRule="evenodd" d="M18.0859 -0.688843L36.1381 30.5756L9.41254 20.1541" fill="#00C9FF" />
            <path fillRule="evenodd" clipRule="evenodd" d="M18.0859 41.0328L36.1381 30.5781L9.41254 20.1542L18.0859 41.0328Z" fill="#1A86FD" />
            <path fillRule="evenodd" clipRule="evenodd" d="M36.1381 30.5756V7.22667L18.0859 -0.688843L36.1381 30.5756Z" fill="#05BBFF" />
            <path fillRule="evenodd" clipRule="evenodd" d="M14.3058 13.0902C14.6097 12.7864 15.0218 12.6157 15.4515 12.6157C15.8812 12.6157 16.2933 12.7864 16.5971 13.0902C18.2702 14.7634 21.3647 17.8582 23.0412 19.5348C23.6774 20.171 23.6774 21.2025 23.0412 21.8388C21.3647 23.5154 18.2702 26.6098 16.5971 28.2831C16.2933 28.5871 15.8812 28.7578 15.4515 28.7578C15.0218 28.7578 14.6097 28.5871 14.3058 28.2831H14.3058C13.6731 27.6502 13.6731 26.6246 14.3058 25.9918C16.1593 24.1383 19.6107 20.6866 19.6107 20.6866C19.6107 20.6866 16.1593 17.2353 14.3058 15.3814C13.6731 14.749 13.6731 13.723 14.3058 13.0905C14.3058 13.0905 14.3058 13.0905 14.3058 13.0902Z" fill="#1A67FD" fillOpacity="0.5" />
            <path fillRule="evenodd" clipRule="evenodd" d="M23.0499 20.8019C23.6818 20.17 23.6818 19.1456 23.0501 18.5138L23.05 18.5137C22.7457 18.2095 22.3331 18.0385 21.9028 18.0385C21.4725 18.0385 21.0597 18.2095 20.7554 18.5139C19.0794 20.19 15.9791 23.2905 14.305 24.9647C13.6733 25.5964 13.6733 26.6207 14.305 27.2525L14.3051 27.2526C14.6094 27.5569 15.022 27.7278 15.4523 27.7278C15.8826 27.7278 16.2953 27.5568 16.5994 27.2528C18.2757 25.5764 21.3757 22.4761 23.0499 20.8019Z" fill="#D6FFFF" />
            <path fillRule="evenodd" clipRule="evenodd" d="M16.6023 12.0657C16.2974 11.7608 15.8836 11.5893 15.4522 11.5893C15.0211 11.5895 14.6072 11.7607 14.3022 12.0657L14.3021 12.0657C13.6719 12.696 13.6721 13.718 14.3021 14.3481C15.9749 16.0209 19.0749 19.1212 20.7526 20.7989C21.0577 21.1041 21.4713 21.2753 21.9027 21.2753C22.334 21.2754 22.7476 21.104 23.0526 20.799L23.0527 20.7989C23.6829 20.1686 23.683 19.1469 23.0527 18.5165L16.6023 12.0657Z" fill="#D6FFFF" />
            </g>
            <path d="M170.499 27.9851C168.399 27.9851 166.908 26.9771 166.257 26.0951L168.231 24.1841C168.819 24.7091 169.554 25.1501 170.667 25.1501C171.339 25.1501 171.654 24.9401 171.654 24.5411C171.654 23.2811 166.614 23.8271 166.614 19.8161C166.614 17.6951 168.462 16.4141 170.856 16.4141C172.956 16.4141 174.153 17.4221 174.804 18.3041L172.536 20.0891C172.2 19.7531 171.57 19.2281 170.709 19.2281C170.205 19.2281 169.911 19.4381 169.911 19.7741C169.911 21.1601 174.993 20.3621 174.993 24.3521C174.993 26.5991 173.019 27.9851 170.499 27.9851Z" fill="#0A0D16" />
            <path d="M165.657 16.6871L162.108 21.7271L165.951 27.7121H161.835L158.748 22.5671V27.7121H155.241V12.5291H158.748V21.1181L161.73 16.6871H165.657Z" fill="#0A0D16" />
            <path d="M153.831 16.4141V19.9841C153.684 19.9631 153.516 19.9421 153.264 19.9421C151.563 19.9421 150.996 21.1811 150.996 22.8401V27.7121H147.489V16.6871H150.912V18.1571C151.437 17.0861 152.466 16.4141 153.831 16.4141Z" fill="#0A0D16" />
            <path d="M134.002 22.2311C134.002 18.8921 136.585 16.4141 139.987 16.4141C143.536 16.4141 146.035 18.8501 146.035 22.1891C146.035 25.5071 143.473 27.9851 139.987 27.9851C136.48 27.9851 134.002 25.5491 134.002 22.2311ZM137.509 22.2311C137.509 23.8271 138.559 24.8981 139.987 24.8981C141.436 24.8981 142.528 23.7641 142.528 22.1891C142.528 20.5721 141.415 19.5011 139.987 19.5011C138.538 19.5011 137.509 20.6141 137.509 22.2311Z" fill="#0A0D16" />
            <path d="M125.802 27.7121L123.429 18.4721L121.056 27.7121H116.982L113.16 13.0121H117.003L119.229 23.3651L121.812 13.0121H125.046L127.629 23.3651L129.855 13.0121H133.698L129.876 27.7121H125.802Z" fill="#0A0D16" />
            <path d="M108.73 16.687L103.27 31.471H99.9515L101.443 27.355L97.3265 16.687H101.086L103.039 23.029L105.076 16.687H108.73Z" fill="#0A0D16" />
            <path d="M92.7906 22.2311C92.7906 20.4881 91.8036 19.5011 90.4176 19.5011C88.9896 19.5011 88.0446 20.6141 88.0446 22.2311C88.0446 23.8691 89.0106 24.9191 90.4176 24.9191C91.9296 24.9191 92.7906 23.7011 92.7906 22.2311ZM92.9166 27.7121V26.8511C92.6016 27.2081 91.5516 27.9851 89.9346 27.9851C86.7636 27.9851 84.5376 25.5911 84.5376 22.2101C84.5376 18.7871 86.5536 16.4141 89.7456 16.4141C91.4256 16.4141 92.4546 17.2541 92.6856 17.5271V12.5291H96.1926V27.7121H92.9166Z" fill="#0A0D16" />
            <path d="M79.6655 22.2311C79.6655 20.4881 78.6785 19.5011 77.2925 19.5011C75.8645 19.5011 74.9195 20.6141 74.9195 22.2311C74.9195 23.8691 75.8855 24.9191 77.2925 24.9191C78.8045 24.9191 79.6655 23.7011 79.6655 22.2311ZM79.7915 27.7121V26.8511C79.4765 27.2081 78.4265 27.9851 76.8095 27.9851C73.6385 27.9851 71.4125 25.5911 71.4125 22.2101C71.4125 18.7871 73.4285 16.4141 76.6205 16.4141C78.3005 16.4141 79.3295 17.2541 79.5605 17.5271V12.5291H83.0675V27.7121H79.7915Z" fill="#0A0D16" />
            <path d="M63.5486 16.687V23.26C63.5486 24.289 64.1576 24.835 64.9976 24.835C65.9636 24.835 66.5096 24.142 66.5096 23.26V16.687H70.0166V27.712H66.5936V26.578C66.1736 27.229 65.2076 27.985 63.8216 27.985C60.3776 27.985 60.0416 25.045 60.0416 22.798V16.687H63.5486Z" fill="#0A0D16" />
            <path d="M58.5734 23.3231C58.5734 26.3051 56.3264 27.7121 53.5124 27.7121H48.0944V13.0121H53.3444C56.3264 13.0121 58.1114 14.4401 58.1114 16.9601C58.1114 18.2201 57.5234 19.4381 56.5154 20.0051C57.9644 20.6141 58.5734 21.8111 58.5734 23.3231ZM53.4284 21.6221H51.6644V24.6251H53.4074C54.3524 24.6251 54.8774 23.9531 54.8774 23.1131C54.8774 22.2311 54.3734 21.6221 53.4284 21.6221ZM53.1554 16.0781H51.6644V18.7451H53.1974C54.0164 18.7451 54.4574 18.1781 54.4574 17.3801C54.4574 16.6031 54.0374 16.0781 53.1554 16.0781Z" fill="#0A0D16" />
        </svg>

        <hr style="
            border-top: 1px solid #d2d2d2;
            display: block;
            width: 100%;
        ">
        <h1><div class="checkmark">✓</div>CLI Connected!</h1>
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
