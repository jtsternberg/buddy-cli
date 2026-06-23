<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Variables;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('vars:set')
            ->setDescription('Create or update an environment variable')
            ->addArgument('key', InputArgument::REQUIRED, 'Variable key')
            ->addArgument('value', InputArgument::OPTIONAL, "Variable value. Use '-' to read from stdin, or omit it and pass --value-file.")
            ->setHelp(<<<'HELP'
Creates or updates an environment variable. If a variable with the same key
exists at the same scope, it will be updated; otherwise a new one is created.

Scope selectors (choose at most one scope):
  -p, --project      Scope the variable to a project (by name)
      --pipeline     Scope the variable to a pipeline ID
      --action       Scope the variable to an action ID (requires --pipeline)

Providing the value securely:
  By default the value is a positional argument, which lands in your shell
  history and is visible in the process list (ps aux). For secrets (especially
  --encrypted variables), provide the value without it touching argv:
      --value-file=<path>   Read the value from a file
      --value-file=-        Read the value from stdin
      vars:set KEY -        Bare '-' positional also reads from stdin
  A single trailing newline is stripped, so 'echo secret | buddy vars:set ...'
  works as expected. The literal positional value and --value-file are mutually
  exclusive.

Other options:
      --workspace    Workspace/domain to operate in (routing context, NOT a scope)
  -t, --type         Variable type: VAR (default), SSH_KEY, SSH_PUBLIC_KEY
  -e, --encrypted    Encrypt the value (cannot be read back)
  -s, --settable     Allow value override during manual pipeline run
  -d, --description  Add a description for the variable
      --value-file   Read the value from a file (or '-' for stdin)

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
  echo -n "secret123" | buddy vars:set API_KEY - --encrypted
  buddy vars:set API_KEY --value-file=./secret.txt --encrypted
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
        $this->addOption('value-file', null, InputOption::VALUE_REQUIRED, "Read the value from a file, or '-' for stdin");
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $key = $input->getArgument('key');

        $value = $this->resolveValue($input, $output);
        if ($value === null) {
            return self::FAILURE;
        }

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

    /**
     * Resolve the variable value from (in priority order) --value-file, a bare '-'
     * positional (stdin), or the literal positional argument.
     *
     * Reading from stdin/file keeps secrets out of argv (ps aux) and shell history.
     * Returns null and writes an error on any failure; callers should treat null as
     * a FAILURE exit.
     */
    private function resolveValue(InputInterface $input, OutputInterface $output): ?string
    {
        $positional = $input->getArgument('value');
        $valueFile = $input->getOption('value-file');

        // A literal positional value and --value-file are two ways of saying the same
        // thing; accepting both would be ambiguous about which wins.
        if ($valueFile !== null && $positional !== null && $positional !== '-') {
            $output->writeln('<error>Cannot use both a positional value and --value-file. Pick one.</error>');
            return null;
        }

        // --value-file=- and the bare '-' positional both mean "read from stdin".
        if ($valueFile === '-' || ($valueFile === null && $positional === '-')) {
            return $this->stripTrailingNewline($this->readStdin($input));
        }

        if ($valueFile !== null) {
            if (!is_file($valueFile) || !is_readable($valueFile)) {
                $output->writeln("<error>Could not read value file: {$valueFile}</error>");
                return null;
            }
            $contents = file_get_contents($valueFile);
            if ($contents === false) {
                $output->writeln("<error>Could not read value file: {$valueFile}</error>");
                return null;
            }
            return $this->stripTrailingNewline($contents);
        }

        if ($positional === null) {
            $output->writeln('<error>No value provided. Pass a value, use --value-file=<path>, or pipe via --value-file=- (or a bare \'-\').</error>');
            return null;
        }

        return $positional;
    }

    /**
     * Read all of stdin. Honors the input stream set by Symfony's CommandTester
     * (StreamableInputInterface) so the command is testable, falling back to the
     * real STDIN otherwise.
     */
    private function readStdin(InputInterface $input): string
    {
        $stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
        $stream = $stream ?? \STDIN;

        $contents = stream_get_contents($stream);

        return $contents === false ? '' : $contents;
    }

    /**
     * Strip exactly one trailing newline (\n, with an optional preceding \r) so that
     * `echo secret | buddy vars:set ...` and editor-saved files don't smuggle a
     * newline into the stored value. Intentional newlines beyond the last are kept.
     */
    private function stripTrailingNewline(string $value): string
    {
        return preg_replace('/\r?\n$/', '', $value, 1);
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
