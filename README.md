# The (Un)official Buddy Works CLI

![buddy-cli](.github/buddy-cli-splash.jpg)

A PHP CLI tool for interacting with [Buddy.works](https://buddy.works) CI/CD pipelines.

> **Note:** The official [buddy-works/buddy-cli](https://github.com/buddy-works/buddy-cli) has been abandoned. This project provides a maintained alternative.

## Installation

```bash
composer require jtsternberg/buddy-cli --dev
```

After installation, the `buddy` command is available via `vendor/bin/buddy`.

## Authentication

### Personal Access Token (Recommended)

Set the `BUDDY_TOKEN` environment variable, or run:

```bash
buddy config:set token <your-token>
```

### OAuth Login

```bash
buddy login
```

This opens your browser to authenticate with Buddy and automatically saves your token.

**Setup:** Create an OAuth application at [buddy.works/api/apps](https://app.buddy.works/api/apps) with callback URL `http://127.0.0.1:8085/callback`.

## Configuration

Configuration can be set via environment variables, config files, or command-line flags.

### Environment Variables

```bash
BUDDY_TOKEN=<token>
BUDDY_WORKSPACE=<workspace-name>
BUDDY_PROJECT=<project-name>
```

### Config Files

- User config: `~/.config/buddy-cli/config.json`
- Project config: `.buddy-cli.json` (in project root)

```json
{
  "workspace": "my-workspace",
  "project": "my-project"
}
```

### Precedence

1. Command-line flags (`--workspace`, `--project`)
2. Environment variables
3. Project config (`.buddy-cli.json`)
4. User config (`~/.config/buddy-cli/config.json`)

## Commands

> [!TIP]
> Run `buddy <command> --help` for detailed documentation including all options, supported fields, and examples.

### Pipelines

```bash
buddy pipelines:list                      # List all pipelines
buddy pipelines:show <id>                 # Show pipeline details
buddy pipelines:show <id> --yaml          # Output as YAML configuration
buddy pipelines:run <id>                  # Run a pipeline
buddy pipelines:run <id> --branch=main    # Run with specific branch
buddy pipelines:run <id> --wait           # Run and wait for completion
buddy pipelines:retry <id>                # Retry last failed execution
buddy pipelines:cancel <id>               # Cancel running execution
buddy pipelines:get <id>                  # Get pipeline config as YAML file
buddy pipelines:create <file>             # Create new pipeline from YAML file
buddy pipelines:update <id> <file>        # Update existing pipeline from YAML
```

### Executions

```bash
buddy executions:list --pipeline=<id>              # List recent executions
buddy executions:show <exec-id> --pipeline=<id>    # Show execution details
buddy executions:show <exec-id> --pipeline=<id> --logs  # Include action logs
buddy executions:failed <exec-id> --pipeline=<id>  # Show failed action details
```

### Actions

```bash
buddy actions:list --pipeline=<id>                 # List actions in a pipeline
buddy actions:show <action-id> --pipeline=<id>     # Show action details
buddy actions:show <action-id> --pipeline=<id> --yaml  # Output as YAML configuration
buddy actions:create <file> --pipeline=<id>        # Create new action from YAML file
buddy actions:update <action-id> <file> --pipeline=<id>  # Update action from YAML
buddy actions:delete <action-id> --pipeline=<id>   # Delete action (with confirmation)
buddy actions:delete <action-id> --pipeline=<id> --force  # Delete without confirmation
```

### Projects

```bash
buddy projects:list                       # List projects in workspace
buddy projects:show <name>                # Show project details
```

### Variables

```bash
buddy vars:list                           # List environment variables
buddy vars:list --project=<name>          # Filter by project
buddy vars:list --pipeline=<id>           # Filter by pipeline
buddy vars:show <id>                      # Show variable details
buddy vars:set <key> <value>              # Create or update variable
buddy vars:set <key> <value> -p <project> # Scope to project
buddy vars:set <key> <value> --encrypted  # Encrypt the value
buddy vars:delete <id>                    # Delete variable (with confirmation)
buddy vars:delete <id> --force            # Delete without confirmation
```

### Configuration

```bash
buddy config:show                         # Show current configuration
buddy config:set <key> <value>            # Set configuration value
buddy config:clear                        # Clear all configuration
```

## Options

All commands support:

- `--workspace`, `-w` - Workspace name
- `--project`, `-p` - Project name
- `--json` - Output as JSON

## License

MIT
