<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Actions;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('actions:delete')
            ->setDescription('Delete an action from a pipeline')
            ->addArgument('action-id', InputArgument::REQUIRED, 'Action ID to delete')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation');

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
        $force = $input->getOption('force');

        // Get action details for confirmation
        try {
            $action = $this->getBuddyService()->getPipelineAction($workspace, $project, $pipelineId, $actionId);
        } catch (\Exception $e) {
            $output->writeln("<error>Action not found: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if (!$force) {
            $helper = $this->getHelper('question');
            $question = new \Symfony\Component\Console\Question\ConfirmationQuestion(
                "Delete action '{$action['name']}' (ID: {$actionId})? [y/N] ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Cancelled.</comment>');
                return self::SUCCESS;
            }
        }

        try {
            $this->getBuddyService()->deletePipelineAction($workspace, $project, $pipelineId, $actionId);

            if ($this->isJsonOutput($input)) {
                $this->outputJson($output, ['deleted' => true, 'id' => $actionId]);
                return self::SUCCESS;
            }

            $output->writeln("<info>Deleted action: {$action['name']} (ID: {$actionId})</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>Delete failed: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
