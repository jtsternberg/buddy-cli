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
            ->addOption('on', null, InputOption::VALUE_REQUIRED, 'Trigger: MANUAL (default) or ON_EVERY_PUSH (needs --refs). SCHEDULED needs a YAML file (flag-based mode)')
            ->addOption('refs', null, InputOption::VALUE_REQUIRED, 'Branch ref, e.g. refs/heads/main: the branch a MANUAL run uses, or the branch whose pushes trigger ON_EVERY_PUSH (flag-based mode)')
            ->setHelp(<<<'HELP'
Creates a new pipeline. Supports two modes:

<comment>Flag-based (quick creation):</comment>
  buddy pipelines:create --name="Deploy to Production" --on=MANUAL --refs=refs/heads/main
  buddy pipelines:create --name="Deploy to Production" --on=MANUAL --refs=refs/heads/main --json
  buddy pipelines:create --name="CI" --on=ON_EVERY_PUSH --refs=refs/heads/main

<comment>YAML file (full configuration):</comment>
  buddy pipelines:create pipeline.yaml

The YAML file is applied as the full pipeline configuration (lossless). This command
creates a pipeline with minimal metadata first, then patches the native YAML.

Example YAML (native Buddy format, as written by pipelines:get):
  - pipeline: "Deploy to Production"
    refs:
      - refs/heads/main
    actions:
      - action: "Build"
        type: BUILD
        docker_image_name: node
        docker_image_tag: "18"
        execute_commands:
          - npm install
          - npm run build

  For a push trigger, replace refs with:
    events:
      - type: PUSH
        refs:
          - refs/heads/main
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

        $triggerMode = strtoupper((string) ($input->getOption('on') ?? 'MANUAL'));
        $refs = $input->getOption('refs');

        // The API dropped trigger_mode/ref_name and silently ignores them:
        // a manual pipeline's branch is `refs`, a push trigger is a PUSH event.
        $pipelineData = ['name' => $name];

        switch ($triggerMode) {
            case 'MANUAL':
                if (!empty($refs)) {
                    $pipelineData['refs'] = [$refs];
                }
                break;
            case 'ON_EVERY_PUSH':
                if (empty($refs)) {
                    $output->writeln('<error>--on=ON_EVERY_PUSH needs --refs to say which branch pushes trigger it, e.g. --refs=refs/heads/main</error>');
                    return self::FAILURE;
                }
                $pipelineData['events'] = [['type' => 'PUSH', 'refs' => [$refs]]];
                break;
            case 'SCHEDULED':
                $output->writeln('<error>--on=SCHEDULED needs a cron expression, which has no flag. Create it from a YAML file instead.</error>');
                return self::FAILURE;
            default:
                $output->writeln("<error>Unknown --on value '{$triggerMode}'. Use MANUAL, ON_EVERY_PUSH or SCHEDULED.</error>");
                return self::FAILURE;
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
