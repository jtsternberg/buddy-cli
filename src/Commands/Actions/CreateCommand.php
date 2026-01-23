<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Actions;

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
            ->setName('actions:create')
            ->setDescription('Create new action from YAML file')
            ->addArgument('file', InputArgument::REQUIRED, 'YAML file path');

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
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $output->writeln("<error>File not found: {$file}</error>");
            return self::FAILURE;
        }

        $yaml = file_get_contents($file);
        $config = YamlFormatter::parse($yaml);

        if (empty($config['name'])) {
            $output->writeln('<error>Action name is required in YAML configuration</error>');
            return self::FAILURE;
        }

        if (empty($config['type'])) {
            $output->writeln('<error>Action type is required in YAML configuration</error>');
            return self::FAILURE;
        }

        $actionData = $this->prepareActionData($config);

        try {
            $action = $this->getBuddyService()->createPipelineAction($workspace, $project, $pipelineId, $actionData);

            if ($this->isJsonOutput($input)) {
                $this->outputJson($output, $action);
                return self::SUCCESS;
            }

            $output->writeln("<info>Created action: {$action['name']} (ID: {$action['id']})</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Create failed: {$e->getMessage()}</error>");
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
