<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Actions;

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
            ->setName('actions:show')
            ->setDescription('Show action details')
            ->addArgument('action-id', InputArgument::REQUIRED, 'Action ID')
            ->addOption('yaml', 'y', InputOption::VALUE_NONE, 'Output as YAML configuration');

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

        $action = $this->getBuddyService()->getPipelineAction($workspace, $project, $pipelineId, $actionId);

        if ($input->getOption('yaml')) {
            $config = $this->buildActionConfig($action);
            YamlFormatter::output($output, $config);
            return self::SUCCESS;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $action);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $action['id'] ?? '-',
            'Name' => $action['name'] ?? '-',
            'Type' => $action['type'] ?? '-',
            'Trigger' => $action['trigger_time'] ?? '-',
        ];

        if (isset($action['docker_image_name'])) {
            $data['Docker Image'] = ($action['docker_image_name'] ?? '') . ':' . ($action['docker_image_tag'] ?? 'latest');
        }
        if (isset($action['shell'])) {
            $data['Shell'] = $action['shell'];
        }
        if (isset($action['working_directory'])) {
            $data['Working Dir'] = $action['working_directory'];
        }

        TableFormatter::keyValue($output, $data, "Action: {$action['name']}");

        // Show commands if available
        if (!empty($action['execute_commands'])) {
            $output->writeln('');
            $output->writeln('<info>Execute Commands:</info>');
            foreach ($action['execute_commands'] as $cmd) {
                $output->writeln("  {$cmd}");
            }
        }

        if (!empty($action['setup_commands'])) {
            $output->writeln('');
            $output->writeln('<info>Setup Commands:</info>');
            foreach ($action['setup_commands'] as $cmd) {
                $output->writeln("  {$cmd}");
            }
        }

        return self::SUCCESS;
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

        return array_filter($config, fn ($v) => $v !== null);
    }
}
