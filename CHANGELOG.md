# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-03-24

### Added

- Hash-to-integer execution ID resolution — CLI now accepts hex hash execution IDs from Buddy URLs and resolves them automatically via the API
- `--action` scope for `vars:set` command, enabling action-level variable management (requires `--pipeline`)
- Scope validation for `vars:set` — rejects conflicting scopes (e.g., `--project` + `--pipeline`) with a clear error message
- Heap overflow and GC exhaustion patterns to `executions:failed --analyze` error detection
- Helpful error messages for `pipelines:retry` on wildcard pipelines, suggesting `pipelines:run --branch=` as alternative
- Comprehensive test coverage for all new code changes (16 new tests, 47 new assertions)
- `VariablesCommandsTest` integration test suite

### Changed

- Troubleshooting skill restructured to lead with log-fetching commands ("FIRST: Get the Logs")
- CICD specialist agent updated with gotchas section and stronger emphasis on reading logs before suggesting fixes
- URL parser agent simplified — no longer needs manual hash resolution since CLI handles it natively
- `vars:set` help text now documents the `--` separator gotcha for values containing dashes
- `executions:failed --analyze` improved "Unidentified" fallback to search for keyword-bearing lines instead of blindly showing last 5 lines

### Fixed

- `resolveExecutionId()` now checks both API URL and HTML URL fields when matching hashes (bug caught by TDD)
- `executions:failed --analyze` no longer produces useless "ERROR" output for heap overflow failures
- `vars:set` help text now accurately documents scope rules and the `--` separator requirement

## [1.2.0] - 2026-02-10

### Added

- URL parser agent for Buddy.works URL and pipeline input resolution
- Webhook management commands (`webhooks:list`, `webhooks:show`, `webhooks:create`, `webhooks:update`, `webhooks:delete`)
- Native Buddy YAML pipeline support (replaces custom YAML format)
- `EXECUTION_MANAGE` scope to default OAuth scopes
- Integration tests for `pipelines:show` and `login` commands
- Integration and unit tests for remaining commands
- Unit tests for OAuthService

### Changed

- Pipelines now use native Buddy YAML format
- GitHub release notes reuse changelog content from release process

## [1.1.0] - 2026-01-27

### Added

- `.env` file support for configuration (BUDDY_TOKEN, BUDDY_WORKSPACE, BUDDY_PROJECT, etc.)
- Recursive `.env` loading from parent directories (child values take precedence)
- Source tracking in `config:show` - displays which `.env` file each value comes from
- `/publish-release` command for automated releases

### Changed

- `config:show` now displays relative paths for env values (e.g., `./.env`, `../.env`)

## [1.0.0] - 2026-01-23

Initial public release of Buddy CLI.

### Added

#### Core
- Symfony Console-based CLI application
- OAuth authentication with local callback server
- Automatic token refresh on 401 errors
- Global installation via `self:install` command
- Shell completion support (bash/zsh/fish)
- JSON output format for all commands (`--json`)
- Configuration stored in `~/.config/buddy-cli/config.json`

#### Authentication
- `login` - OAuth browser flow with local callback server
- `logout` - Clear stored credentials
- `--test` flag to verify callback server setup
- `--no-browser` flag for headless/SSH environments
- `--port` flag to customize callback port

#### Pipeline Commands
- `pipelines:list` - List all pipelines in a project
- `pipelines:show` - Display pipeline details with actions
- `pipelines:get` - Export pipeline config as YAML
- `pipelines:create` - Create pipeline from YAML config
- `pipelines:update` - Update pipeline from YAML config
- `pipelines:run` - Trigger pipeline execution
- `pipelines:retry` - Retry failed execution
- `pipelines:cancel` - Cancel running execution
- `--var` option for passing variables to runs
- `--wait` option to block until execution completes
- `--yaml` output format for show command

#### Action Commands
- `actions:list` - List actions in a pipeline
- `actions:show` - Display action details
- `actions:create` - Create action from YAML
- `actions:update` - Update action from YAML
- `actions:delete` - Remove action from pipeline

#### Variable Commands
- `vars:list` - List project/pipeline variables
- `vars:show` - Display variable details
- `vars:set` - Create or update variable
- `vars:delete` - Remove variable

#### Execution Commands
- `executions:list` - List pipeline executions
- `executions:show` - Display execution details with action logs
- `executions:failed` - Show failed executions
- `--summary` flag for concise output
- `--analyze` flag for failure analysis

#### Configuration Commands
- `config:show` - Display current configuration
- `config:set` - Store configuration values
- `config:clear` - Remove all configuration
- `config:validate` - Validate configuration

#### Project Commands
- `projects:list` - List workspace projects
- `projects:show` - Display project details

#### Claude Code Plugin
- Full plugin for Claude Code AI assistant
- Skills: `/deploy`, `/status`, `/logs`
- CI/CD specialist subagent
- Troubleshooting workflows
- Marketplace registration

#### Documentation
- Comprehensive README with installation and usage
- Detailed `--help` text for all commands
- Pipeline debugging workflow guide
- Shell completion instructions

### Fixed
- Help output shows `buddy` instead of full binary path
- Symfony 8 compatibility (deprecated `add()` method)
