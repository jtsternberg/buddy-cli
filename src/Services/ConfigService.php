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
            $envValue = getenv($envKey);
            if ($envValue !== false && $envValue !== '') {
                return $envValue;
            }
        }

        return $this->config[$key] ?? $default;
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
            $envValue = getenv($envKey);
            if ($envValue !== false && $envValue !== '') {
                $result[$key] = $envValue;
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

        return $home . '/.buddy-cli.json';
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

        $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            file_put_contents($this->userConfigPath, $json . "\n");
        }
    }
}
