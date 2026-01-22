<?php

declare(strict_types=1);

namespace BuddyCli\Services;

use Buddy\BuddyOAuth;

class OAuthService
{
    private const AUTHORIZE_URL = 'https://api.buddy.works/oauth2/authorize';
    private const TOKEN_URL = 'https://api.buddy.works/oauth2/token';

    private string $clientId;
    private string $clientSecret;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function getDefaultScopes(): array
    {
        return [
            BuddyOAuth::SCOPE_WORKSPACE,
            BuddyOAuth::SCOPE_EXECUTION_INFO,
            BuddyOAuth::SCOPE_EXECUTION_RUN,
            BuddyOAuth::SCOPE_USER_INFO,
        ];
    }

    public function getAuthorizeUrl(string $redirectUri, string $state, array $scopes = []): string
    {
        if (empty($scopes)) {
            $scopes = $this->getDefaultScopes();
        }

        $params = [
            'type' => 'web_server',
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Failed to exchange code: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Token exchange failed with HTTP {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new \RuntimeException('No access token in response: ' . $response);
        }

        return $data;
    }

    public function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }
}
