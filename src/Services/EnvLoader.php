<?php

declare(strict_types=1);

namespace BuddyCli\Services;

use Dotenv\Dotenv;

class EnvLoader
{
    /** @var array<string, string> Map of env key to source file path */
    private static array $sources = [];

    /**
     * Load .env files recursively from given directory up to filesystem root.
     * Child directories take precedence over parent directories.
     */
    public static function loadRecursive(string $startDir): void
    {
        $dir = $startDir;
        while (true) {
            $envFile = $dir . '/.env';
            if (file_exists($envFile)) {
                self::loadFile($envFile);
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break; // Reached filesystem root
            }
            $dir = $parent;
        }
    }

    /**
     * Load a single .env file, tracking which keys came from it.
     */
    private static function loadFile(string $path): void
    {
        $dir = dirname($path);
        $dotenv = Dotenv::createImmutable($dir);

        // Parse file to get keys before loading
        $content = file_get_contents($path);
        if ($content === false) {
            return;
        }

        // Track which keys will be set from this file
        // Only track if key isn't already set (child takes precedence)
        foreach (self::parseEnvKeys($content) as $key) {
            if (!isset(self::$sources[$key]) && !self::envKeyExists($key)) {
                self::$sources[$key] = $path;
            }
        }

        $dotenv->safeLoad();
    }

    /**
     * Parse .env content to extract variable names.
     *
     * @return string[]
     */
    private static function parseEnvKeys(string $content): array
    {
        $keys = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            // Extract key from KEY=value or export KEY=value
            if (preg_match('/^(?:export\s+)?([A-Z_][A-Z0-9_]*)\s*=/i', $line, $matches)) {
                $keys[] = $matches[1];
            }
        }
        return $keys;
    }

    /**
     * Check if an env key already exists.
     */
    private static function envKeyExists(string $key): bool
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return true;
        }
        return isset($_ENV[$key]) && $_ENV[$key] !== '';
    }

    /**
     * Get the source file path for an env key.
     */
    public static function getSource(string $key): ?string
    {
        return self::$sources[$key] ?? null;
    }

    /**
     * Get all tracked sources.
     *
     * @return array<string, string>
     */
    public static function getSources(): array
    {
        return self::$sources;
    }

    /**
     * Clear tracked sources (useful for testing).
     */
    public static function reset(): void
    {
        self::$sources = [];
    }
}
