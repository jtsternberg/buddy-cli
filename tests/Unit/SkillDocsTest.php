<?php

declare(strict_types=1);

namespace BuddyCli\Tests\Unit;

use BuddyCli\Application;
use BuddyCli\Tests\TestCase;

/**
 * The shipped plugin is how agents learn the CLI, and it drifted silently for
 * several releases (webhooks, --follow, an auth:login heading). The skill must
 * mention every command and option, and the plugin must name only real commands.
 */
class SkillDocsTest extends TestCase
{
    private const PLUGIN_DIR = __DIR__ . '/../../claude-plugin';

    private const SKILL_DIR = self::PLUGIN_DIR . '/skills/using-buddy-cli';

    private const BUILTIN_COMMANDS = ['help', 'list', 'completion', '_complete'];

    // Shared by every resource command and covered once in SKILL.md.
    private const COMMON_OPTIONS = ['json', 'workspace', 'project'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTempDir();
        $this->setEnv('HOME', $this->tempDir);
    }

    public function testEveryCommandAndOptionIsInTheSkill(): void
    {
        $docs = '';
        foreach (glob(self::SKILL_DIR . '/*.md') as $file) {
            $docs .= file_get_contents($file);
        }

        $app = new Application();
        $globalOptions = array_keys($app->getDefinition()->getOptions());
        $missing = [];

        foreach ($app->all() as $name => $command) {
            if ($command->getName() !== $name || in_array($name, self::BUILTIN_COMMANDS, true)) {
                continue;
            }

            if (!str_contains($docs, $name)) {
                $missing[] = $name;
            }

            foreach ($command->getDefinition()->getOptions() as $option) {
                $optionName = $option->getName();
                if (in_array($optionName, self::COMMON_OPTIONS, true) || in_array($optionName, $globalOptions, true)) {
                    continue;
                }
                if (!str_contains($docs, '--' . $optionName)) {
                    $missing[] = "{$name} --{$optionName}";
                }
            }
        }

        $this->assertSame([], $missing, 'Undocumented in claude-plugin/skills/using-buddy-cli: ' . implode(', ', $missing));
    }

    public function testEveryDocumentedCommandExists(): void
    {
        $app = new Application();
        $bad = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::PLUGIN_DIR, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            foreach (file($file->getPathname()) as $i => $line) {
                preg_match_all('/\bbuddy\s+([a-z]+:[a-z-]+)/', $line, $invoked);
                preg_match('/^#{2,4}\s+`?([a-z]+:[a-z-]+)`?\s*$/', $line, $heading);
                foreach (array_merge($invoked[1], array_slice($heading, 1)) as $name) {
                    if (!$app->has($name)) {
                        $bad[] = substr($file->getPathname(), strlen(self::PLUGIN_DIR) + 1) . ':' . ($i + 1) . " {$name}";
                    }
                }
            }
        }

        $this->assertSame([], $bad, 'Docs name commands that do not exist: ' . implode(', ', $bad));
    }
}
