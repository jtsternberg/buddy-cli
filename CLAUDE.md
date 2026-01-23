# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP CLI tool for Buddy.works CI/CD pipelines, built on `buddy-works/buddy-works-php-api` and `symfony/console`. Package name: `jtsternberg/buddy-cli`.

## Commands

```bash
# Install dependencies
composer install

# Run the CLI (after setup)
./bin/buddy <command>

# Run tests
./vendor/bin/phpunit

# Run a single test
./vendor/bin/phpunit tests/Path/To/TestFile.php
./vendor/bin/phpunit --filter testMethodName

# Code style (if configured)
./vendor/bin/php-cs-fixer fix
./vendor/bin/phpstan analyse
```

## Architecture

```
bin/buddy                    # Entry point
src/
├── Application.php          # Symfony Console application bootstrap
├── Commands/                # Command classes organized by resource
│   ├── Pipelines/           # pipelines:list, pipelines:run, pipelines:show, etc.
│   ├── Executions/          # executions:list, executions:show, executions:failed
│   ├── Projects/            # projects:list, projects:show
│   └── Config/              # config:show, config:set, config:clear
├── Services/
│   ├── BuddyService.php     # Wraps buddy-works/buddy-works-php-api SDK
│   └── ConfigService.php    # Manages config from env vars and config files
└── Output/
    ├── JsonFormatter.php    # --json flag output
    └── TableFormatter.php   # Human-readable table output
```

## Key Dependencies

**buddy-works/buddy-works-php-api**: Handles all Buddy API calls. Throws `BuddyResponseException` (API errors) and `BuddySDKException` (SDK errors). Read vendor source for actual method signatures.

**symfony/console**: Command framework. Commands extend `Symfony\Component\Console\Command\Command`. Use `InputInterface` for args/options, `OutputInterface` for output.

## Configuration Precedence

1. Command-line flags (`--workspace`, `--project`)
2. Environment variables (`BUDDY_TOKEN`, `BUDDY_WORKSPACE`, `BUDDY_PROJECT`)
3. Project config (`.buddy-cli.json`)
4. User config (`~/.config/buddy-cli/config.json`)

## Output Conventions

All commands support `--json` flag. Default is human-readable tables. JSON output must be valid and parseable for tool integration.

## Required API Token Scopes

`WORKSPACE`, `EXECUTION_INFO`, `EXECUTION_RUN`
