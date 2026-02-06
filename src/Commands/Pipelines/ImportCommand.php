<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use Buddy\Exceptions\BuddyResponseException;
use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:import')
            ->setDescription('Import pipeline configuration from YAML file (lossless, via YAML API)')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID to update')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to YAML file')
            ->setHelp(<<<'HELP'
Import a pipeline configuration from a native Buddy YAML file.

This uses Buddy's dedicated YAML API endpoint and performs a lossless
update of the pipeline, including all settings (SSH targets, integration
refs, rsync configs, disabled flags, etc.).

The YAML file is typically produced by <info>pipelines:export</info>.

Examples:
  buddy pipelines:import 12345 pipeline.yaml --project=my-project
  buddy pipelines:import 12345 deploy-pipeline.yaml -w my-workspace -p my-project
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
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $output->writeln("<error>File not found: {$filePath}</error>");
            return self::FAILURE;
        }

        $yaml = file_get_contents($filePath);
        if ($yaml === false) {
            $output->writeln("<error>Failed to read file: {$filePath}</error>");
            return self::FAILURE;
        }

        $base64Yaml = base64_encode($yaml);

        try {
            $this->getBuddyService()->importPipelineYaml($workspace, $project, $pipelineId, $base64Yaml);
        } catch (BuddyResponseException $e) {
            $output->writeln("<error>Import failed (HTTP {$e->getStatusCode()}): {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        $output->writeln("<info>Imported pipeline YAML from {$filePath} to pipeline {$pipelineId}</info>");

        return self::SUCCESS;
    }
}
