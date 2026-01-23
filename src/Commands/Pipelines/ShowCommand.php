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

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:show')
            ->setDescription('Show pipeline details')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('yaml', 'y', InputOption::VALUE_NONE, 'Output as YAML configuration')
            ->setHelp(<<<'HELP'
Display detailed information about a pipeline including its actions.

Default output shows pipeline metadata (status, trigger, branch) and a table
of configured actions. Use <info>--yaml</info> or <info>--json</info> for machine-readable formats.

Output Formats:
  (default)  Human-readable table with pipeline info and actions
  --yaml     YAML config (same format as pipelines:get, can be used with create)
  --json     Full API response as JSON

Options:
  -y, --yaml  Output as YAML configuration (to stdout, not a file)

Examples:
  buddy pipelines:show 12345 --project=my-project
  buddy pipelines:show 12345 --yaml
  buddy pipelines:show 12345 --json
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

        $pipeline = $this->getBuddyService()->getPipeline($workspace, $project, $pipelineId);
        $actions = $this->getBuddyService()->getPipelineActions($workspace, $project, $pipelineId);

        if ($input->getOption('yaml')) {
            $config = $this->buildPipelineConfig($pipeline, $actions['actions'] ?? []);
            YamlFormatter::output($output, $config);
            return self::SUCCESS;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $pipeline);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $pipeline['id'] ?? '-',
            'Name' => $pipeline['name'] ?? '-',
            'Status' => $this->formatStatus($pipeline['last_execution_status'] ?? 'UNKNOWN'),
            'Trigger' => $pipeline['trigger_mode'] ?? '-',
            'Branch/Ref' => $pipeline['ref_name'] ?? '-',
            'Last Run' => $this->formatTime($pipeline['last_execution_date'] ?? null),
            'Created' => $this->formatTime($pipeline['create_date'] ?? null),
        ];

        TableFormatter::keyValue($output, $data, "Pipeline: {$pipeline['name']}");

        // Show actions if available
        $actionList = $actions['actions'] ?? [];

        if (!empty($actionList)) {
            $output->writeln('');
            $output->writeln('<info>Actions:</info>');

            $rows = [];
            foreach ($actionList as $action) {
                $rows[] = [
                    $action['id'] ?? '-',
                    $action['name'] ?? '-',
                    $action['type'] ?? '-',
                    $this->formatTriggerConditions($action['trigger_conditions'] ?? []),
                ];
            }

            TableFormatter::render($output, ['ID', 'Name', 'Type', 'Conditions'], $rows);
        }

        return self::SUCCESS;
    }

    private function formatTriggerConditions(array $conditions): string
    {
        if (empty($conditions)) {
            return '-';
        }

        $formatted = [];
        foreach ($conditions as $cond) {
            $type = $cond['trigger_condition'] ?? '';
            $key = $cond['trigger_variable_key'] ?? '';
            $value = $cond['trigger_variable_value'] ?? '';

            // Format as "VAR_IS_NOT:KEY=val" or similar
            if ($key) {
                $formatted[] = "{$type}:{$key}={$value}";
            } else {
                $formatted[] = $type;
            }
        }

        return implode(', ', $formatted);
    }

    private function buildPipelineConfig(array $pipeline, array $actions): array
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
            'fail_on_prepare_env_warning' => $pipeline['fail_on_prepare_env_warning'] ?? true,
        ];

        if (!empty($pipeline['variables'])) {
            $config['variables'] = array_map(fn($v) => [
                'key' => $v['key'],
                'value' => $v['value'] ?? '',
                'type' => $v['type'] ?? 'VAR',
                'settable' => $v['settable'] ?? false,
                'description' => $v['description'] ?? null,
            ], $pipeline['variables']);
        }

        if (!empty($actions)) {
            $config['actions'] = array_map(fn($a) => $this->buildActionConfig($a), $actions);
        }

        return array_filter($config, fn($v) => $v !== null && $v !== []);
    }

    private function buildActionConfig(array $action): array
    {
        $config = [
            'name' => $action['name'] ?? null,
            'type' => $action['type'] ?? null,
            'trigger_time' => $action['trigger_time'] ?? 'ON_EVERY_EXECUTION',
        ];

        // Include type-specific configuration
        if (isset($action['docker_image_name'])) {
            $config['docker_image_name'] = $action['docker_image_name'];
            $config['docker_image_tag'] = $action['docker_image_tag'] ?? 'latest';
        }
        if (isset($action['execute_commands'])) {
            $config['execute_commands'] = $action['execute_commands'];
        }
        if (isset($action['setup_commands'])) {
            $config['setup_commands'] = $action['setup_commands'];
        }
        if (isset($action['shell'])) {
            $config['shell'] = $action['shell'];
        }
        if (isset($action['working_directory'])) {
            $config['working_directory'] = $action['working_directory'];
        }
        if (!empty($action['trigger_conditions'])) {
            $config['trigger_conditions'] = $action['trigger_conditions'];
        }

        return array_filter($config, fn($v) => $v !== null);
    }
}
