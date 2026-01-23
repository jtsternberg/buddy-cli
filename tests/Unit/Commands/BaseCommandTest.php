<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit\Commands;

use BuddyCli\Application;
use BuddyCli\Commands\BaseCommand;
use BuddyCli\Services\ConfigService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Concrete implementation for testing the abstract BaseCommand.
 */
class TestableCommand extends BaseCommand
{
    protected static $defaultName = 'test:command';

    protected function configure(): void
    {
        parent::configure();
        $this->addWorkspaceOption();
        $this->addProjectOption();
        $this->addPipelineOption();
    }

    // Expose protected methods for testing
    public function exposeFormatStatus(string $status): string
    {
        return $this->formatStatus($status);
    }

    public function exposeFormatTime(?string $datetime): string
    {
        return $this->formatTime($datetime);
    }

    public function exposeFormatDuration(?string $start, ?string $finish): string
    {
        return $this->formatDuration($start, $finish);
    }

    public function exposeRequireWorkspace(ArrayInput $input): string
    {
        return $this->requireWorkspace($input);
    }

    public function exposeRequireProject(ArrayInput $input): string
    {
        return $this->requireProject($input);
    }

    public function exposeRequirePipeline(ArrayInput $input): int
    {
        return $this->requirePipeline($input);
    }

    public function exposeIsJsonOutput(ArrayInput $input): bool
    {
        return $this->isJsonOutput($input);
    }
}

class BaseCommandTest extends TestCase
{
    private TestableCommand $command;
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
        $this->unsetEnv('BUDDY_TOKEN');

        $configService = new ConfigService();
        $this->app = $this->createMock(Application::class);
        $this->app->method('getConfigService')->willReturn($configService);

