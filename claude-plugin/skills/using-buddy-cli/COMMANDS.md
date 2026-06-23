# buddy-cli Command Reference

Complete command reference for the buddy CLI.

## Global Options

All commands support:
- `--workspace`, `-w` - Workspace name
- `--project`, `-p` - Project name
- `--json` - Output as JSON

## Pipeline Commands

### pipelines:list
List all pipelines in the project.
```bash
buddy pipelines:list
buddy pipelines:list --json
```

### pipelines:show
Show pipeline details.
```bash
buddy pipelines:show <pipeline-id>
buddy pipelines:show <pipeline-id> --yaml  # Output as YAML config
```

### pipelines:run
Run a pipeline.
```bash
buddy pipelines:run <pipeline-id>
buddy pipelines:run <pipeline-id> --branch=main
buddy pipelines:run <pipeline-id> --wait  # Wait for completion
buddy pipelines:run <pipeline-id> --var KEY=VALUE  # Pass variables
```

### pipelines:retry
Retry the last failed execution.
```bash
buddy pipelines:retry <pipeline-id>
```

### pipelines:cancel
Cancel a running execution.
```bash
buddy pipelines:cancel <pipeline-id>
```

### pipelines:get
Get pipeline config as YAML file.
```bash
buddy pipelines:get <pipeline-id>
```

### pipelines:create
Create pipeline from YAML file or via flags.
```bash
# From YAML file (full configuration):
buddy pipelines:create pipeline.yaml

# Via flags (quick creation):
buddy pipelines:create --name="My Pipeline" --on=MANUAL --refs=refs/heads/main
buddy pipelines:create --name="My Pipeline" --on=ON_EVERY_PUSH --json
```

`--on` accepts `MANUAL`, `ON_EVERY_PUSH`, or `SCHEDULED` (case-insensitive).
`--refs` sets the branch/tag pattern (e.g. `refs/heads/main`, `refs/tags/v*`).
Both modes support `--json` to return the full pipeline object.

### pipelines:update
Update pipeline from YAML file.
```bash
buddy pipelines:update <pipeline-id> pipeline.yaml
```

## Execution Commands

### executions:list
List recent executions.
```bash
buddy executions:list --pipeline=<pipeline-id>
```

### executions:show
Show execution details.
```bash
buddy executions:show <exec-id> --pipeline=<pipeline-id>
buddy executions:show <exec-id> --pipeline=<pipeline-id> --logs      # Full logs for all actions
buddy executions:show <exec-id> --pipeline=<pipeline-id> --logs -v   # Show errors for skipped actions
buddy executions:show <exec-id> --pipeline=<pipeline-id> --summary   # Compact status view
```

`--logs` fetches each action's logs sequentially (shows progress indicator). Use `-v` to surface errors when individual log fetches fail (rate limits, permissions, etc.) instead of silently skipping.

### executions:failed
Show failed action details with logs.
```bash
buddy executions:failed <exec-id> --pipeline=<pipeline-id>
buddy executions:failed <exec-id> --pipeline=<pipeline-id> --analyze  # Categorize errors
```

### executions:actions
List all action executions with their `action_execution_id`, status, and duration.
```bash
buddy executions:actions <exec-id> --pipeline=<pipeline-id>
buddy executions:actions <exec-id> --pipeline=<pipeline-id> --json
```

Use this to find the `action_execution_id` needed by `executions:action-logs`. Accepts hex execution IDs from Buddy URLs.

### executions:action-logs
Fetch full log output for a specific action execution.
```bash
buddy executions:action-logs <exec-id> <action-execution-id> --pipeline=<pipeline-id>
buddy executions:action-logs <exec-id> <action-execution-id> --pipeline=<pipeline-id> --json
```

The `action-execution-id` is a hex string from `executions:actions` output. More targeted than `--logs` (single API call vs N calls). Accepts hex execution IDs from Buddy URLs.

## Action Commands

