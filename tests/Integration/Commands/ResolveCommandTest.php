<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Integration\Commands;

use BuddyCli\Application;
use BuddyCli\Services\BuddyService;
use BuddyCli\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ResolveCommandTest extends TestCase
{
    private Application $app;
    private BuddyService $mockBuddyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
        $this->unsetEnv('BUDDY_WORKSPACE');
        $this->unsetEnv('BUDDY_PROJECT');
        $this->setEnv('BUDDY_TOKEN', 'fake-token');

        $this->app = new Application();
        $this->mockBuddyService = $this->createMock(BuddyService::class);
        $property = (new \ReflectionClass(Application::class))->getProperty('buddyService');
        $property->setValue($this->app, $this->mockBuddyService);
    }

    private function resolveJson(array $input): array
    {
        $tester = new CommandTester($this->app->find('resolve'));
        $status = $tester->execute($input + ['--json' => true]);
        $this->assertSame(0, $status, $tester->getDisplay());

        return json_decode($tester->getDisplay(), true);
    }

    public function testExecutionUrlResolvesEveryPartWithoutCallingTheApi(): void
    {
        $this->mockBuddyService->expects($this->never())->method($this->anything());

        $data = $this->resolveJson(['input' => 'https://app.buddy.works/awesomemotive/lindris-frontend/pipelines/pipeline/506857/execution/698b463a541a3b001b122b95?actionExecutionId=698b463a541a3b001b122bac']);

        $this->assertSame([
            'source' => 'url',
            'workspace' => 'awesomemotive',
            'project' => 'lindris-frontend',
            'pipeline' => 506857,
            'execution' => '698b463a541a3b001b122b95',
            'action_execution' => '698b463a541a3b001b122bac',
            'flags' => '-w awesomemotive -p lindris-frontend --pipeline=506857',
        ], $data);
    }

    public function testPipelineUrl(): void
    {
        $data = $this->resolveJson(['input' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/42']);

        $this->assertSame(['source' => 'url', 'workspace' => 'ws', 'project' => 'proj', 'pipeline' => 42, 'flags' => '-w ws -p proj --pipeline=42'], $data);
    }

    public function testProjectUrl(): void
    {
        $data = $this->resolveJson(['input' => 'https://app.buddy.works/ws/proj/pipelines']);

        $this->assertSame(['source' => 'url', 'workspace' => 'ws', 'project' => 'proj', 'flags' => '-w ws -p proj'], $data);
    }

    public function testNumericIdPassesThroughWithoutContext(): void
    {
        $this->mockBuddyService->expects($this->never())->method($this->anything());

        $data = $this->resolveJson(['input' => '578424']);

        $this->assertSame(['source' => 'id', 'pipeline' => 578424, 'flags' => '--pipeline=578424'], $data);
    }

    public function testNameExactMatchWinsOverPartialMatches(): void
    {
        $this->mockBuddyService->expects($this->once())->method('getAllPipelines')->with('ws', 'proj')->willReturn(['pipelines' => [
            ['id' => 2, 'name' => 'Deploy Production Hotfix'],
            ['id' => 1, 'name' => 'Deploy Production'],
        ]]);

        $data = $this->resolveJson(['input' => 'deploy production', '--workspace' => 'ws', '--project' => 'proj']);

        $this->assertSame(['source' => 'name', 'workspace' => 'ws', 'project' => 'proj', 'pipeline' => 1, 'pipeline_name' => 'Deploy Production', 'flags' => '-w ws -p proj --pipeline=1'], $data);
    }

    public function testNameUniquePartialMatch(): void
    {
        $this->mockBuddyService->method('getAllPipelines')->willReturn(['pipelines' => [
            ['id' => 7, 'name' => '[CI] Pull Request Tests'],
            ['id' => 8, 'name' => '[Production] Deploy'],
        ]]);

        $data = $this->resolveJson(['input' => 'pull request', '--workspace' => 'ws', '--project' => 'proj']);

        $this->assertSame(7, $data['pipeline']);
    }

    public function testAmbiguousNameFailsListingCandidates(): void
    {
        $this->mockBuddyService->method('getAllPipelines')->willReturn(['pipelines' => [
            ['id' => 7, 'name' => 'Deploy Staging'],
            ['id' => 8, 'name' => 'Deploy Production'],
        ]]);

        $tester = new CommandTester($this->app->find('resolve'));
        $status = $tester->execute(['input' => 'deploy', '--workspace' => 'ws', '--project' => 'proj', '--json' => true]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('matches 2 pipelines', $tester->getDisplay());
        $this->assertStringContainsString('7  Deploy Staging', $tester->getDisplay());
        $this->assertStringContainsString('8  Deploy Production', $tester->getDisplay());
    }

    public function testUnknownNameFailsListingAvailable(): void
    {
        $this->mockBuddyService->method('getAllPipelines')->willReturn(['pipelines' => [['id' => 7, 'name' => 'Deploy Staging']]]);

        $tester = new CommandTester($this->app->find('resolve'));
        $status = $tester->execute(['input' => 'nightly', '--workspace' => 'ws', '--project' => 'proj']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('No pipeline matches "nightly"', $tester->getDisplay());
        $this->assertStringContainsString('7  Deploy Staging', $tester->getDisplay());
    }

    public function testNameLookupRequiresProject(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No project specified');

        (new CommandTester($this->app->find('resolve')))->execute(['input' => 'deploy', '--workspace' => 'ws']);
    }

    public function testUrlWithoutProjectIsRejected(): void
    {
        $tester = new CommandTester($this->app->find('resolve'));
        $status = $tester->execute(['input' => 'https://app.buddy.works/ws']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Not a Buddy.works project URL', $tester->getDisplay());
    }

    public function testTableOutputShowsFlags(): void
    {
        $tester = new CommandTester($this->app->find('resolve'));
        $tester->execute(['input' => 'https://app.buddy.works/ws/proj/pipelines/pipeline/42']);

        $this->assertStringContainsString('-w ws -p proj --pipeline=42', $tester->getDisplay());
    }
}
