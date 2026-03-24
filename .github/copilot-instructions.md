# Copilot Code Review Instructions

## Project Context

PHP CLI tool (`jtsternberg/buddy-cli`) for managing Buddy.works CI/CD pipelines. Built on `symfony/console` and `buddy-works/buddy-works-php-api`. Commands live in `src/Commands/`, organized by resource (Pipelines, Executions, Actions, Projects, Config, Variables, Webhooks, Auth, Self).

This repo also bundles a **Claude Code plugin** at `claude-plugin/` with skills, slash commands, and subagents that mirror CLI capabilities.

## Test-Driven Development (TDD) — Required

Every PR that adds or changes functionality **must** include tests.

- Tests use PHPUnit and live in `tests/`.
- If a PR modifies command behavior, adds flags/options, or fixes bugs, and no test changes are present — **flag this as a blocking concern**.
- Exception: purely cosmetic changes (formatting, comments, docs-only) don't require tests.

## Claude Code Plugin — Must Stay in Sync with CLI

This repo bundles a Claude Code plugin (`claude-plugin/`) that teaches Claude how to use this CLI tool. The plugin's skills, commands, and agents serve as Claude's documentation — they describe available commands, flags, expected output, and usage patterns. **If the CLI changes but the plugin doesn't, Claude will give users incorrect instructions.**

When a PR changes CLI behavior, flag it if the plugin isn't also updated:

- **New commands or subcommands** → The plugin skills (`claude-plugin/skills/`) must document them so Claude knows they exist and how to use them. A new slash command in `claude-plugin/commands/` may also be warranted.
- **New, changed, or removed flags/options** → Skills reference specific flags and syntax. Stale flag references mean Claude will suggest commands that don't work.
- **Changed output formats** → Skills and agents describe what output to expect. If output changes, Claude will misparse results or give users wrong guidance.
- **Renamed or removed commands** → Any old names still referenced in the plugin become dead instructions.

If CLI functionality changes and no corresponding plugin updates are included, **flag this as a blocking concern** — it means Claude users will get broken guidance.

## Documentation

PRs that add commands, change behavior, or modify options should update:

- `README.md` — command reference and examples
- `--help` output — description and option text in the command class itself
- `CLAUDE.md` — if architecture or conventions changed

If relevant docs aren't updated, flag it.

## Shell Completions

This project does not yet have shell completions. If/when they exist, PRs that add, rename, or remove commands or options must update completions too.

## Code Style

- PHP code follows PSR-12 with tabs (size 3) for indentation
- Code style is enforced via `pint.json` (Laravel Pint)
- All commands must support `--json` for machine-readable output

## Output Conventions

- Human-readable tables are the default output format
- `--json` output must be valid, parseable JSON
- Error messages should be descriptive and actionable
