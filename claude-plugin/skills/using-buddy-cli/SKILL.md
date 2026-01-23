---
name: using-buddy-cli
description: Manages CI/CD pipelines via the buddy CLI tool. Use when running deployments, checking pipeline status, viewing execution logs, or managing Buddy.works configuration.
---

# Using buddy-cli

The `buddy` CLI manages Buddy.works CI/CD pipelines from the command line.

## Prerequisites

Configuration must be set before use:

```bash
# Authentication (required)
export BUDDY_TOKEN=<token>  # or: buddy config:set token <token>

# Context (required for most commands)
export BUDDY_WORKSPACE=<name>  # or: buddy config:set workspace <name>
export BUDDY_PROJECT=<name>    # or: buddy config:set project <name>
```

Validate with: `${CLAUDE_PLUGIN_ROOT}/scripts/validate_config.sh`

## Quick Reference

### Pipelines
```bash
buddy pipelines:list                    # List all pipelines
buddy pipelines:show <id>               # Show pipeline details
buddy pipelines:run <id>                # Run a pipeline
buddy pipelines:run <id> --wait         # Run and wait for completion
buddy pipelines:retry <id>              # Retry last failed execution
buddy pipelines:cancel <id>             # Cancel running execution
```

### Executions
```bash
buddy executions:list --pipeline=<id>   # List recent executions
buddy executions:show <id> --pipeline=<id>           # Show execution details
buddy executions:show <id> --pipeline=<id> --logs    # Include action logs
buddy executions:failed <id> --pipeline=<id>         # Show failed action details
```

### Configuration
```bash
buddy config:show                       # Show current configuration
buddy config:set <key> <value>          # Set configuration value
```

## JSON Output

Add `--json` to any command for machine-readable output:

```bash
buddy pipelines:list --json | jq '.[] | {id, name, status}'
buddy executions:show <id> --pipeline=<id> --json | python ${CLAUDE_PLUGIN_ROOT}/scripts/format_status.py
```

## Common Workflows

### Run a deployment
```bash
# Find pipeline ID
buddy pipelines:list

# Run and monitor
buddy pipelines:run <id> --wait
```

### Check recent activity
```bash
buddy executions:list --pipeline=<id>
buddy executions:show <exec-id> --pipeline=<id>
```

See COMMANDS.md for full command reference, WORKFLOWS.md for detailed patterns.
