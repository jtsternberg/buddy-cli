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
Create pipeline from YAML file.
```bash
buddy pipelines:create pipeline.yaml
```

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
buddy executions:show <exec-id> --pipeline=<pipeline-id> --logs
```

### executions:failed
Show failed action details with logs.
```bash
buddy executions:failed <exec-id> --pipeline=<pipeline-id>
```

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
buddy vars:set KEY value --encrypted
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
