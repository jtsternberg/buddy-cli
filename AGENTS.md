# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP CLI tool for Buddy.works CI/CD pipelines, built on `buddy-works/buddy-works-php-api` and `symfony/console`. Package name: `jtsternberg/buddy-cli`.

## Special Instructions

- **Use 'bd' for task tracking**

## Commands

```bash
# Install dependencies
composer install

# Run the CLI
buddy <command>                # Global (after self:install)
./bin/buddy <command>          # Development

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

## Landing the Plane (Session Completion)

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd sync
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds

<!-- bv-agent-instructions-v1 -->

---

## Beads Workflow Integration

This project uses [beads_viewer](https://github.com/Dicklesworthstone/beads_viewer) for issue tracking. Issues are stored in `.beads/` and tracked in git.

### Essential Commands

```bash
# View issues (launches TUI - avoid in automated sessions)
bv

# CLI commands for agents (use these instead)
bd ready              # Show issues ready to work (no blockers)
bd list --status=open # All open issues
bd show <id>          # Full issue details with dependencies
bd create --title="..." --type=task --priority=2
bd update <id> --status=in_progress
bd close <id> --reason="Completed"
bd close <id1> <id2>  # Close multiple issues at once
bd sync               # Commit and push changes
```

### Workflow Pattern

1. **Start**: Run `bd ready` to find actionable work
2. **Claim**: Use `bd update <id> --status=in_progress`
3. **Work**: Implement the task
4. **Complete**: Use `bd close <id>`
5. **Sync**: Always run `bd sync` at session end

### Key Concepts

- **Dependencies**: Issues can block other issues. `bd ready` shows only unblocked work.
- **Priority**: P0=critical, P1=high, P2=medium, P3=low, P4=backlog (use numbers, not words)
- **Types**: task, bug, feature, epic, question, docs
- **Blocking**: `bd dep add <issue> <depends-on>` to add dependencies

### Session Protocol

**Before ending any session, run this checklist:**

```bash
git status              # Check what changed
git add <files>         # Stage code changes
bd sync                 # Commit beads changes
git commit -m "..."     # Commit code
bd sync                 # Commit any new beads changes
git push                # Push to remote
```

### Best Practices

- Check `bd ready` at session start to find available work
- Update status as you work (in_progress → closed)
- Create new issues with `bd create` when you discover tasks
- Use descriptive titles and set appropriate priority/type
- Always `bd sync` before ending session

<!-- end-bv-agent-instructions -->
