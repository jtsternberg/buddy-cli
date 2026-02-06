<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use BuddyCli\Output\YamlFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SettingsCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:settings')
            ->setDescription('Show or update pipeline settings (metadata + variables)')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('update', 'u', InputOption::VALUE_REQUIRED, 'YAML file path to update settings')
            ->addOption('yaml', 'y', InputOption::VALUE_NONE, 'Output as YAML configuration')
            ->setHelp(<<<'HELP'
Show or update pipeline settings (metadata + variables only).

Output Formats:
  (default)  Human-readable table
  --yaml     YAML config (settings + variables)
  --json     JSON config (settings + variables)

Examples:
  buddy pipelines:settings 12345 --project=my-project
  buddy pipelines:settings 12345 --yaml
  buddy pipelines:settings 12345 --update settings.yaml
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = (int) $input->getArgument('pipeline-id');
        $updateFile = $input->getOption('update');

        if ($updateFile) {
            return $this->handleUpdate($workspace, $project, $pipelineId, (string) $updateFile, $output);
        }

        $pipeline = $this->getBuddyService()->getPipeline($workspace, $project, $pipelineId);
        $config = $this->buildSettingsConfig($pipeline);

        if ($input->getOption('yaml')) {
            YamlFormatter::output($output, $config);
            return self::SUCCESS;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $config);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $pipeline['id'] ?? '-',
            'Name' => $pipeline['name'] ?? '-',
            'Trigger' => $pipeline['trigger_mode'] ?? '-',
            'Branch/Ref' => $pipeline['ref_name'] ?? '-',
            'Priority' => $pipeline['priority'] ?? '-',
            'Fetch All Refs' => $this->formatBool($pipeline['fetch_all_refs'] ?? false),
            'Always From Scratch' => $this->formatBool($pipeline['always_from_scratch'] ?? false),
            'Auto Clear Cache' => $this->formatBool($pipeline['auto_clear_cache'] ?? false),
            'No Skip To Most Recent' => $this->formatBool($pipeline['no_skip_to_most_recent'] ?? false),
            'Terminate Stale Runs' => $this->formatBool($pipeline['terminate_stale_runs'] ?? false),
            'Concurrent Runs' => $this->formatBool($pipeline['concurrent_pipeline_runs'] ?? false),
            'Fail On Prepare Env Warning' => $this->formatBool($pipeline['fail_on_prepare_env_warning'] ?? false),
        ];

        TableFormatter::keyValue($output, $data, "Pipeline Settings: {$pipeline['name']}");

        $variables = $pipeline['variables'] ?? [];
        if (!empty($variables)) {
            $output->writeln('');
            $output->writeln('<info>Variables:</info>');

            $rows = array_map(fn ($v) => [
                $v['key'] ?? '-',
                $v['type'] ?? 'VAR',
                $this->formatBool($v['settable'] ?? false),
                $v['description'] ?? '',
            ], $variables);

            TableFormatter::render($output, ['Key', 'Type', 'Settable', 'Description'], $rows);
        }

        return self::SUCCESS;
    }

    private function handleUpdate(
        string $workspace,
        string $project,
        int $pipelineId,
        string $file,
        OutputInterface $output
    ): int {
        if (!file_exists($file)) {
            $output->writeln("<error>File not found: {$file}</error>");
            return self::FAILURE;
        }

        $yaml = file_get_contents($file);
        $config = YamlFormatter::parse($yaml);

        $pipelineData = $this->preparePipelineData($config);

        try {
            $pipeline = $this->getBuddyService()->updatePipeline($workspace, $project, $pipelineId, $pipelineData);
            $output->writeln("<info>Updated pipeline settings: {$pipeline['name']} (ID: {$pipeline['id']})</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Update failed: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function buildSettingsConfig(array $pipeline): array
    {
        $config = [
            'name' => $pipeline['name'] ?? null,
            'trigger_mode' => $pipeline['trigger_mode'] ?? null,
            'ref_name' => $pipeline['ref_name'] ?? null,
            'events' => $pipeline['events'] ?? [],
            'priority' => $pipeline['priority'] ?? null,
            'fetch_all_refs' => $pipeline['fetch_all_refs'] ?? false,
            'always_from_scratch' => $pipeline['always_from_scratch'] ?? false,
            'auto_clear_cache' => $pipeline['auto_clear_cache'] ?? false,
            'no_skip_to_most_recent' => $pipeline['no_skip_to_most_recent'] ?? false,
            'terminate_stale_runs' => $pipeline['terminate_stale_runs'] ?? false,
            'concurrent_pipeline_runs' => $pipeline['concurrent_pipeline_runs'] ?? false,
            'fail_on_prepare_env_warning' => $pipeline['fail_on_prepare_env_warning'] ?? false,
        ];

        if (!empty($pipeline['variables'])) {
            $config['variables'] = array_map(fn ($v) => array_filter([
                'key' => $v['key'] ?? null,
                'value' => $v['value'] ?? '',
                'type' => $v['type'] ?? 'VAR',
                'settable' => $v['settable'] ?? false,
                'description' => $v['description'] ?? null,
            ], fn ($val) => $val !== null), $pipeline['variables']);
        }

        return array_filter($config, fn ($v) => $v !== null && $v !== []);
    }

    private function preparePipelineData(array $config): array
    {
        $data = [];

        $allowedFields = [
            'name', 'trigger_mode', 'ref_name', 'events', 'priority',
            'fetch_all_refs', 'always_from_scratch', 'auto_clear_cache',
            'no_skip_to_most_recent', 'terminate_stale_runs', 'concurrent_pipeline_runs',
            'fail_on_prepare_env_warning', 'variables',
        ];

        foreach ($allowedFields as $field) {
            if (isset($config[$field])) {
                $data[$field] = $config[$field];
            }
        }

        return $data;
    }

    private function formatBool(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
