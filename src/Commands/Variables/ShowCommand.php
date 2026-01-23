<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Variables;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('vars:show')
            ->setDescription('Show variable details')
            ->addArgument('variable-id', InputArgument::REQUIRED, 'Variable ID')
            ->setHelp(<<<'HELP'
Displays detailed information about a specific environment variable.

Shows ID, key, type, encryption status, settable flag, description, and scope.
For non-encrypted variables, the value is also displayed.

Example:
  buddy vars:show 12345
HELP);

        $this->addWorkspaceOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspace = $this->requireWorkspace($input);
        $variableId = (int) $input->getArgument('variable-id');

        $variable = $this->getBuddyService()->getVariable($workspace, $variableId);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $variable);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $variable['id'] ?? '-',
            'Key' => $variable['key'] ?? '-',
            'Type' => $variable['type'] ?? 'VAR',
            'Encrypted' => ($variable['encrypted'] ?? false) ? 'Yes' : 'No',
            'Settable' => ($variable['settable'] ?? false) ? 'Yes' : 'No',
            'Description' => $variable['description'] ?? '-',
            'Scope' => $this->getScope($variable),
        ];

        if (!($variable['encrypted'] ?? false) && isset($variable['value'])) {
            $data['Value'] = $variable['value'];
        }

        TableFormatter::keyValue($output, $data, "Variable: {$variable['key']}");

        return self::SUCCESS;
    }

    private function getScope(array $variable): string
    {
        if (isset($variable['action']['id'])) {
            return 'action:' . $variable['action']['id'];
        }
        if (isset($variable['pipeline']['id'])) {
            return 'pipeline:' . $variable['pipeline']['id'];
        }
        if (isset($variable['project']['name'])) {
            return 'project:' . $variable['project']['name'];
        }
        return 'workspace';
    }
}
