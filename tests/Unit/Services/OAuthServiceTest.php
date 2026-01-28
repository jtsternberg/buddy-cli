<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Services;

use Buddy\BuddyOAuth;
use BuddyCli\Services\OAuthService;
use BuddyCli\Tests\TestCase;

/**
 * Testable OAuthService that allows mocking HTTP responses.
 */
class TestableOAuthService extends OAuthService
{
    private ?string $mockResponse = null;
    private ?int $mockHttpCode = null;
    private ?string $mockError = null;
    public ?array $lastRequestParams = null;

    public function setMockResponse(string $response, int $httpCode = 200, ?string $error = null): void
    {
        $this->mockResponse = $response;
        $this->mockHttpCode = $httpCode;
        $this->mockError = $error;
    }

    /**
     * Override the token request to use mock instead of real curl.
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        return $this->mockRequestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ], 'exchange code');
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->mockRequestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ], 'refresh token');
    }

    private function mockRequestToken(array $params, string $action): array
    {
        // Get client credentials via reflection
        $reflection = new \ReflectionClass(OAuthService::class);
        $clientIdProp = $reflection->getProperty('clientId');
        $clientSecretProp = $reflection->getProperty('clientSecret');

        $params['client_id'] = $clientIdProp->getValue($this);
        $params['client_secret'] = $clientSecretProp->getValue($this);

        $this->lastRequestParams = $params;

        if ($this->mockError) {
            throw new \RuntimeException("Failed to {$action}: {$this->mockError}");
        }

        if ($this->mockHttpCode !== 200) {
            throw new \RuntimeException("Failed to {$action} (HTTP {$this->mockHttpCode}): {$this->mockResponse}");
        }

        $data = json_decode($this->mockResponse ?? '', true);
        if (!isset($data['access_token'])) {
            throw new \RuntimeException("Failed to {$action}: no access token in response");
        }

        return $data;
    }
}

class OAuthServiceTest extends TestCase
{
    private OAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OAuthService('test-client-id', 'test-client-secret');
    }

    public function testGetDefaultScopes(): void
    {
        $scopes = $this->service->getDefaultScopes();

        $this->assertIsArray($scopes);
        $this->assertContains(BuddyOAuth::SCOPE_WORKSPACE, $scopes);
        $this->assertContains(BuddyOAuth::SCOPE_EXECUTION_INFO, $scopes);
        $this->assertContains(BuddyOAuth::SCOPE_EXECUTION_RUN, $scopes);
        $this->assertContains(BuddyOAuth::SCOPE_USER_INFO, $scopes);
        $this->assertContains(OAuthService::SCOPE_VARIABLE_INFO, $scopes);
        $this->assertContains(OAuthService::SCOPE_VARIABLE_ADD, $scopes);
        $this->assertContains(OAuthService::SCOPE_VARIABLE_MANAGE, $scopes);
    }

    public function testGetAuthorizeUrlWithDefaultScopes(): void
    {
        $url = $this->service->getAuthorizeUrl('https://localhost/callback', 'test-state');

        $this->assertStringStartsWith('https://api.buddy.works/oauth2/authorize?', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode('https://localhost/callback'), $url);
        $this->assertStringContainsString('state=test-state', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('type=web_server', $url);
        // Check default scopes are included
        $this->assertStringContainsString('WORKSPACE', $url);
    }

    public function testGetAuthorizeUrlWithCustomScopes(): void
    {
        $customScopes = ['SCOPE_A', 'SCOPE_B'];
        $url = $this->service->getAuthorizeUrl('https://localhost/callback', 'test-state', $customScopes);

        $this->assertStringContainsString('scope=' . urlencode('SCOPE_A SCOPE_B'), $url);
        // Should not contain default scopes
        $this->assertStringNotContainsString('WORKSPACE', $url);
    }

    public function testGenerateStateReturnsHexString(): void
    {
        $state = $this->service->generateState();

        $this->assertIsString($state);
        $this->assertEquals(32, strlen($state)); // 16 bytes = 32 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $state);
    }

    public function testGenerateStateIsUnique(): void
    {
        $state1 = $this->service->generateState();
        $state2 = $this->service->generateState();

        $this->assertNotEquals($state1, $state2);
    }

    public function testScopeConstants(): void
    {
        $this->assertEquals('VARIABLE_INFO', OAuthService::SCOPE_VARIABLE_INFO);
        $this->assertEquals('VARIABLE_ADD', OAuthService::SCOPE_VARIABLE_ADD);
        $this->assertEquals('VARIABLE_MANAGE', OAuthService::SCOPE_VARIABLE_MANAGE);
    }

    public function testExchangeCodeForTokenSuccess(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse(json_encode([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]));

        $result = $testable->exchangeCodeForToken('auth-code-123', 'https://localhost/callback');

        $this->assertEquals('new-access-token', $result['access_token']);
        $this->assertEquals('new-refresh-token', $result['refresh_token']);
        $this->assertEquals(3600, $result['expires_in']);

        // Verify request params
        $this->assertEquals('authorization_code', $testable->lastRequestParams['grant_type']);
        $this->assertEquals('auth-code-123', $testable->lastRequestParams['code']);
        $this->assertEquals('https://localhost/callback', $testable->lastRequestParams['redirect_uri']);
        $this->assertEquals('test-client-id', $testable->lastRequestParams['client_id']);
        $this->assertEquals('test-client-secret', $testable->lastRequestParams['client_secret']);
    }

    public function testExchangeCodeForTokenHttpError(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse('{"error":"invalid_grant"}', 400);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to exchange code (HTTP 400)');

        $testable->exchangeCodeForToken('bad-code', 'https://localhost/callback');
    }

    public function testExchangeCodeForTokenNetworkError(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse('', 200, 'Connection refused');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to exchange code: Connection refused');

        $testable->exchangeCodeForToken('code', 'https://localhost/callback');
    }

    public function testExchangeCodeForTokenMissingAccessToken(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse(json_encode(['refresh_token' => 'token']));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no access token in response');

        $testable->exchangeCodeForToken('code', 'https://localhost/callback');
    }

    public function testRefreshTokenSuccess(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse(json_encode([
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]));

        $result = $testable->refreshToken('old-refresh-token');

        $this->assertEquals('refreshed-access-token', $result['access_token']);
        $this->assertEquals('new-refresh-token', $result['refresh_token']);

        // Verify request params
        $this->assertEquals('refresh_token', $testable->lastRequestParams['grant_type']);
        $this->assertEquals('old-refresh-token', $testable->lastRequestParams['refresh_token']);
        $this->assertEquals('test-client-id', $testable->lastRequestParams['client_id']);
        $this->assertEquals('test-client-secret', $testable->lastRequestParams['client_secret']);
    }

    public function testRefreshTokenHttpError(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse('{"error":"invalid_grant"}', 401);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to refresh token (HTTP 401)');

        $testable->refreshToken('expired-refresh-token');
    }

    public function testRefreshTokenNetworkError(): void
    {
        $testable = new TestableOAuthService('test-client-id', 'test-client-secret');
        $testable->setMockResponse('', 200, 'SSL certificate problem');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to refresh token: SSL certificate problem');

        $testable->refreshToken('refresh-token');
    }
}
