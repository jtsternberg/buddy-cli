<?php

declare(strict_types=1);

namespace BuddyCli\Services;

class ConfigService
{
    private array $config = [];
    private ?string $userConfigPath = null;
    private ?string $projectConfigPath = null;

    private const ENV_MAP = [
        'token' => 'BUDDY_TOKEN',
        'workspace' => 'BUDDY_WORKSPACE',
        'project' => 'BUDDY_PROJECT',
        'client_id' => 'BUDDY_CLIENT_ID',
        'client_secret' => 'BUDDY_CLIENT_SECRET',
    ];

    public function __construct()
    {
        $this->userConfigPath = $this->getUserConfigPath();
        $this->projectConfigPath = $this->getProjectConfigPath();
        $this->loadConfig();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        // Environment variables take precedence
        $envKey = self::ENV_MAP[$key] ?? null;
        if ($envKey !== null) {
            $envValue = $this->getEnv($envKey);
            if ($envValue !== null) {
                return $envValue;
            }
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * Get environment variable from getenv() or $_ENV.
     */
    private function getEnv(string $key): ?string
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        return null;
    }

    public function set(string $key, string $value): void
    {
        $this->config[$key] = $value;
        $this->saveConfig();
    }

    public function remove(string $key): void
    {
        unset($this->config[$key]);
        $this->saveConfig();
    }

    public function clear(): void
    {
        $this->config = [];
        $this->saveConfig();
    }

    public function all(): array
    {
        $result = $this->config;

        // Overlay environment variables
        foreach (self::ENV_MAP as $key => $envKey) {
            $envValue = $this->getEnv($envKey);
            if ($envValue !== null) {
                $result[$key] = $envValue;
            }
        }

        return $result;
    }

    /**
     * Get all config values with their sources.
     *
     * @return array<string, array{value: string, source: string, path?: string}>
     */
    public function allWithSources(): array
    {
        $result = [];

        // Add config file values
        foreach ($this->config as $key => $value) {
            $result[$key] = ['value' => $value, 'source' => 'config'];
        }

        // Overlay environment variables
        foreach (self::ENV_MAP as $key => $envKey) {
            $envValue = $this->getEnv($envKey);
            if ($envValue !== null) {
                $entry = ['value' => $envValue, 'source' => 'env'];
                $envPath = EnvLoader::getSource($envKey);
                if ($envPath !== null) {
                    $entry['path'] = $envPath;
                }
                $result[$key] = $entry;
            }
        }

        return $result;
    }

    public function getConfigFilePath(): ?string
    {
        return $this->userConfigPath;
    }

    private function getUserConfigPath(): ?string
    {
        $home = getenv('HOME');
        if ($home === false) {
            return null;
        }

        return $home . '/.config/buddy-cli/config.json';
    }

    private function getProjectConfigPath(): ?string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return null;
        }

        $path = $cwd . '/.buddy-cli.json';
        return file_exists($path) ? $path : null;
    }

    private function loadConfig(): void
    {
        // Load user config first (lower priority)
        if ($this->userConfigPath !== null && file_exists($this->userConfigPath)) {
            $content = file_get_contents($this->userConfigPath);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->config = $data;
                }
            }
        }

        // Overlay project config (higher priority)
        if ($this->projectConfigPath !== null) {
            $content = file_get_contents($this->projectConfigPath);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $this->config = array_merge($this->config, $data);
                }
            }
        }
    }

    private function saveConfig(): void
    {
        if ($this->userConfigPath === null) {
            return;
        }

        $dir = dirname($this->userConfigPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            file_put_contents($this->userConfigPath, $json . "\n");
        }
    }
}
