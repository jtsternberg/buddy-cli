# buddy-cli Claude Plugin

Claude Code plugin for Buddy.works CI/CD pipeline management.

## Installation

### Local Development

```bash
claude --plugin-dir /path/to/buddy-cli
```

### Project Settings

Add to `.claude/settings.json`:

```json
{
  "plugins": ["/path/to/buddy-cli"]
}
```

## Prerequisites

The plugin requires buddy-cli to be installed and configured:

```bash
# Install buddy-cli
composer require jtsternberg/buddy-cli --dev

# Configure authentication
export BUDDY_TOKEN=<your-token>
# Or: ./bin/buddy config:set token <your-token>

# Set workspace and project
export BUDDY_WORKSPACE=<workspace>
export BUDDY_PROJECT=<project>
```

## Features

### Skills (Auto-Invoked)

**using-buddy-cli** - Automatically invoked when discussing:
- Pipeline deployments
- Execution status
- buddy-cli commands

**troubleshooting-pipelines** - Automatically invoked when:
- Builds fail
- Executions error
- Debugging deployments

### Commands (User-Invoked)

| Command | Description |
|---------|-------------|
| `/buddy-cli:deploy` | Run a pipeline deployment |
| `/buddy-cli:status` | Show execution status |
| `/buddy-cli:logs` | View execution logs |

### Agents

**cicd-specialist** - Specialized agent for:
- CI/CD workflow automation
- Pipeline troubleshooting
- Log analysis

## Usage Examples

### Run a deployment
```
/buddy-cli:deploy production --wait
```

### Check status
```
/buddy-cli:status
```

### View failure logs
```
/buddy-cli:logs --errors-only
```

### Natural language (skills auto-invoke)
- "Deploy to production"
- "Why did my build fail?"
- "Show me the pipeline status"

## Configuration

The plugin uses buddy-cli's configuration. Set via:

1. Environment variables: `BUDDY_TOKEN`, `BUDDY_WORKSPACE`, `BUDDY_PROJECT`
2. Config command: `buddy config:set <key> <value>`
3. Project file: `.buddy-cli.json`

## Testing

### Verify Plugin Loads

```bash
claude --plugin-dir /path/to/buddy-cli --print-plugins
```

### Test Commands

```bash
claude --plugin-dir /path/to/buddy-cli
> /buddy-cli:status
```

### Test Skills

Ask Claude about deployments or pipeline failures - the skills should auto-invoke.

### Troubleshooting

- **Plugin not loading**: Check plugin.json is valid JSON
- **Commands not found**: Ensure commands/ directory has .md files
- **Skills not invoking**: Check skill description matches use case
