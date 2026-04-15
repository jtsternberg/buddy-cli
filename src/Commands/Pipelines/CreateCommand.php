<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\YamlFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:create')
            ->setDescription('Create a new pipeline (from YAML file or flags)')
            ->addArgument('file', InputArgument::OPTIONAL, 'YAML file path (optional — omit to use flags instead)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Pipeline name (flag-based mode)')
            ->addOption('on', null, InputOption::VALUE_REQUIRED, 'Trigger mode: MANUAL, ON_EVERY_PUSH, SCHEDULED (flag-based mode)')
            ->addOption('refs', null, InputOption::VALUE_REQUIRED, 'Ref name / branch pattern, e.g. refs/heads/main (flag-based mode)')
            ->setHelp(<<<'HELP'
Creates a new pipeline. Supports two modes:

<comment>Flag-based (quick creation):</comment>
  buddy pipelines:create --name="Deploy to Production" --on=MANUAL --refs=refs/heads/main
  buddy pipelines:create --name="Deploy to Production" --on=MANUAL --refs=refs/heads/main --json

<comment>YAML file (full configuration):</comment>
  buddy pipelines:create pipeline.yaml

The YAML file is applied as the full pipeline configuration (lossless). This command
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

        if ($file !== null) {
            return $this->createFromFile($input, $output, $workspace, $project, $file);
        }

        return $this->createFromFlags($input, $output, $workspace, $project);
    }

    private function createFromFile(InputInterface $input, OutputInterface $output, string $workspace, string $project, string $file): int
    {
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
        $pipeline = null;

        try {
            $pipeline = $this->getBuddyService()->createPipeline($workspace, $project, $pipelineData);
            $this->getBuddyService()->updatePipelineYaml($workspace, $project, (int) $pipeline['id'], $yaml);
        } catch (\Exception $e) {
            $message = "Import failed: {$e->getMessage()}";
            if ($pipeline !== null) {
                $message .= " Pipeline #{$pipeline['id']} was created but YAML update failed — you may need to delete it manually.";
            }
            $output->writeln("<error>{$message}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $pipeline);
            return self::SUCCESS;
        }

        $output->writeln("<info>Created pipeline: {$pipeline['name']} (ID: {$pipeline['id']})</info>");
        return self::SUCCESS;
    }

    private function createFromFlags(InputInterface $input, OutputInterface $output, string $workspace, string $project): int
    {
        $name = $input->getOption('name');

        if (empty($name)) {
            $output->writeln('<error>Pipeline name is required. Use --name=<name> or provide a YAML file.</error>');
            return self::FAILURE;
        }

        $triggerMode = $input->getOption('on');
        $refs = $input->getOption('refs');

        $pipelineData = ['name' => $name];

        if (!empty($triggerMode)) {
            $pipelineData['trigger_mode'] = strtoupper($triggerMode);
        }

        if (!empty($refs)) {
            $pipelineData['ref_name'] = $refs;
        }

        try {
            $pipeline = $this->getBuddyService()->createPipeline($workspace, $project, $pipelineData);
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to create pipeline: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $pipeline);
            return self::SUCCESS;
        }

        $output->writeln("<info>Created pipeline: {$pipeline['name']} (ID: {$pipeline['id']})</info>");
        return self::SUCCESS;
    }

    protected function preparePipelineData(array $config): array
    {
        $data = parent::preparePipelineData($config);

        if (empty($data['name']) && isset($config['pipeline'])) {
            $data['name'] = $config['pipeline'];
        }

        if (empty($data['ref_name']) && isset($config['refs']) && is_array($config['refs']) && !empty($config['refs'][0])) {
            $data['ref_name'] = $config['refs'][0];
        }

        return $data;
    }

    /**
     * Unwrap parsed YAML config if the parser returned it inside a
     * numerically-indexed array (e.g. from document separator "---").
     */
    private function extractPipelineConfig(array $config): array
    {
        if (isset($config[0]) && is_array($config[0])) {
            return $config[0];
        }

        return $config;
    }
}
