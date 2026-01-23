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
Creates a new pipeline from a YAML file. Requires <info>name</info> field.

Pipeline YAML fields:
  name                       Pipeline name (required)
  trigger_mode               MANUAL (default), ON_EVERY_PUSH, or SCHEDULED
  ref_name                   Git branch/tag reference (e.g., refs/heads/main)
  events                     Trigger events for ON_EVERY_PUSH mode
  priority                   Execution priority (NORMAL, HIGH, LOW)
  fetch_all_refs             Fetch all git refs (default: false)
  always_from_scratch        Clean workspace each run (default: false)
  auto_clear_cache           Clear cache automatically (default: false)
  no_skip_to_most_recent     Don't skip to latest commit (default: false)
  terminate_stale_runs       Cancel older runs when new one starts (default: false)
  concurrent_pipeline_runs   Allow concurrent executions (default: false)
  fail_on_prepare_env_warning  Fail on environment warnings (default: false)
  variables                  Pipeline-level variables
  actions                    List of actions (optional, created after pipeline)

Action YAML fields (within actions list):
  name                 Action name (required)
  type                 Action type (required): BUILD, SFTP, SSH_COMMAND, SLACK, etc.
  trigger_time         ON_EVERY_EXECUTION (default), ON_FAILURE, or ON_BACK_TO_SUCCESS
  docker_image_name    Docker image (for BUILD actions)
  docker_image_tag     Docker image tag (default: latest)
  execute_commands     Commands to execute
  setup_commands       Setup commands
  shell                Shell to use (BASH, SH)
  working_directory    Working directory path

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

        if (empty($config['name'])) {
            $output->writeln('<error>Pipeline name is required in YAML configuration</error>');
            return self::FAILURE;
        }

        $actions = $config['actions'] ?? [];
        unset($config['actions']);

        $pipelineData = $this->preparePipelineData($config);

        try {
            $pipeline = $this->getBuddyService()->createPipeline($workspace, $project, $pipelineData);
            $output->writeln("<info>Created pipeline: {$pipeline['name']} (ID: {$pipeline['id']})</info>");

            if (!empty($actions)) {
                $output->writeln('<comment>Creating actions...</comment>');
                foreach ($actions as $actionConfig) {
                    $actionData = $this->prepareActionData($actionConfig);
                    $action = $this->getBuddyService()->createPipelineAction($workspace, $project, (int) $pipeline['id'], $actionData);
                    $output->writeln("  - Created action: {$action['name']}");
                }
            }
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

        return $data;
    }

    private function prepareActionData(array $config): array
    {
        $data = [];

        $allowedFields = [
            'name', 'type', 'trigger_time', 'docker_image_name', 'docker_image_tag',
            'execute_commands', 'setup_commands', 'shell', 'working_directory',
        ];

        foreach ($allowedFields as $field) {
            if (isset($config[$field])) {
                $data[$field] = $config[$field];
            }
        }

        return $data;
    }
}
