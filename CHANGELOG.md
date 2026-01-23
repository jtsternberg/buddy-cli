# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
