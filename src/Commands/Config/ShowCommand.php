<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Config;

use BuddyCli\Commands\BaseCommand;
use BuddyCli\Output\TableFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:show')
            ->setDescription('Show current configuration');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configService = $this->getConfigService();
        $configWithSources = $configService->allWithSources();

        if ($this->isJsonOutput($input)) {
            $this->outputJson($output, $configService->all());
            return self::SUCCESS;
        }

        if (empty($configWithSources)) {
            $output->writeln('<comment>No configuration set.</comment>');
            $output->writeln('');
            $output->writeln('Set configuration with:');
            $output->writeln('  buddy config:set token <your-api-token>');
            $output->writeln('  buddy config:set workspace <workspace-name>');
            $output->writeln('  buddy config:set project <project-name>');
            return self::SUCCESS;
        }

        // Build display rows with source indicator
        $rows = [];
        foreach ($configWithSources as $key => $data) {
            $value = $data['value'];
            $source = $data['source'];

            // Mask tokens for display
            if (($key === 'token' || $key === 'refresh_token') && strlen($value) > 8) {
                $value = substr($value, 0, 4) . '...' . substr($value, -4);
            }

            // Add source indicator for env values
            if ($source === 'env') {
                $path = $data['path'] ?? null;
                if ($path !== null) {
                    $relativePath = $this->getRelativePath($path);
                    $value .= " <comment>({$relativePath})</comment>";
                } else {
                    $value .= ' <comment>(env)</comment>';
                }
            }

            $rows[$key] = $value;
        }

        TableFormatter::keyValue($output, $rows, 'Current Configuration');

        return self::SUCCESS;
    }

    /**
     * Get a relative path from cwd to the given absolute path.
     */
    private function getRelativePath(string $absolutePath): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return $absolutePath;
        }

        // Resolve symlinks for comparison
        $realCwd = realpath($cwd) ?: $cwd;
        $realPath = realpath(dirname($absolutePath)) ?: dirname($absolutePath);
        $filename = basename($absolutePath);

        // Same directory
        if ($realPath === $realCwd) {
            return "./{$filename}";
        }

        // Check if path is under cwd
        if (str_starts_with($realPath, $realCwd . '/')) {
            return './' . substr($realPath, strlen($realCwd) + 1) . '/' . $filename;
        }

        // Check if path is a parent of cwd
        if (str_starts_with($realCwd, $realPath . '/')) {
            $depth = substr_count(substr($realCwd, strlen($realPath)), '/');
            return str_repeat('../', $depth) . $filename;
        }

        // Fall back to absolute path
        return $absolutePath;
    }
}
