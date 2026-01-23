<?php

declare(strict_types=1);

namespace BuddyCli\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private array $originalEnv = [];
    protected ?string $tempDir = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreEnv();
        $this->cleanupTempDir();
        parent::tearDown();
    }

    /**
     * Set an environment variable and track it for restoration.
     */
    protected function setEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->originalEnv)) {
            $this->originalEnv[$key] = getenv($key);
        }
        putenv("$key=$value");
    }

    /**
     * Unset an environment variable and track it for restoration.
     */
    protected function unsetEnv(string $key): void
    {
        if (!array_key_exists($key, $this->originalEnv)) {
            $this->originalEnv[$key] = getenv($key);
        }
        putenv($key);
    }

    /**
     * Restore all modified environment variables.
     */
    private function restoreEnv(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("$key=$value");
            }
        }
        $this->originalEnv = [];
    }

    /**
     * Create a temporary directory for test files.
     */
    protected function createTempDir(): string
    {
        $this->tempDir = sys_get_temp_dir() . '/buddy-cli-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        return $this->tempDir;
    }

    /**
     * Clean up the temporary directory.
     */
    private function cleanupTempDir(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
            $this->tempDir = null;
        }
    }

    private function recursiveDelete(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Write a file in the temp directory.
     */
    protected function writeTempFile(string $relativePath, string $content): string
    {
        if ($this->tempDir === null) {
            $this->createTempDir();
        }

        $fullPath = $this->tempDir . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
        return $fullPath;
    }
}
