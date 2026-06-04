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
            ->addArgument('value', InputArgument::REQUIRED, 'Variable value')
            ->setHelp(<<<'HELP'
Creates or updates an environment variable. If a variable with the same key
exists at the same scope, it will be updated; otherwise a new one is created.

Scope selectors (choose at most one scope):
  -p, --project      Scope the variable to a project (by name)
      --pipeline     Scope the variable to a pipeline ID
      --action       Scope the variable to an action ID (requires --pipeline)

Other options:
      --workspace    Workspace/domain to operate in (routing context, NOT a scope)
  -t, --type         Variable type: VAR (default), SSH_KEY, SSH_PUBLIC_KEY
  -e, --encrypted    Encrypt the value (cannot be read back)
  -s, --settable     Allow value override during manual pipeline run
  -d, --description  Add a description for the variable

Scope hierarchy (most specific wins):
  action > pipeline > project > workspace

Only ONE scope is allowed per variable. --project is mutually exclusive with
--pipeline/--action. For an action-scoped variable, pass BOTH --pipeline and
--action: the pipeline routes to the action, and only the action scope is sent
to the API. If no scope selector is given, the variable is workspace-scoped.

--workspace is routing context (which workspace/domain to talk to), never a
scope — it can accompany any scope without conflict.

Values containing dashes (like --max-old-space-size=4096) require placing
all options BEFORE the -- separator:
  buddy vars:set --pipeline=12345 -- NODE_OPTIONS "--max-old-space-size=4096"

Examples:
  buddy vars:set API_KEY "secret123" --encrypted
  buddy vars:set NODE_ENV production --project=my-project
  buddy vars:set DEBUG true --pipeline=12345 --settable
  buddy vars:set DEPLOY_KEY "..." --type=SSH_KEY --encrypted
  buddy vars:set --pipeline=12345 -- NODE_OPTIONS "--max-old-space-size=4096"
  buddy vars:set --pipeline=506857 --action=1558732 -- NODE_OPTIONS "--max-old-space-size=8192"
HELP);

        $this->addWorkspaceOption();
        $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Scope to project');
        $this->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Scope to pipeline ID');
        $this->addOption('action', null, InputOption::VALUE_REQUIRED, 'Scope to action ID (requires --pipeline)');
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

        // Determine scope. The Buddy API accepts exactly ONE scope object on the
        // variable (project, pipeline, action, or — implicitly — workspace). The
        // scope selectors are --project, --pipeline, and --action. --workspace is
        // pure routing context (the domain), never a scope.
        $hasProject = $input->getOption('project') !== null;
        $hasPipeline = $input->getOption('pipeline') !== null;
        $hasAction = $input->getOption('action') !== null;

        // An action belongs to a pipeline, so --action needs --pipeline to route the
        // lookup. The pipeline is context here, NOT a second scope object (see below).
        if ($hasAction && !$hasPipeline) {
            $output->writeln('<error>--action requires --pipeline. An action belongs to a specific pipeline.</error>');
            return self::FAILURE;
        }

        // Project scope is a different (broader) scope than pipeline/action scope —
        // a variable can only have one, so these are mutually exclusive.
        if ($hasProject && ($hasPipeline || $hasAction)) {
            $output->writeln('<error>Only one scope allowed. Use --project OR --pipeline/--action, not both.</error>');
            $output->writeln('<comment>Scope hierarchy: action > pipeline > project > workspace</comment>');
            return self::FAILURE;
        }

        // Emit exactly one scope object (most specific wins). For an action-scoped
        // variable, send ONLY the action reference — the action already belongs to a
        // pipeline, so emitting `pipeline` too would trip the API's "Only one scope
        // is allowed" error. --pipeline is used purely to scope the existing-variable
        // lookup below.
        $filters = [];
        if ($hasAction) {
            $data['action'] = ['id' => (int) $input->getOption('action')];
            $filters['pipelineId'] = (int) $input->getOption('pipeline');
            $filters['actionId'] = (int) $input->getOption('action');
        } elseif ($hasPipeline) {
            $data['pipeline'] = ['id' => (int) $input->getOption('pipeline')];
            $filters['pipelineId'] = (int) $input->getOption('pipeline');
        } elseif ($hasProject) {
            $data['project'] = ['name' => $input->getOption('project')];
            $filters['projectName'] = $input->getOption('project');
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
