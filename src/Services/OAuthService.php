<?php

declare(strict_types=1);

namespace BuddyCli\Services;

use BuddyCli\Sdk\BuddyOAuth;

class OAuthService
{
    private const AUTHORIZE_URL = 'https://api.buddy.works/oauth2/authorize';
    private const TOKEN_URL = 'https://api.buddy.works/oauth2/token';

    public const SCOPE_VARIABLE_INFO = 'VARIABLE_INFO';
    public const SCOPE_VARIABLE_ADD = 'VARIABLE_ADD';
    public const SCOPE_VARIABLE_MANAGE = 'VARIABLE_MANAGE';

    /**
     * Scope names from Buddy's current OAuth docs
     * (https://buddy.works/docs/api/getting-started/oauth2/introduction#supported-scopes).
     */
    private const SCOPE_FAMILIES = [
        'USER' => ['USER_READ', 'USER_WRITE'],
        'USER_SSH_KEY' => ['USER_SSH_KEY_READ', 'USER_SSH_KEY_WRITE', 'USER_SSH_KEY_MANAGE'],
        'USER_EMAIL' => ['USER_EMAIL_READ', 'USER_EMAIL_WRITE', 'USER_EMAIL_MANAGE'],
        'USER_PAT' => ['USER_PAT_READ', 'USER_PAT_WRITE', 'USER_PAT_MANAGE'],
        'WORKSPACE' => ['WORKSPACE_READ', 'WORKSPACE_MANAGE'],
        'PROJECT' => ['PROJECT_READ', 'PROJECT_WRITE', 'PROJECT_MANAGE'],
        'MEMBER' => ['MEMBER_READ', 'MEMBER_WRITE', 'MEMBER_MANAGE'],
        'GROUP' => ['GROUP_READ', 'GROUP_WRITE', 'GROUP_MANAGE'],
        'PERMISSION' => ['PERMISSION_READ', 'PERMISSION_WRITE', 'PERMISSION_MANAGE'],
        'REPOSITORY' => ['REPOSITORY_READ', 'REPOSITORY_WRITE'],
        'RULESET' => ['RULESET_READ', 'RULESET_MANAGE'],
        'PIPELINE' => ['PIPELINE_READ', 'PIPELINE_RUN', 'PIPELINE_WRITE', 'PIPELINE_MANAGE'],
        'TARGET' => ['TARGET_READ', 'TARGET_USE', 'TARGET_WRITE', 'TARGET_MANAGE'],
        'DOMAIN' => ['DOMAIN_READ', 'DOMAIN_WRITE', 'DOMAIN_MANAGE'],
        'INTEGRATION' => ['INTEGRATION_READ', 'INTEGRATION_WRITE', 'INTEGRATION_MANAGE'],
        'WEBHOOK' => ['WEBHOOK_READ', 'WEBHOOK_WRITE', 'WEBHOOK_MANAGE'],
        'VARIABLE' => ['VARIABLE_READ', 'VARIABLE_WRITE', 'VARIABLE_MANAGE'],
        'ENVIRONMENT' => ['ENVIRONMENT_READ', 'ENVIRONMENT_WRITE', 'ENVIRONMENT_MANAGE'],
        'DISTRIBUTION' => ['DISTRIBUTION_READ', 'DISTRIBUTION_WRITE', 'DISTRIBUTION_MANAGE'],
        'SANDBOX' => ['SANDBOX_READ', 'SANDBOX_WRITE', 'SANDBOX_MANAGE'],
        'UNIT_TEST' => ['UNIT_TEST_READ', 'UNIT_TEST_WRITE', 'UNIT_TEST_MANAGE'],
        'VISUAL_TEST' => ['VISUAL_TEST_READ', 'VISUAL_TEST_WRITE', 'VISUAL_TEST_MANAGE'],
        'CRAWL' => ['CRAWL_READ', 'CRAWL_WRITE', 'CRAWL_MANAGE'],
        'TUNNEL' => ['TUNNEL_READ', 'TUNNEL_MANAGE'],
    ];

    private const STANDALONE_SCOPES = [
        'MEMBER_EMAIL_READ', 'ARTIFACT_READ', 'ARTIFACT_WRITE', 'ARTIFACT_MANAGE',
        'MASKED_VARIABLE_VALUE_DECRYPTED',
    ];

    /**
     * Older names Buddy no longer documents. getDefaultScopes() still sends
     * them and existing logins hold them, so they stay accepted.
     */
    private const LEGACY_SCOPES = [
        'WORKSPACE', 'PROJECT_DELETE', 'EXECUTION_INFO', 'EXECUTION_RUN', 'EXECUTION_MANAGE',
        'USER_INFO', 'USER_KEY', 'USER_EMAIL', 'INTEGRATION_INFO', 'INTEGRATION_ADD',
        'MEMBER_EMAIL', 'MANAGE_EMAILS', 'WEBHOOK_INFO', 'WEBHOOK_ADD',
        'VARIABLE_INFO', 'VARIABLE_ADD',
    ];

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
            BuddyOAuth::SCOPE_EXECUTION_MANAGE,
            BuddyOAuth::SCOPE_USER_INFO,
            BuddyOAuth::SCOPE_INTEGRATION_INFO,
            self::SCOPE_VARIABLE_INFO,
            self::SCOPE_VARIABLE_ADD,
            self::SCOPE_VARIABLE_MANAGE,
        ];
    }

    /**
     * Parse a comma- and/or space-separated scope list, normalised to
     * uppercase and de-duplicated.
     *
     * @return string[]
     * @throws \InvalidArgumentException on an empty list or an unknown scope
     */
    public static function parseScopes(string $list): array
    {
        $scopes = array_values(array_unique(array_map(
            'strtoupper',
            preg_split('/[\s,]+/', trim($list), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        if ($scopes === []) {
            throw new \InvalidArgumentException('No scopes given.');
        }

        $known = self::knownScopes();
        foreach ($scopes as $scope) {
            if (!in_array($scope, $known, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown scope: %s. Known scopes: %s',
                    $scope,
                    implode(', ', $known)
                ));
            }
        }

        return $scopes;
    }

    /**
     * @return string[]
     */
    public static function knownScopes(): array
    {
        return array_merge(array_merge(...array_values(self::SCOPE_FAMILIES)), self::STANDALONE_SCOPES, self::LEGACY_SCOPES);
    }

    /**
     * Extract code and state from the URL the browser was redirected to.
     * Accepts the full URL or just its query string.
     *
     * @return array{code: string, state: string}
     */
    public static function parseCallbackUrl(string $url): array
    {
        $url = trim($url);
        $query = str_contains($url, '?') ? substr($url, strpos($url, '?') + 1) : $url;
        $query = explode('#', $query, 2)[0];
        parse_str($query, $params);

        if (isset($params['error'])) {
            throw new \RuntimeException('Authorization denied: ' . ($params['error_description'] ?? $params['error']));
        }

        if (empty($params['code']) || !is_string($params['code'])) {
            throw new \RuntimeException('No authorization code in the pasted URL.');
        }

        return ['code' => $params['code'], 'state' => is_string($params['state'] ?? null) ? $params['state'] : ''];
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
        return $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], 'exchange code');
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], 'refresh token');
    }

    public function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function requestToken(array $params, string $action): array
    {
        $params['client_id'] = $this->clientId;
        $params['client_secret'] = $this->clientSecret;

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
            throw new \RuntimeException("Failed to {$action}: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Failed to {$action} (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if (!isset($data['access_token'])) {
            throw new \RuntimeException("Failed to {$action}: no access token in response");
        }

        return $data;
    }
}
