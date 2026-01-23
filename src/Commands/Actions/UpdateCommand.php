<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Actions;

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
            ->setName('actions:update')
            ->setDescription('Update existing action from YAML file')
            ->addArgument('action-id', InputArgument::REQUIRED, 'Action ID to update')
            ->addArgument('file', InputArgument::REQUIRED, 'YAML file path')
            ->setHelp(<<<'HELP'
Updates an existing action using fields from a YAML file. Only fields present in the YAML are updated.

Supported YAML fields:
  name               Action name
  type               Action type (BUILD, SFTP, etc.)
  trigger_time       ON_EVERY_EXECUTION, ON_FAILURE, or ON_BACK_TO_SUCCESS
  docker_image_name  Docker image (for BUILD actions)
  docker_image_tag   Docker image tag
  execute_commands   List of commands to execute
  setup_commands     List of setup commands
  shell              Shell to use (e.g., BASH, SH)
  working_directory  Working directory path

Example YAML:
  name: "Build App"
  execute_commands:
    - composer install
    - phpunit

Tip: Use <info>actions:show --yaml</info> to export current config as a starting point.
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        $this->addPipelineOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelineId = $this->requirePipeline($input);
        $actionId = (int) $input->getArgument('action-id');
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $output->writeln("<error>File not found: {$file}</error>");
            return self::FAILURE;
        }

        $yaml = file_get_contents($file);
        $config = YamlFormatter::parse($yaml);

        $actionData = $this->prepareActionData($config);

        try {
            $action = $this->getBuddyService()->updatePipelineAction($workspace, $project, $pipelineId, $actionId, $actionData);

            if ($this->isJsonOutput($input)) {
                $this->outputJson($output, $action);
                return self::SUCCESS;
            }

            $output->writeln("<info>Updated action: {$action['name']} (ID: {$action['id']})</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Update failed: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        return self::SUCCESS;
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