        $this->command = new TestableCommand($this->app);
    }

    private function createInput(array $options = []): ArrayInput
    {
        $definition = new InputDefinition([
            new InputOption('json', null, InputOption::VALUE_NONE),
            new InputOption('workspace', 'w', InputOption::VALUE_REQUIRED),
            new InputOption('project', 'p', InputOption::VALUE_REQUIRED),
            new InputOption('pipeline', null, InputOption::VALUE_REQUIRED),
        ]);
        return new ArrayInput($options, $definition);
    }

    // formatStatus tests
    public function testFormatStatusSuccessful(): void
    {
        $result = $this->command->exposeFormatStatus('SUCCESSFUL');
        $this->assertSame('<fg=green>SUCCESSFUL</>', $result);
    }

    public function testFormatStatusFailed(): void
    {
        $result = $this->command->exposeFormatStatus('FAILED');
        $this->assertSame('<fg=red>FAILED</>', $result);
    }

    public function testFormatStatusInProgress(): void
    {
        $result = $this->command->exposeFormatStatus('INPROGRESS');
        $this->assertSame('<fg=yellow>INPROGRESS</>', $result);
    }

    public function testFormatStatusEnqueued(): void
    {
        $result = $this->command->exposeFormatStatus('ENQUEUED');
        $this->assertSame('<fg=cyan>ENQUEUED</>', $result);
    }

    public function testFormatStatusTerminated(): void
    {
        $result = $this->command->exposeFormatStatus('TERMINATED');
        $this->assertSame('<fg=red>TERMINATED</>', $result);
    }

    public function testFormatStatusSkipped(): void
    {
        $result = $this->command->exposeFormatStatus('SKIPPED');
        $this->assertSame('<fg=gray>SKIPPED</>', $result);
    }

    public function testFormatStatusUnknown(): void
    {
        $result = $this->command->exposeFormatStatus('UNKNOWN');
        $this->assertSame('UNKNOWN', $result);
    }

    // formatTime tests
    public function testFormatTimeNull(): void
    {
        $result = $this->command->exposeFormatTime(null);
        $this->assertSame('-', $result);
    }

    public function testFormatTimeJustNow(): void
    {
        $now = new \DateTimeImmutable();
        $result = $this->command->exposeFormatTime($now->format('Y-m-d H:i:s'));
        $this->assertSame('just now', $result);
    }

    public function testFormatTimeMinutesAgo(): void
    {
        $past = (new \DateTimeImmutable())->modify('-5 minutes');
        $result = $this->command->exposeFormatTime($past->format('Y-m-d H:i:s'));
        $this->assertSame('5 min ago', $result);
    }

    public function testFormatTimeHoursAgo(): void
    {
        $past = (new \DateTimeImmutable())->modify('-3 hours');
        $result = $this->command->exposeFormatTime($past->format('Y-m-d H:i:s'));
        $this->assertSame('3 hours ago', $result);
    }

    public function testFormatTimeOneHourAgo(): void
    {
        $past = (new \DateTimeImmutable())->modify('-1 hour');
        $result = $this->command->exposeFormatTime($past->format('Y-m-d H:i:s'));
        $this->assertSame('1 hour ago', $result);
    }

    public function testFormatTimeYesterday(): void
    {
        $past = (new \DateTimeImmutable())->modify('-1 day');
        $result = $this->command->exposeFormatTime($past->format('Y-m-d H:i:s'));
        $this->assertSame('yesterday', $result);
    }

    public function testFormatTimeDaysAgo(): void
    {
        $past = (new \DateTimeImmutable())->modify('-3 days');
        $result = $this->command->exposeFormatTime($past->format('Y-m-d H:i:s'));
        $this->assertSame('3 days ago', $result);
    }

    public function testFormatTimeOlderThanWeek(): void
    {
        $past = (new \DateTimeImmutable('2024-01-15 10:30:00'));
        $result = $this->command->exposeFormatTime($past->format('Y-m-d H:i:s'));
        $this->assertSame('2024-01-15 10:30', $result);
    }

    // formatDuration tests
    public function testFormatDurationNull(): void
    {
        $result = $this->command->exposeFormatDuration(null, null);
        $this->assertSame('-', $result);
    }

    public function testFormatDurationSeconds(): void
    {
        $start = '2024-01-15 10:00:00';
        $finish = '2024-01-15 10:00:45';
        $result = $this->command->exposeFormatDuration($start, $finish);
        $this->assertSame('45s', $result);
    }

    public function testFormatDurationMinutes(): void
    {
        $start = '2024-01-15 10:00:00';
        $finish = '2024-01-15 10:05:30';
        $result = $this->command->exposeFormatDuration($start, $finish);
        $this->assertSame('5m 30s', $result);
    }

    public function testFormatDurationHours(): void
    {
        $start = '2024-01-15 10:00:00';
        $finish = '2024-01-15 12:15:00';
        $result = $this->command->exposeFormatDuration($start, $finish);
        $this->assertSame('2h 15m', $result);
    }

    // requireWorkspace tests
    public function testRequireWorkspaceFromOption(): void
    {
        $input = $this->createInput(['--workspace' => 'my-ws']);
        $result = $this->command->exposeRequireWorkspace($input);
        $this->assertSame('my-ws', $result);
    }

    public function testRequireWorkspaceThrowsWhenMissing(): void
    {
        $input = $this->createInput([]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No workspace specified');
        $this->command->exposeRequireWorkspace($input);
    }

    // requireProject tests
    public function testRequireProjectFromOption(): void
    {
        $input = $this->createInput(['--project' => 'my-project']);
        $result = $this->command->exposeRequireProject($input);
        $this->assertSame('my-project', $result);
    }

    public function testRequireProjectThrowsWhenMissing(): void
    {
        $input = $this->createInput([]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');
        $this->command->exposeRequireProject($input);
    }

    // requirePipeline tests
    public function testRequirePipelineFromOption(): void
    {
        $input = $this->createInput(['--pipeline' => '123']);
        $result = $this->command->exposeRequirePipeline($input);
        $this->assertSame(123, $result);
    }

    public function testRequirePipelineThrowsWhenMissing(): void
    {
        $input = $this->createInput([]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline ID is required');
        $this->command->exposeRequirePipeline($input);
    }

    // isJsonOutput tests
    public function testIsJsonOutputTrue(): void
    {
        $input = $this->createInput(['--json' => true]);
        $this->assertTrue($this->command->exposeIsJsonOutput($input));
    }

    public function testIsJsonOutputFalse(): void
    {
        $input = $this->createInput([]);
        $this->assertFalse($this->command->exposeIsJsonOutput($input));
    }
}
