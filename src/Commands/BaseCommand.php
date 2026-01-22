<?php

declare(strict_types=1);

namespace BuddyCli\Commands;

use BuddyCli\Application;
use BuddyCli\Output\JsonFormatter;
use BuddyCli\Services\BuddyService;
use BuddyCli\Services\ConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function getConfigService(): ConfigService
    {
        return $this->app->getConfigService();
    }

    protected function getBuddyService(): BuddyService
    {
        return $this->app->getBuddyService();
    }

    protected function isJsonOutput(InputInterface $input): bool
    {
        return (bool) $input->getOption('json');
    }

    protected function outputJson(OutputInterface $output, mixed $data): void
    {
        JsonFormatter::output($output, $data);
    }

    protected function requireWorkspace(InputInterface $input): string
    {
        $workspace = $input->getOption('workspace') ?? $this->getConfigService()->get('workspace');

        if ($workspace === null) {
            throw new \RuntimeException(
                "No workspace specified. Use --workspace option, set BUDDY_WORKSPACE env var, or run 'buddy config:set workspace <name>'"
            );
        }

        return $workspace;
    }

    protected function requireProject(InputInterface $input): string
    {
        $project = $input->getOption('project') ?? $this->getConfigService()->get('project');

        if ($project === null) {
            throw new \RuntimeException(
                "No project specified. Use --project option, set BUDDY_PROJECT env var, or run 'buddy config:set project <name>'"
            );
        }

        return $project;
    }

    protected function addWorkspaceOption(): static
    {
        $this->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Workspace name');
        return $this;
    }

    protected function addProjectOption(): static
    {
        $this->addOption('project', 'p', InputOption::VALUE_REQUIRED, 'Project name');
        return $this;
    }

    protected function addPipelineOption(): static
    {
        $this->addOption('pipeline', null, InputOption::VALUE_REQUIRED, 'Pipeline ID');
        return $this;
    }

    protected function requirePipeline(InputInterface $input): int
    {
        $pipelineId = $input->getOption('pipeline');

        if ($pipelineId === null) {
            throw new \RuntimeException('Pipeline ID is required. Use --pipeline=<id>');
        }

        return (int) $pipelineId;
    }

    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'SUCCESSFUL' => '<fg=green>SUCCESSFUL</>',
            'FAILED' => '<fg=red>FAILED</>',
            'INPROGRESS' => '<fg=yellow>INPROGRESS</>',
            'ENQUEUED' => '<fg=cyan>ENQUEUED</>',
            'TERMINATED' => '<fg=red>TERMINATED</>',
            'SKIPPED' => '<fg=gray>SKIPPED</>',
            default => $status,
        };
    }

    protected function formatTime(?string $datetime): string
    {
        if ($datetime === null) {
            return '-';
        }

        try {
            $date = new \DateTimeImmutable($datetime);
            $now = new \DateTimeImmutable();
            $diff = $now->diff($date);

            if ($diff->days === 0) {
                if ($diff->h === 0) {
                    if ($diff->i === 0) {
                        return 'just now';
                    }
                    return $diff->i . ' min ago';
                }
                return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            }

            if ($diff->days === 1) {
                return 'yesterday';
            }

            if ($diff->days < 7) {
                return $diff->days . ' days ago';
            }

            return $date->format('Y-m-d H:i');
        } catch (\Exception) {
            return $datetime;
        }
    }

    protected function formatDuration(?string $start, ?string $finish): string
    {
        if ($start === null) {
            return '-';
        }

        try {
            $startDate = new \DateTimeImmutable($start);
            $endDate = $finish !== null ? new \DateTimeImmutable($finish) : new \DateTimeImmutable();
            $diff = $endDate->diff($startDate);

            if ($diff->h > 0) {
                return sprintf('%dh %dm', $diff->h, $diff->i);
            }
            if ($diff->i > 0) {
                return sprintf('%dm %ds', $diff->i, $diff->s);
            }
            return sprintf('%ds', $diff->s);
        } catch (\Exception) {
            return '-';
        }
    }
}
