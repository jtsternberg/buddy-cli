<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Services;

use BuddyCli\Services\EnvLoader;
use BuddyCli\Tests\TestCase;

class EnvLoaderTest extends TestCase
{
    private static array $envKeys = ['BUDDY_TOKEN', 'BUDDY_WORKSPACE', 'BUDDY_PROJECT'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->clearAllEnvVars();
        EnvLoader::reset();
    }

    protected function tearDown(): void
    {
        EnvLoader::reset();
        $this->clearAllEnvVars();
        parent::tearDown();
    }

    private function clearAllEnvVars(): void
    {
        foreach (self::$envKeys as $key) {
            putenv($key); // Unset from getenv
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    public function testLoadsSingleEnvFile(): void
    {
        $this->writeTempFile('.env', 'BUDDY_TOKEN=test-token');

        EnvLoader::loadRecursive($this->tempDir);

        $this->assertSame('test-token', $_ENV['BUDDY_TOKEN'] ?? null);
    }

    public function testTracksSourceFile(): void
    {
        $this->writeTempFile('.env', 'BUDDY_TOKEN=test-token');

        EnvLoader::loadRecursive($this->tempDir);

        $expectedPath = $this->tempDir . '/.env';
        $this->assertSame($expectedPath, EnvLoader::getSource('BUDDY_TOKEN'));
    }

    public function testLoadsFromParentDirectory(): void
    {
        // Create parent .env
        $this->writeTempFile('.env', 'BUDDY_WORKSPACE=parent-workspace');

        // Create child directory
        $childDir = $this->tempDir . '/child';
        mkdir($childDir);

        EnvLoader::loadRecursive($childDir);

        $this->assertSame('parent-workspace', $_ENV['BUDDY_WORKSPACE'] ?? null);
    }

    public function testChildValuesTakePrecedence(): void
    {
        // Create parent .env
        $this->writeTempFile('.env', 'BUDDY_WORKSPACE=parent-workspace');

        // Create child .env with same key
        $this->writeTempFile('child/.env', 'BUDDY_WORKSPACE=child-workspace');

        $childDir = $this->tempDir . '/child';
        EnvLoader::loadRecursive($childDir);

        $this->assertSame('child-workspace', $_ENV['BUDDY_WORKSPACE'] ?? null);
    }

    public function testMergesValuesFromMultipleLevels(): void
    {
        // Parent has token and workspace
        $this->writeTempFile('.env', "BUDDY_TOKEN=parent-token\nBUDDY_WORKSPACE=parent-workspace");

        // Child only has project
        $this->writeTempFile('child/.env', 'BUDDY_PROJECT=child-project');

        $childDir = $this->tempDir . '/child';
        EnvLoader::loadRecursive($childDir);

        $this->assertSame('parent-token', $_ENV['BUDDY_TOKEN'] ?? null);
        $this->assertSame('parent-workspace', $_ENV['BUDDY_WORKSPACE'] ?? null);
        $this->assertSame('child-project', $_ENV['BUDDY_PROJECT'] ?? null);
    }

    public function testTracksCorrectSourceForMergedValues(): void
    {
        // Parent has token
        $this->writeTempFile('.env', 'BUDDY_TOKEN=parent-token');

        // Child has project
        $this->writeTempFile('child/.env', 'BUDDY_PROJECT=child-project');

        $childDir = $this->tempDir . '/child';
        EnvLoader::loadRecursive($childDir);

        $this->assertSame($this->tempDir . '/.env', EnvLoader::getSource('BUDDY_TOKEN'));
        $this->assertSame($childDir . '/.env', EnvLoader::getSource('BUDDY_PROJECT'));
    }

    public function testChildSourceTrackedWhenOverridingParent(): void
    {
        // Parent has workspace
        $this->writeTempFile('.env', 'BUDDY_WORKSPACE=parent-workspace');

        // Child overrides workspace
        $this->writeTempFile('child/.env', 'BUDDY_WORKSPACE=child-workspace');

        $childDir = $this->tempDir . '/child';
        EnvLoader::loadRecursive($childDir);

        // Source should be the child file since it took precedence
        $this->assertSame($childDir . '/.env', EnvLoader::getSource('BUDDY_WORKSPACE'));
    }

    public function testIgnoresCommentsInEnvFile(): void
    {
        $this->writeTempFile('.env', "# This is a comment\nBUDDY_TOKEN=test-token\n# Another comment");

        EnvLoader::loadRecursive($this->tempDir);

        $this->assertSame('test-token', $_ENV['BUDDY_TOKEN'] ?? null);
        $this->assertNull(EnvLoader::getSource('#'));
    }

    public function testIgnoresEmptyLines(): void
    {
        $this->writeTempFile('.env', "\n\nBUDDY_TOKEN=test-token\n\n");

        EnvLoader::loadRecursive($this->tempDir);

        $this->assertSame('test-token', $_ENV['BUDDY_TOKEN'] ?? null);
    }

    public function testGetSourcesReturnsAllTrackedSources(): void
    {
        $this->writeTempFile('.env', "BUDDY_TOKEN=token\nBUDDY_WORKSPACE=workspace");

        EnvLoader::loadRecursive($this->tempDir);

        $sources = EnvLoader::getSources();
        $this->assertArrayHasKey('BUDDY_TOKEN', $sources);
        $this->assertArrayHasKey('BUDDY_WORKSPACE', $sources);
    }

    public function testResetClearsSources(): void
    {
        $this->writeTempFile('.env', 'BUDDY_TOKEN=test-token');

        EnvLoader::loadRecursive($this->tempDir);
        $this->assertNotNull(EnvLoader::getSource('BUDDY_TOKEN'));

        EnvLoader::reset();
        $this->assertNull(EnvLoader::getSource('BUDDY_TOKEN'));
    }
}
