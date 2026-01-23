<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Config;

use BuddyCli\Commands\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:validate')
            ->setDescription('Validate configuration is complete and working')
            ->addOption('test-api', null, InputOption::VALUE_NONE, 'Test API connectivity')
            ->setHelp(<<<'HELP'
Validates that buddy-cli configuration is complete and ready for use.

Checks:
  - Token is configured (via config or BUDDY_TOKEN env var)
  - Workspace is configured (via config or BUDDY_WORKSPACE env var)
  - Project is configured (warns if missing)

Options:
      --test-api  Also verify the token works by calling the API

Examples:
  buddy config:validate
  buddy config:validate --test-api
HELP);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->getConfigService();
        $errors = [];
        $warnings = [];

        // Check token
        $token = $config->get('token');
        if ($token === null || $token === '') {
            $errors[] = [
                'field' => 'token',
                'message' => 'No API token configured',
                'help' => [
                    'Set via environment variable:',
                    '  export BUDDY_TOKEN=<your-token>',
                    '',
                    'Or via config:',
                    '  buddy config:set token <your-token>',
                    '',
                    'Get a token at: https://app.buddy.works/api-tokens',
                ],
            ];
        }

        // Check workspace
        $workspace = $config->get('workspace');
        if ($workspace === null || $workspace === '') {
            $errors[] = [
                'field' => 'workspace',
                'message' => 'No workspace configured',
                'help' => [
                    'Set via environment variable:',
                    '  export BUDDY_WORKSPACE=<workspace-name>',
                    '',
                    'Or via config:',
                    '  buddy config:set workspace <workspace-name>',
                ],
            ];
        }

        // Check project (warning only)
        $project = $config->get('project');
        if ($project === null || $project === '') {
            $warnings[] = [
                'field' => 'project',
                'message' => 'No project configured (required for most commands)',
                'help' => [
                    'Set via environment variable:',
                    '  export BUDDY_PROJECT=<project-name>',
                    '',
                    'Or via config:',
                    '  buddy config:set project <project-name>',
                ],
            ];
        }

        // Test API if requested and we have a token
        if ($input->getOption('test-api') && empty($errors)) {
            try {
                $this->getBuddyService()->getWorkspaces();
                $output->writeln('<info>✓</info> API connection successful');
            } catch (\Exception $e) {
                $errors[] = [
                    'field' => 'api',
                    'message' => 'API connection failed: ' . $e->getMessage(),
                    'help' => [
                        'Verify your token is valid and has not expired.',
                        'Get a new token at: https://app.buddy.works/api-tokens',
                    ],
                ];
            }
        }

        // JSON output
        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, [
                'valid' => empty($errors),
                'errors' => array_map(fn($e) => $e['message'], $errors),
                'warnings' => array_map(fn($w) => $w['message'], $warnings),
            ]);
            return empty($errors) ? self::SUCCESS : self::FAILURE;
        }

        // Output errors
        foreach ($errors as $error) {
            $output->writeln('<error>✗ ' . $error['message'] . '</error>');
            $output->writeln('');
            foreach ($error['help'] as $line) {
                $output->writeln('  ' . $line);
            }
            $output->writeln('');
        }

        // Output warnings
        foreach ($warnings as $warning) {
            $output->writeln('<comment>! ' . $warning['message'] . '</comment>');
            $output->writeln('');
            foreach ($warning['help'] as $line) {
                $output->writeln('  ' . $line);
            }
            $output->writeln('');
        }

        if (!empty($errors)) {
            return self::FAILURE;
        }

        if (empty($warnings)) {
            $output->writeln('<info>Configuration valid.</info>');
        } else {
            $output->writeln('<info>Configuration valid (with warnings).</info>');
        }

        return self::SUCCESS;
    }
}
