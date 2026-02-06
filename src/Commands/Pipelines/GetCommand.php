<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:get')
            ->setDescription('Get pipeline configuration as YAML')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: pipeline-{id}.yaml)')
            ->setHelp(<<<'HELP'
Export a pipeline as a native Buddy YAML configuration file.

The output YAML is lossless and round-trippable with <info>pipelines:update</info>
and <info>pipelines:create</info>, making it suitable for version control and
template workflows.

Options:
  -o, --output  Output file path (default: pipeline-{id}.yaml)

Examples:
  buddy pipelines:get 12345 --project=my-project
  buddy pipelines:get 12345 -o deploy-pipeline.yaml
  buddy pipelines:get 12345 --output=templates/build.yaml
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

        $yaml = $this->getBuddyService()->getPipelineYaml($workspace, $project, $pipelineId);

        $outputPath = $input->getOption('output') ?? "pipeline-{$pipelineId}.yaml";
        file_put_contents($outputPath, $yaml);

        $output->writeln("<info>Saved pipeline config to {$outputPath}</info>");

        return self::SUCCESS;
    }

}
