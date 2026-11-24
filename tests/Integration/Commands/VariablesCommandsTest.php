<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class VariablesCommandsTest extends TestCase
{
    private Application $app;
    private BuddyService $mockBuddyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_TOKEN');
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
        $this->setEnv('BUDDY_TOKEN', 'fake-token');

        $this->app = new Application();
        $this->mockBuddyService = $this->createMock(BuddyService::class);
        $this->injectMockBuddyService();
    }

    private function injectMockBuddyService(): void
    {
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('buddyService');
        $property->setValue($this->app, $this->mockBuddyService);
    }

    // vars:set scope validation tests

    public function testVarsSetRejectsMultipleScopes(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'my-project',
            '--pipeline' => '123',
            'key' => 'TEST_KEY',
            'value' => 'test-value',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Only one scope allowed', $output);
        $this->assertStringContainsString('--project OR --pipeline', $output);
    }

    public function testVarsSetActionRequiresPipeline(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--action' => '456',
            'key' => 'TEST_KEY',
            'value' => 'test-value',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('--action requires --pipeline', $output);
    }

    public function testVarsSetRejectsProjectWithAction(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'my-project',
            '--pipeline' => '123',
            '--action' => '456',
            'key' => 'TEST_KEY',
            'value' => 'test-value',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Only one scope allowed', $output);
    }

    public function testVarsSetWithPipelineScopeCreatesVariable(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 42, 'key' => 'NODE_OPTIONS'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--pipeline' => '123',
            'key' => 'NODE_OPTIONS',
            'value' => '--max-old-space-size=4096',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created variable: NODE_OPTIONS', $output);
        $this->assertStringContainsString('ID: 42', $output);

        // Pipeline scope emits exactly one scope object: pipeline.
        $this->assertSame(['id' => 123], $captured['pipeline'] ?? null);
        $this->assertArrayNotHasKey('action', $captured);
        $this->assertArrayNotHasKey('project', $captured);
    }

    public function testVarsSetWithActionScopeCreatesVariable(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 43, 'key' => 'DEBUG'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--pipeline' => '123',
            '--action' => '456',
            'key' => 'DEBUG',
            'value' => 'true',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Created variable: DEBUG', $output);
        $this->assertStringContainsString('ID: 43', $output);

        // Action scope must emit ONLY the action scope object. Buddy's API rejects
        // a payload carrying both `pipeline` and `action` ("Only one scope is allowed").
        // --pipeline is routing/context for resolving the action, not a second scope.
        $this->assertSame(['id' => 456], $captured['action'] ?? null);
        $this->assertArrayNotHasKey('pipeline', $captured);
        $this->assertArrayNotHasKey('project', $captured);
    }

    public function testVarsSetWithProjectScopeEmitsOnlyProjectScope(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 45, 'key' => 'NODE_ENV'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--project' => 'my-project',
            'key' => 'NODE_ENV',
            'value' => 'production',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Created variable: NODE_ENV', $tester->getDisplay());

        $this->assertSame(['name' => 'my-project'], $captured['project'] ?? null);
        $this->assertArrayNotHasKey('pipeline', $captured);
        $this->assertArrayNotHasKey('action', $captured);
    }

    public function testVarsSetWorkspaceScopeWhenNoScopeGiven(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $this->mockBuddyService->method('createVariable')
            ->willReturn(['id' => 44, 'key' => 'GLOBAL_VAR']);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'GLOBAL_VAR',
            'value' => 'hello',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Created variable: GLOBAL_VAR', $tester->getDisplay());
    }

    public function testVarsSetUpdatesExistingVariable(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => [
                ['id' => 99, 'key' => 'EXISTING_KEY'],
            ]]);

        $this->mockBuddyService->method('updateVariable')
            ->willReturn(['id' => 99, 'key' => 'EXISTING_KEY']);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'EXISTING_KEY',
            'value' => 'new-value',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Updated variable: EXISTING_KEY', $tester->getDisplay());
    }

    public function testVarsSetReadsValueFromStdinViaDashPositional(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 60, 'key' => 'API_KEY'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        // setInputs writes each entry followed by PHP_EOL, so the stream carries a
        // trailing newline we expect the command to strip.
        $tester->setInputs(['secret123']);
        $tester->execute([
            '--workspace' => 'ws',
            '--encrypted' => true,
            'key' => 'API_KEY',
            'value' => '-',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Created variable: API_KEY', $tester->getDisplay());
        // The secret came from stdin (never argv) with its trailing newline stripped.
        $this->assertSame('secret123', $captured['value'] ?? null);
        $this->assertTrue($captured['encrypted'] ?? false);
    }

    public function testVarsSetReadsValueFromStdinViaValueFileDash(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 61, 'key' => 'TOKEN'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->setInputs(['piped-secret']);
        $tester->execute([
            '--workspace' => 'ws',
            '--value-file' => '-',
            'key' => 'TOKEN',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('piped-secret', $captured['value'] ?? null);
    }

    public function testVarsSetReadsValueFromFile(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 62, 'key' => 'FILE_KEY'];
            });

        $secretFile = $this->tempDir . '/secret.txt';
        // Trailing newline (as most editors add) must be stripped.
        file_put_contents($secretFile, "file-secret\n");

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--value-file' => $secretFile,
            'key' => 'FILE_KEY',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('file-secret', $captured['value'] ?? null);
    }

    public function testVarsSetSshKeyFromFileKeepsTrailingNewline(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 63, 'key' => 'DEPLOY_KEY'];
            });

        $keyFile = $this->tempDir . '/id_deploy';
        $key = "-----BEGIN OPENSSH PRIVATE KEY-----\nnot-a-real-key\n-----END OPENSSH PRIVATE KEY-----\n";
        file_put_contents($keyFile, $key);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--type' => 'SSH_KEY',
            '--file-place' => 'NONE',
            '--value-file' => $keyFile,
            'key' => 'DEPLOY_KEY',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame($key, $captured['value'] ?? null);
    }

    public function testVarsSetSshPublicKeyFromStdinKeepsTrailingNewline(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 64, 'key' => 'DEPLOY_PUB'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        // setInputs appends PHP_EOL, which must survive for key types.
        $tester->setInputs(['ssh-ed25519 AAAA not-a-real-key']);
        $tester->execute([
            '--workspace' => 'ws',
            '--type' => 'SSH_PUBLIC_KEY',
            '--file-place' => 'NONE',
            '--value-file' => '-',
            'key' => 'DEPLOY_PUB',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('ssh-ed25519 AAAA not-a-real-key' . PHP_EOL, $captured['value'] ?? null);
    }

    public function testVarsSetRejectsBothValueAndValueFile(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--value-file' => '/some/path',
            'key' => 'CONFLICT',
            'value' => 'literal',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Cannot use both', $tester->getDisplay());
    }

    public function testVarsSetRequiresAValueSource(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'NO_VALUE',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No value provided', $tester->getDisplay());
    }

    public function testVarsSetErrorsWhenValueFileMissing(): void
    {
        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--value-file' => $this->tempDir . '/does-not-exist.txt',
            'key' => 'MISSING_FILE',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Could not read value file', $tester->getDisplay());
    }

    public function testVarsSetJsonOutput(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $result = ['id' => 50, 'key' => 'MY_KEY', 'value' => 'my_value'];
        $this->mockBuddyService->method('createVariable')
            ->willReturn($result);

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            'key' => 'MY_KEY',
            'value' => 'my_value',
            '--json' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertSame(50, $data['id']);
        $this->assertSame('MY_KEY', $data['key']);
    }

    // vars:set file fields (SSH_KEY and other file-backed types)

    public function testVarsSetSshKeyRequiresFilePlace(): void
    {
        $this->mockBuddyService->expects($this->never())->method('createVariable');
        $this->mockBuddyService->expects($this->never())->method('updateVariable');

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--type' => 'SSH_KEY',
            'key' => 'DEPLOY_KEY',
            'value' => 'not-a-real-key',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('--file-place', $tester->getDisplay());
    }

    public function testVarsSetSshKeySendsFileFields(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 77, 'key' => 'DEPLOY_KEY'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--pipeline' => '123',
            '--type' => 'SSH_KEY',
            '--file-place' => 'container',
            '--file-path' => '~/.ssh/id_deploy',
            '--file-chmod' => '600',
            'key' => 'DEPLOY_KEY',
            'value' => 'not-a-real-key',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('SSH_KEY', $captured['type']);
        $this->assertSame('CONTAINER', $captured['file_place']);
        $this->assertSame('~/.ssh/id_deploy', $captured['file_path']);
        $this->assertSame('600', $captured['file_chmod']);
    }

    public function testVarsSetFilePlaceNoneOmitsPathAndChmod(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 78, 'key' => 'DEPLOY_KEY'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--type' => 'SSH_KEY',
            '--file-place' => 'NONE',
            'key' => 'DEPLOY_KEY',
            'value' => 'not-a-real-key',
        ]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame('NONE', $captured['file_place']);
        $this->assertArrayNotHasKey('file_path', $captured);
        $this->assertArrayNotHasKey('file_chmod', $captured);
    }

    public function testVarsSetFilePlaceContainerRequiresPathAndChmod(): void
    {
        $this->mockBuddyService->expects($this->never())->method('createVariable');

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--type' => 'SSH_KEY',
            '--file-place' => 'CONTAINER',
            'key' => 'DEPLOY_KEY',
            'value' => 'not-a-real-key',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('--file-path', $output);
        $this->assertStringContainsString('--file-chmod', $output);
    }

    public function testVarsSetRejectsUnknownFilePlace(): void
    {
        $this->mockBuddyService->expects($this->never())->method('createVariable');

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute([
            '--workspace' => 'ws',
            '--type' => 'SSH_KEY',
            '--file-place' => 'DISK',
            'key' => 'DEPLOY_KEY',
            'value' => 'not-a-real-key',
        ]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('CONTAINER or NONE', $tester->getDisplay());
    }

    public function testVarsSetPlainVarSendsNoFileFields(): void
    {
        $this->mockBuddyService->method('getVariables')
            ->willReturn(['variables' => []]);

        $captured = null;
        $this->mockBuddyService->method('createVariable')
            ->willReturnCallback(function (string $workspace, array $data) use (&$captured) {
                $captured = $data;
                return ['id' => 79, 'key' => 'PLAIN'];
            });

        $command = $this->app->find('vars:set');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', 'key' => 'PLAIN', 'value' => 'x']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertArrayNotHasKey('file_place', $captured);
        $this->assertArrayNotHasKey('file_path', $captured);
        $this->assertArrayNotHasKey('file_chmod', $captured);
    }

    // vars:show SSH key public half

    public function testVarsShowDisplaysSshKeyPublicValueAndFingerprint(): void
    {
        $this->mockBuddyService->method('getVariable')->willReturn([
            'id' => 77,
            'key' => 'DEPLOY_KEY',
            'type' => 'SSH_KEY',
            'encrypted' => true,
            'value' => '!encrypted xyz',
            'file_place' => 'CONTAINER',
            'file_path' => '~/.ssh/id_deploy',
            'file_chmod' => '600',
            'public_value' => 'ssh-ed25519 AAAAfakepublic buddy',
            'key_fingerprint' => 'SHA256:fakefingerprint',
        ]);

        $command = $this->app->find('vars:show');
        $tester = new CommandTester($command);
        $tester->execute(['--workspace' => 'ws', 'variable-id' => '77']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('ssh-ed25519 AAAAfakepublic buddy', $output);
        $this->assertStringContainsString('SHA256:fakefingerprint', $output);
        $this->assertStringContainsString('~/.ssh/id_deploy', $output);
        $this->assertStringNotContainsString('!encrypted', $output);
    }
}