### actions:list
List actions in a pipeline.
```bash
buddy actions:list --pipeline=<pipeline-id>
```

### actions:show
Show action details.
```bash
buddy actions:show <action-id> --pipeline=<pipeline-id>
buddy actions:show <action-id> --pipeline=<pipeline-id> --yaml
```

### actions:create
Create action from YAML file.
```bash
buddy actions:create action.yaml --pipeline=<pipeline-id>
```

### actions:update
Update action from YAML file.
```bash
buddy actions:update <action-id> action.yaml --pipeline=<pipeline-id>
```

### actions:delete
Delete an action.
```bash
buddy actions:delete <action-id> --pipeline=<pipeline-id>
buddy actions:delete <action-id> --pipeline=<pipeline-id> --force
```

## Variable Commands

### vars:list
List environment variables.
```bash
buddy vars:list
buddy vars:list --project=<name>
buddy vars:list --pipeline=<pipeline-id>
```

### vars:show
Show variable details.
```bash
buddy vars:show <var-id>
```

### vars:set
Create or update a variable.
```bash
buddy vars:set KEY value
buddy vars:set KEY value --project=<name>
buddy vars:set KEY value --pipeline=<pipeline-id>
buddy vars:set KEY value --encrypted
```

**Gotchas:**

**Secrets — keep the value off argv and out of shell history:**
A positional value is visible in `ps aux` and saved to shell history. For
secrets (especially `--encrypted`), read the value from stdin or a file instead:
```bash
# stdin via bare '-' (or --value-file=-):
echo -n "$SECRET" | buddy vars:set API_KEY - --encrypted
printf %s "$SECRET" | buddy vars:set API_KEY --value-file=- --encrypted

# from a file:
buddy vars:set API_KEY --value-file=./secret.txt --encrypted
```
A single trailing newline is stripped (so `echo | ...` works). The literal
positional value and `--value-file` are mutually exclusive.

**Values containing `--` (e.g., Node.js flags):**
Symfony Console parses `--max-old-space-size` as a CLI flag. All options MUST come BEFORE the `--` separator:
```bash
# WRONG - value gets parsed as a flag:
buddy vars:set NODE_OPTIONS "--max-old-space-size=4096" --pipeline=12345

# RIGHT - options first, then -- separator, then positional args:
buddy vars:set --pipeline=12345 -- NODE_OPTIONS "--max-old-space-size=4096"
```

**Scope rules (exactly ONE scope allowed):**
The Buddy API requires exactly one scope per variable. Combining scopes fails.
```bash
# WRONG - two scopes:
buddy vars:set KEY val --project=foo --pipeline=123
# ERROR: "Only one scope is allowed"

# RIGHT - one scope:
buddy vars:set KEY val --pipeline=123
buddy vars:set KEY val --project=foo
buddy vars:set KEY val                    # workspace scope (default)
```

**Action-level variables:** Use `--action=<id>` with `--pipeline=<id>` to scope to a specific action:
```bash
buddy vars:set --pipeline=123 --action=456 -- KEY "value"
```

### vars:delete
Delete a variable.
```bash
buddy vars:delete <var-id>
buddy vars:delete <var-id> --force
```

## Project Commands

### projects:list
List projects in workspace.
```bash
buddy projects:list
```

### projects:show
Show project details.
```bash
buddy projects:show <project-name>
```

## Configuration Commands

### config:show
Show current configuration.
```bash
buddy config:show
```

### config:set
Set configuration value.
```bash
buddy config:set token <value>
buddy config:set workspace <value>
buddy config:set project <value>
```

### config:clear
Clear all configuration.
```bash
buddy config:clear
```

### config:validate
Validate configuration is complete and working.
```bash
buddy config:validate
buddy config:validate --test-api  # Also test API connectivity
```

## Authentication Commands

### auth:login
OAuth login (opens browser).
```bash
buddy login
```

### auth:logout
Clear saved credentials.
```bash
buddy logout
```
