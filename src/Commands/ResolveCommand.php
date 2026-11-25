<?php

declare(strict_types=1);

namespace BuddyCli\Commands;

use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResolveCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('resolve')
            ->setDescription('Resolve a Buddy.works URL, pipeline name or ID into CLI flags')
            ->addArgument('input', InputArgument::REQUIRED, 'Buddy.works URL, pipeline name, or pipeline ID')
            ->setHelp(<<<'HELP'
Turns what a user pastes into the values other commands take.

  URL    https://app.buddy.works/{workspace}/{project}/pipelines/pipeline/{id}/execution/{exec}?actionExecutionId={action}
         → workspace, project, pipeline, execution and action_execution (whichever the URL has).
           Execution IDs from URLs are hex hashes; other commands accept them as-is.
  ID     A number → pipeline.
  Name   Anything else is matched against the project's pipeline names, case-insensitively:
         an exact match wins, otherwise a single partial match. Needs --workspace/--project
         (or configured ones). Several or no matches fail and list the candidates.

Resolved values apply to the next command only; nothing is saved to config.

Examples:
  buddy resolve https://app.buddy.works/acme/web/pipelines/pipeline/506857 --json
  buddy resolve "deploy production" --project=web
  buddy resolve 506857
HELP);

        $this->addWorkspaceOption();
        $this->addProjectOption();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = trim((string) $input->getArgument('input'));

        try {
            $resolved = match (true) {
                (bool) preg_match('#^https?://#i', $raw) => $this->fromUrl($raw),
                ctype_digit($raw) => ['source' => 'id', 'pipeline' => (int) $raw],
                default => $this->fromName($raw, $input),
            };
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return self::FAILURE;
        }

        $resolved['flags'] = $this->flags($resolved);

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $resolved);
        } else {
            TableFormatter::keyValue($output, array_map('strval', $resolved), 'Resolved');
        }

        return self::SUCCESS;
    }

    private function fromUrl(string $url): array
    {
        $path = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH)), 'strlen'));
        if (count($path) < 2) {
            throw new \InvalidArgumentException("Not a Buddy.works project URL: {$url}");
        }

        $resolved = ['source' => 'url', 'workspace' => $path[0], 'project' => $path[1]];

        $pipelineAt = array_search('pipeline', $path, true);
        if ($pipelineAt !== false && isset($path[$pipelineAt + 1]) && ctype_digit($path[$pipelineAt + 1])) {
            $resolved['pipeline'] = (int) $path[$pipelineAt + 1];
        }

        $executionAt = array_search('execution', $path, true);
        if ($executionAt !== false && isset($path[$executionAt + 1])) {
            $resolved['execution'] = $path[$executionAt + 1];
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (!empty($query['actionExecutionId']) && is_string($query['actionExecutionId'])) {
            $resolved['action_execution'] = $query['actionExecutionId'];
        }

        return $resolved;
    }

    private function fromName(string $name, InputInterface $input): array
    {
        $workspace = $this->requireWorkspace($input);
        $project = $this->requireProject($input);
        $pipelines = $this->getBuddyService()->getAllPipelines($workspace, $project)['pipelines'] ?? [];

        $needle = mb_strtolower($name);
        $exact = array_filter($pipelines, fn ($p) => mb_strtolower((string) ($p['name'] ?? '')) === $needle);
        $matches = $exact ?: array_filter($pipelines, fn ($p) => str_contains(mb_strtolower((string) ($p['name'] ?? '')), $needle));

        if (count($matches) !== 1) {
            $message = $matches === []
                ? "No pipeline matches \"{$name}\". Available:"
                : "\"{$name}\" matches " . count($matches) . ' pipelines; use the ID:';
            $list = array_map(fn ($p) => "  {$p['id']}  {$p['name']}", $matches ?: $pipelines);
            throw new \InvalidArgumentException($message . "\n" . implode("\n", $list));
        }

        $match = reset($matches);

        return [
            'source' => 'name',
            'workspace' => $workspace,
            'project' => $project,
            'pipeline' => (int) $match['id'],
            'pipeline_name' => (string) $match['name'],
        ];
    }

    private function flags(array $resolved): string
    {
        $flags = [];
        if (isset($resolved['workspace'])) {
            $flags[] = '-w ' . $resolved['workspace'];
        }
        if (isset($resolved['project'])) {
            $flags[] = '-p ' . $resolved['project'];
        }
        if (isset($resolved['pipeline'])) {
            $flags[] = '--pipeline=' . $resolved['pipeline'];
        }

        return implode(' ', $flags);
    }
}
