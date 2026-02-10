<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
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
Updates an existing pipeline using native Buddy YAML. The YAML is applied
as the full pipeline configuration (lossless, includes actions).

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

        try {
            $yaml = file_get_contents($file);
            $this->getBuddyService()->updatePipelineYaml($workspace, $project, $pipelineId, $yaml);
            $output->writeln("<info>Updated pipeline #{$pipelineId} from native YAML</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Update failed: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
