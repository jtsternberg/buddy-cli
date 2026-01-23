<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\YamlFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:update')
            ->setDescription('Update existing pipeline from YAML file')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID to update')
            ->addArgument('file', InputArgument::REQUIRED, 'YAML file path')
            ->setHelp(<<<'HELP'
Updates an existing pipeline from a YAML file. Only specified fields are updated.

Note: Actions are <comment>not</comment> updated via this command. Use <info>actions:update</info> to modify actions.

Pipeline YAML fields:
  name                       Pipeline name
  trigger_mode               MANUAL, ON_EVERY_PUSH, or SCHEDULED
  ref_name                   Git branch/tag reference (e.g., refs/heads/main)
  events                     Trigger events for ON_EVERY_PUSH mode
  priority                   Execution priority (NORMAL, HIGH, LOW)
  fetch_all_refs             Fetch all git refs
  always_from_scratch        Clean workspace each run
  auto_clear_cache           Clear cache automatically
  no_skip_to_most_recent     Don't skip to latest commit
  terminate_stale_runs       Cancel older runs when new one starts
  concurrent_pipeline_runs   Allow concurrent executions
  fail_on_prepare_env_warning  Fail on environment warnings
  variables                  Pipeline-level variables

Example YAML:
  name: "Renamed Pipeline"
  trigger_mode: ON_EVERY_PUSH
  ref_name: refs/heads/develop

Example:
  buddy pipelines:update 12345 pipeline-config.yaml --project=my-project
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
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $output->writeln("<error>File not found: {$file}</error>");
            return self::FAILURE;
        }

        $yaml = file_get_contents($file);
        $config = YamlFormatter::parse($yaml);

        // Extract actions (not updated via this command)
        unset($config['actions']);

        $pipelineData = $this->preparePipelineData($config);

        try {
            $pipeline = $this->getBuddyService()->updatePipeline($workspace, $project, $pipelineId, $pipelineData);
            $output->writeln("<info>Updated pipeline: {$pipeline['name']} (ID: {$pipeline['id']})</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Update failed: {$e->getMessage()}</error>");
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
}
