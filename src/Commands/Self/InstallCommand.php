<?php

declare(strict_types=1);

namespace BuddyCli\Commands\Self;

use BuddyCli\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('self:install')
            ->setDescription('Install buddy command globally for pathless execution')
            ->addOption('bin-dir', null, InputOption::VALUE_REQUIRED, 'Target bin directory (must be in PATH)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing symlink');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->getBuddyBinPath();
        if ($source === null) {
            $output->writeln('<error>Could not locate buddy executable</error>');
            return self::FAILURE;
        }

        $targetDir = $input->getOption('bin-dir');
        if ($targetDir !== null) {
            if (!is_dir($targetDir)) {
                $output->writeln("<error>Directory does not exist: {$targetDir}</error>");
                return self::FAILURE;
            }
            if (!$this->isInPath($targetDir)) {
                $output->writeln("<comment>Warning: {$targetDir} is not in your PATH</comment>");
            }
        } else {
            $targetDir = $this->findBinDirectory();
            if ($targetDir === null) {
                $output->writeln('<error>No suitable bin directory found in PATH</error>');
                $output->writeln('');
                $output->writeln('Searched for:');
                foreach ($this->getCandidateBinDirs() as $dir) {
                    $output->writeln("  - {$dir}");
                }
                $output->writeln('');
                $output->writeln('Create one of these directories and add it to your PATH, or use --bin-dir');
                return self::FAILURE;
            }
        }

        $target = rtrim($targetDir, '/') . '/buddy';

        // Check if target already exists
        if (file_exists($target) || is_link($target)) {
            if (!$input->getOption('force')) {
                $existing = is_link($target) ? readlink($target) : 'file';
                $output->writeln("<comment>Target already exists: {$target}</comment>");
                $output->writeln("  Currently points to: {$existing}");
                $output->writeln('');
                $output->writeln('Use --force to overwrite');
                return self::FAILURE;
            }
            unlink($target);
        }

        // Create symlink
        if (!@symlink($source, $target)) {
            $output->writeln("<error>Failed to create symlink: {$target}</error>");
            $output->writeln('You may need to run with elevated permissions');
            return self::FAILURE;
        }

        $output->writeln('<info>Installed successfully!</info>');
        $output->writeln("  {$target} -> {$source}");
        $output->writeln('');
        $output->writeln('You can now run: <comment>buddy --help</comment>');

        return self::SUCCESS;
    }

    private function getBuddyBinPath(): ?string
    {
        // Get the real path to bin/buddy relative to this file
        $path = realpath(__DIR__ . '/../../../bin/buddy');
        return $path !== false ? $path : null;
    }

    /**
     * @return string[]
     */
    private function getCandidateBinDirs(): array
    {
        $home = getenv('HOME') ?: '';
        return array_filter([
            $home . '/.local/bin',
            $home . '/bin',
            '/usr/local/bin',
        ]);
    }

    private function findBinDirectory(): ?string
    {
        foreach ($this->getCandidateBinDirs() as $dir) {
            if (is_dir($dir) && is_writable($dir) && $this->isInPath($dir)) {
                return $dir;
            }
        }
        return null;
    }

    private function isInPath(string $dir): bool
    {
        $path = getenv('PATH') ?: '';
        $realDir = realpath($dir);

        foreach (explode(PATH_SEPARATOR, $path) as $pathDir) {
            $realPathDir = realpath($pathDir);
            if ($realPathDir !== false && $realDir !== false && $realPathDir === $realDir) {
                return true;
            }
            // Also check string match for non-existent dirs
            if ($pathDir === $dir) {
                return true;
            }
        }
        return false;
    }
}
