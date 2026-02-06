<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use Buddy\Exceptions\BuddyResponseException;
use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:export')
            ->setDescription('Export pipeline configuration as YAML (lossless, via YAML API)')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: pipeline-{id}.yaml)')
            ->setHelp(<<<'HELP'
Export a pipeline's complete configuration as native Buddy YAML.

Unlike <info>pipelines:get</info>, this uses Buddy's dedicated YAML API endpoint
and produces a lossless export that includes all settings (SSH targets,
integration refs, rsync configs, disabled flags, etc.).

The exported YAML can be re-imported with <info>pipelines:import</info>.

Examples:
  buddy pipelines:export 12345 --project=my-project
  buddy pipelines:export 12345 -o deploy-pipeline.yaml
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

        try {
            $response = $this->getBuddyService()->exportPipelineYaml($workspace, $project, $pipelineId);
        } catch (BuddyResponseException $e) {
            $output->writeln("<error>Export failed (HTTP {$e->getStatusCode()}): {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        $base64Yaml = $response['yaml'] ?? '';
        $yaml = base64_decode($base64Yaml, true);

        if ($yaml === false) {
            $output->writeln('<error>Failed to decode YAML response from API</error>');
            return self::FAILURE;
        }

        $outputPath = $input->getOption('output') ?? "pipeline-{$pipelineId}.yaml";
        file_put_contents($outputPath, $yaml);

        $output->writeln("<info>Exported pipeline YAML to {$outputPath}</info>");

        return self::SUCCESS;
    }
}
