<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Pipelines;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('pipelines:show')
            ->setDescription('Show pipeline details')
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID')
            ->addOption('yaml', 'y', InputOption::VALUE_NONE, 'Output as YAML configuration')
            ->setHelp(<<<'HELP'
Display detailed information about a pipeline including its actions.

Default output shows pipeline metadata (status, trigger, branch) and a table
of configured actions. Use <info>--yaml</info> or <info>--json</info> for machine-readable formats.

Output Formats:
  (default)  Human-readable table with pipeline info and actions
  --yaml     Native Buddy YAML configuration (lossless)
  --json     Full API response as JSON

Options:
  -y, --yaml  Output as YAML configuration (to stdout, not a file)

Examples:
  buddy pipelines:show 12345 --project=my-project
  buddy pipelines:show 12345 --yaml
  buddy pipelines:show 12345 --json
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

        if ($input->getOption('yaml')) {
            $yaml = $this->getBuddyService()->getPipelineYaml($workspace, $project, $pipelineId);
            $output->write($yaml);
            return self::SUCCESS;
        }

        $pipeline = $this->getBuddyService()->getPipeline($workspace, $project, $pipelineId);
        $actions = $this->getBuddyService()->getPipelineActions($workspace, $project, $pipelineId);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $pipeline);
            return self::SUCCESS;
        }

        $data = [
            'ID' => $pipeline['id'] ?? '-',
            'Name' => $pipeline['name'] ?? '-',
            'Status' => $this->formatStatus($pipeline['last_execution_status'] ?? 'UNKNOWN'),
            'Trigger' => $pipeline['trigger_mode'] ?? '-',
            'Branch/Ref' => $pipeline['ref_name'] ?? '-',
            'Last Run' => $this->formatTime($pipeline['last_execution_date'] ?? null),
            'Created' => $this->formatTime($pipeline['create_date'] ?? null),
        ];

        TableFormatter::keyValue($output, $data, "Pipeline: {$pipeline['name']}");

        // Show actions if available
        $actionList = $actions['actions'] ?? [];

        if (!empty($actionList)) {
            $output->writeln('');
            $output->writeln('<info>Actions:</info>');

            $rows = [];
            foreach ($actionList as $action) {
                $rows[] = [
                    $action['id'] ?? '-',
                    $action['name'] ?? '-',
                    $action['type'] ?? '-',
                    $this->formatTriggerConditions($action['trigger_conditions'] ?? []),
                ];
            }

            TableFormatter::render($output, ['ID', 'Name', 'Type', 'Conditions'], $rows);
        }

        return self::SUCCESS;
    }

    private function formatTriggerConditions(array $conditions): string
    {
        if (empty($conditions)) {
            return '-';
        }

        $formatted = [];
        foreach ($conditions as $cond) {
            $type = $cond['trigger_condition'] ?? '';
            $key = $cond['trigger_variable_key'] ?? '';
            $value = $cond['trigger_variable_value'] ?? '';

            // Format as "VAR_IS_NOT:KEY=val" or similar
            if ($key) {
                $formatted[] = "{$type}:{$key}={$value}";
            } else {
                $formatted[] = $type;
            }
        }

        return implode(', ', $formatted);
    }

}
