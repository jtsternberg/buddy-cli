<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Services;

use BuddyCli\Services\ConfigService;
use BuddyCli\Tests\TestCase;

class ConfigServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        // Ensure no env vars interfere with tests
        $this->unsetEnv('BUDDY_TOKEN');
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
        $this->unsetEnv('BUDDY_CLIENT_ID');
        $this->unsetEnv('BUDDY_CLIENT_SECRET');
    }

    public function testGetReturnsDefaultWhenKeyNotSet(): void
    {
        $config = new ConfigService();
        $this->assertNull($config->get('nonexistent'));
        $this->assertSame('default', $config->get('nonexistent', 'default'));
    }

    public function testSetAndGet(): void
    {
        $config = new ConfigService();
        $config->set('token', 'my-token');

        $this->assertSame('my-token', $config->get('token'));
    }

    public function testSetPersistsToFile(): void
    {
        $config = new ConfigService();
        $config->set('workspace', 'my-workspace');

        // Create new instance to verify persistence
        $config2 = new ConfigService();
        $this->assertSame('my-workspace', $config2->get('workspace'));
    }

    public function testRemove(): void
    {
        $config = new ConfigService();
        $config->set('token', 'to-remove');
        $config->remove('token');

        $this->assertNull($config->get('token'));
    }

    public function testClear(): void
    {
        $config = new ConfigService();
        $config->set('token', 'value1');
        $config->set('workspace', 'value2');
        $config->clear();

        $this->assertNull($config->get('token'));
        $this->assertNull($config->get('workspace'));
        $this->assertEmpty($config->all());
    }

    public function testEnvVarTakesPrecedence(): void
    {
        $config = new ConfigService();
        $config->set('token', 'file-token');

        $this->setEnv('BUDDY_TOKEN', 'env-token');

        $this->assertSame('env-token', $config->get('token'));
    }

    public function testAllIncludesEnvVars(): void
    {
        $config = new ConfigService();
        $config->set('workspace', 'file-workspace');

        $this->setEnv('BUDDY_TOKEN', 'env-token');

        $all = $config->all();
        $this->assertSame('env-token', $all['token']);
        $this->assertSame('file-workspace', $all['workspace']);
    }

    public function testGetConfigFilePath(): void
    {
        $config = new ConfigService();
        $expected = $this->tempDir . '/.config/buddy-cli/config.json';
        $this->assertSame($expected, $config->getConfigFilePath());
    }

    public function testConfigDirectoryIsCreated(): void
    {
        $config = new ConfigService();
        $config->set('token', 'test');

        $expectedDir = $this->tempDir . '/.config/buddy-cli';
        $this->assertDirectoryExists($expectedDir);
    }

    public function testLoadsExistingConfigFile(): void
    {
        // Create config file before instantiating service
        $configDir = $this->tempDir . '/.config/buddy-cli';
        mkdir($configDir, 0755, true);
        file_put_contents(
            $configDir . '/config.json',
            json_encode(['token' => 'preexisting', 'workspace' => 'myws'])
        );

        $config = new ConfigService();

        $this->assertSame('preexisting', $config->get('token'));
        $this->assertSame('myws', $config->get('workspace'));
    }

    public function testInvalidJsonIsIgnored(): void
    {
        $configDir = $this->tempDir . '/.config/buddy-cli';
        mkdir($configDir, 0755, true);
        file_put_contents($configDir . '/config.json', 'not valid json');

        $config = new ConfigService();

        $this->assertNull($config->get('token'));
    }

    public function testAllWithSourcesReturnsConfigSource(): void
    {
        $config = new ConfigService();
        $config->set('workspace', 'file-workspace');

        $result = $config->allWithSources();

        $this->assertSame('file-workspace', $result['workspace']['value']);
        $this->assertSame('config', $result['workspace']['source']);
    }

    public function testAllWithSourcesReturnsEnvSource(): void
    {
        $this->setEnv('BUDDY_TOKEN', 'env-token');

        $config = new ConfigService();
        $result = $config->allWithSources();

        $this->assertSame('env-token', $result['token']['value']);
        $this->assertSame('env', $result['token']['source']);
    }

    public function testAllWithSourcesEnvOverridesConfig(): void
    {
        $config = new ConfigService();
        $config->set('workspace', 'file-workspace');

        $this->setEnv('BUDDY_WORKSPACE', 'env-workspace');

        $result = $config->allWithSources();

        $this->assertSame('env-workspace', $result['workspace']['value']);
        $this->assertSame('env', $result['workspace']['source']);
    }
}
