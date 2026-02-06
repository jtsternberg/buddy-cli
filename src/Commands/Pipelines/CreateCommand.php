<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\YamlFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:create')
            ->setDescription('Create new pipeline from YAML file')
            ->addArgument('file', InputArgument::REQUIRED, 'YAML file path')
            ->setHelp(<<<'HELP'
Creates a new pipeline from a native Buddy YAML file. Requires a pipeline name.

The YAML is applied as the full pipeline configuration (lossless). This command
creates a pipeline with minimal metadata first, then patches the native YAML.

Example YAML:
  name: "Deploy to Production"
  trigger_mode: MANUAL
  ref_name: refs/heads/main
  actions:
    - name: "Build"
      type: BUILD
      docker_image_name: node
      docker_image_tag: "18"
      execute_commands:
        - npm install
        - npm run build
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $output->writeln("<error>File not found: {$file}</error>");
            return self::FAILURE;
        }

        $yaml = file_get_contents($file);
        $config = YamlFormatter::parse($yaml);
        $pipelineConfig = $this->extractPipelineConfig($config);

        if (empty($pipelineConfig['name']) && empty($pipelineConfig['pipeline'])) {
            $output->writeln('<error>Pipeline name is required in YAML configuration</error>');
            return self::FAILURE;
        }

        $pipelineData = $this->preparePipelineData($pipelineConfig);

        try {
            $pipeline = $this->getBuddyService()->createPipeline($workspace, $project, $pipelineData);
            $this->getBuddyService()->updatePipelineYaml($workspace, $project, (int) $pipeline['id'], $yaml);
            $output->writeln("<info>Created pipeline: {$pipeline['name']} (ID: {$pipeline['id']})</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Import failed: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        return self::SUCCESS;
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

        if (empty($data['name']) && isset($config['pipeline'])) {
            $data['name'] = $config['pipeline'];
        }

        if (empty($data['ref_name']) && isset($config['refs']) && is_array($config['refs']) && !empty($config['refs'][0])) {
            $data['ref_name'] = $config['refs'][0];
        }

        return $data;
    }

    private function extractPipelineConfig(array $config): array
    {
        if (isset($config[0]) && is_array($config[0])) {
            return $config[0];
        }

        return $config;
    }
}
