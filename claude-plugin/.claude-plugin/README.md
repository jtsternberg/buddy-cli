# buddy-cli Claude Plugin

Claude Code plugin for Buddy.works CI/CD pipeline management. Enables natural language control of deployments, execution monitoring, and pipeline troubleshooting.

> **Warning:** This plugin executes commands that can trigger deployments, cancel executions, and modify pipelines on your Buddy.works account. Review commands before confirming execution.

## Installation

### Option 1: From GitHub (Recommended)

```
/plugin marketplace add jtsternberg/buddy-cli
/plugin install buddy-cli
```

### Option 2: From Local Path

If you already have buddy-cli locally (via composer or git clone), point to it:

```
# From composer install:
/plugin marketplace add ./vendor/jtsternberg/buddy-cli

# From existing git clone:
/plugin marketplace add /path/to/buddy-cli

# Then install:
/plugin install buddy-cli
```

### Option 3: Clone for Development

For plugin development or contributing:

```bash
git clone https://github.com/jtsternberg/buddy-cli
```

```
/plugin marketplace add ./buddy-cli
/plugin install buddy-cli
```

### Restart Claude Code

After installation, restart Claude Code to activate the plugin.

## Prerequisites

The plugin requires buddy-cli to be installed and authenticated:

```bash
# Install buddy-cli
composer require jtsternberg/buddy-cli --dev

# Authenticate (choose one)
export BUDDY_TOKEN=<your-token>
buddy config:set token <your-token>

# Set workspace and project context
export BUDDY_WORKSPACE=<workspace-name>
export BUDDY_PROJECT=<project-name>
```

Get your API token from [Buddy.works App Tokens](https://app.buddy.works/api-tokens).

## Quick Start

```
You: "What pipelines are available?"
You: "Run the deploy pipeline"
You: "Why did my last build fail?"
You: "Show me the execution logs"
```

The plugin's skills automatically activate when discussing CI/CD topics.

## Features

### Skills (Auto-Invoked)

Skills load automatically based on conversation context.

**using-buddy-cli** - Activates when you mention:
- Running or listing pipelines
- Checking execution status
- Configuring buddy-cli settings

Example prompts:
- "List all pipelines in this project"
- "Run the staging deployment"
- "What's the status of the last execution?"

**troubleshooting-pipelines** - Activates when you mention:
- Build failures or errors
- Stuck or failed deployments
- Debugging CI/CD issues

Example prompts:
- "Why did my build fail?"
- "The deploy is stuck, help me debug"
- "Show me the error logs from the last run"

### Commands (User-Invoked)

Invoke directly with `/buddy-cli:<command>`:

| Command | Description | Example |
|---------|-------------|---------|
| `/buddy-cli:deploy` | Run a pipeline | `/buddy-cli:deploy production` |
| `/buddy-cli:status` | Show execution status | `/buddy-cli:status --latest` |
| `/buddy-cli:logs` | View execution logs | `/buddy-cli:logs --errors-only` |

### Agent

**cicd-specialist** - Autonomous agent for complex CI/CD tasks:
- Multi-step deployment workflows
- Cross-pipeline coordination
- Detailed log analysis and error diagnosis

## Workflow Examples

### Deploy to Production

```
You: "Deploy the main branch to production"
Claude: I'll run the production pipeline for you.
        [Executes: buddy pipelines:run production --branch main]
        Pipeline started. Execution ID: 12345

You: "Wait for it to finish and show me the result"
Claude: [Monitors execution status]
        Execution completed successfully in 3m 42s.
```

### Debug a Failed Build

```
You: "My build failed, what happened?"
Claude: Let me check the recent executions.
        [Executes: buddy executions:list --failed]

        Found failed execution #5678 from 10 minutes ago.
        [Executes: buddy executions:show 5678 --logs]

        The build failed due to a test failure in UserServiceTest.
        Error: "Expected 200 but got 401" on line 45.

        This looks like an authentication issue in your test setup.
```

### Monitor Deployment Status

```
You: "Is anything running right now?"
Claude: [Executes: buddy executions:list --status=running]

        Yes, there's one active execution:
        - Pipeline: deploy-staging
        - Started: 2 minutes ago
        - Current action: Running tests (3/5 complete)
```

## Configuration

The plugin uses buddy-cli's configuration system:

| Method | Example |
|--------|---------|
| Environment variables | `export BUDDY_TOKEN=xxx` |
| Config command | `buddy config:set token xxx` |
| Project file | `.buddy-cli.json` in project root |

Required settings:
- `token` - Buddy.works API token
- `workspace` - Workspace name (or `BUDDY_WORKSPACE`)
- `project` - Project name (or `BUDDY_PROJECT`)

## Testing

### Verify Plugin Loads

After installation, check that the plugin is loaded:
```
/plugin list
```

### Test Skills

Ask Claude about deployments or pipelines - skills should auto-invoke:
- "What pipelines do I have?"
- "Show me recent builds"

### Test Commands

```
/buddy-cli:status
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Plugin not loading | Verify plugin.json is valid JSON |
| Commands not found | Check commands/ directory has .md files |
| Skills not invoking | Ensure conversation mentions CI/CD topics |
| Authentication errors | Run `buddy config:show` to verify token |
| Wrong project | Set `BUDDY_WORKSPACE` and `BUDDY_PROJECT` |

## Resources

- [buddy-cli Documentation](https://github.com/jtsternberg/buddy-cli)
- [Ask questions in NotebookLM](https://notebooklm.google.com/notebook/c4d8bcb1-2333-490c-9885-667be4d0ef22) - Interactive Q&A
- [Buddy.works API](https://buddy.works/docs/api)
- [Claude Code Plugins](https://docs.anthropic.com/en/docs/claude-code)
