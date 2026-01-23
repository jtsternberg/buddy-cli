<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\YamlFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:get')
            ->setDescription('Get pipeline configuration as YAML')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: pipeline-{id}.yaml)')
            ->setHelp(<<<'HELP'
Export a pipeline and its actions as a YAML configuration file.

The output YAML is compatible with <info>pipelines:create</info>, making it easy to:
  - Clone a pipeline to another project
  - Version control pipeline configurations
  - Create templates from existing pipelines

Includes pipeline settings, variables, and all action configurations.

Options:
  -o, --output  Output file path (default: pipeline-{id}.yaml)

Examples:
  buddy pipelines:get 12345 --project=my-project
  buddy pipelines:get 12345 -o deploy-pipeline.yaml
  buddy pipelines:get 12345 --output=templates/build.yaml
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

        $config = $this->buildPipelineConfig($pipeline, $actions['actions'] ?? []);
        $yaml = YamlFormatter::dump($config);

        $outputPath = $input->getOption('output') ?? "pipeline-{$pipelineId}.yaml";
        file_put_contents($outputPath, $yaml);

        $output->writeln("<info>Saved pipeline config to {$outputPath}</info>");

        return self::SUCCESS;
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
            $config['variables'] = array_map(fn($v) => array_filter([
                'key' => $v['key'],
                'value' => $v['value'] ?? '',
                'type' => $v['type'] ?? 'VAR',
                'settable' => $v['settable'] ?? false,
                'description' => $v['description'] ?? null,
            ], fn($val) => $val !== null), $pipeline['variables']);
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

        return array_filter($config, fn($v) => $v !== null);
    }
}
