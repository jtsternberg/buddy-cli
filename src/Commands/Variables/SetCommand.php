<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Variables;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('vars:set')
            ->setDescription('Create or update an environment variable')
            ->addArgument('key', InputArgument::REQUIRED, 'Variable key')
            ->addArgument('value', InputArgument::REQUIRED, 'Variable value');

        $this->addWorkspaceOption();
        $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Scope to project');
        $this->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Scope to pipeline ID');
        $this->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Variable type: VAR, SSH_KEY, SSH_PUBLIC_KEY', 'VAR');
        $this->addOption('encrypted', 'e', InputOption::VALUE_NONE, 'Encrypt the variable value');
        $this->addOption('settable', 's', InputOption::VALUE_NONE, 'Allow value to be set during manual run');
        $this->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Variable description');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        $data = [
            'key' => $key,
            'value' => $value,
            'type' => $input->getOption('type'),
        ];

        if ($input->getOption('encrypted')) {
            $data['encrypted'] = true;
        }
        if ($input->getOption('settable')) {
            $data['settable'] = true;
        }
        if ($input->getOption('description') !== null) {
            $data['description'] = $input->getOption('description');
        }

        // Add scoping
        if ($input->getOption('project') !== null) {
            $data['project'] = ['name' => $input->getOption('project')];
        }
        if ($input->getOption('pipeline') !== null) {
            $data['pipeline'] = ['id' => (int) $input->getOption('pipeline')];
        }

        // Try to find existing variable by key
        $filters = [];
        if (isset($data['project'])) {
            $filters['projectName'] = $data['project']['name'];
        }
        if (isset($data['pipeline'])) {
            $filters['pipelineId'] = $data['pipeline']['id'];
        }

        $existingId = $this->findVariableByKey($workspace, $key, $filters);

        try {
            if ($existingId !== null) {
                $result = $this->getBuddyService()->updateVariable($workspace, $existingId, $data);
                $action = 'Updated';
            } else {
                $result = $this->getBuddyService()->createVariable($workspace, $data);
                $action = 'Created';
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Failed to set variable: {$e->getMessage()}</error>");
            return self::FAILURE;
        }

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $result);
            return self::SUCCESS;
        }

        $output->writeln("<info>{$action} variable: {$key} (ID: {$result['id']})</info>");
        return self::SUCCESS;
    }

    private function findVariableByKey(string $workspace, string $key, array $filters): ?int
    {
        try {
            $response = $this->getBuddyService()->getVariables($workspace, $filters);
            $variables = $response['variables'] ?? [];

            foreach ($variables as $variable) {
                if (($variable['key'] ?? '') === $key) {
                    return (int) $variable['id'];
                }
            }
        } catch (\Exception) {
            // If we can't list variables, just try to create
        }

        return null;
    }
}
