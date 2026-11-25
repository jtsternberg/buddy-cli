---
name: status
description: Show current and recent Buddy.works pipeline execution status by pipeline name, numeric ID, or app.buddy.works URL.
argument-hint: "[pipeline-name-or-id-or-url]"
disable-model-invocation: true
---

# Buddy Status

Invoked with: $ARGUMENTS

Codex: the arguments are the text after the skill name in the request.

Show current and recent pipeline execution status.

## Usage

```
/buddy-cli:status [pipeline-name-or-id-or-url]     # Claude Code
$buddy-cli:status [pipeline-name-or-id-or-url]     # Codex
```

Examples below use the Claude Code form.

## Arguments

- `pipeline-name-or-id-or-url` - Pipeline name, numeric ID, or Buddy.works URL (optional). Resolve it with `buddy resolve <input> --json`.

## Instructions

1. **Get executions**: Fetch recent execution status
   ```bash
   buddy executions:list --pipeline=<id> --json
   ```

2. **Show details**: Compact status view of an execution
   ```bash
   buddy executions:show <exec-id> --pipeline=<id> --summary
   ```

3. **Highlight important info**:
   - Currently running executions (show progress)
   - Failed executions (suggest the logs skill to investigate)
   - Recent successful deployments

## Output Format

Show a summary table:
```
Pipeline: production-deploy

Recent Executions:
  ✓ #1234 - Successful (2m 30s ago) - branch: main
  ✗ #1233 - Failed (1h ago) - branch: feature/x
  → #1235 - Running (started 45s ago) - branch: main
```

For running executions, show which action is currently executing.

## URL Support

Resolve a Buddy.works URL or pipeline name with `buddy resolve <input> --json`, then pass its `flags` (plus `execution` where needed).

## Example Interactions

User: `/buddy-cli:status https://app.buddy.works/awesomemotive/lindris-frontend/pipelines/pipeline/506857`
--> Parse URL, show status for pipeline 506857 in awesomemotive/lindris-frontend

User: `/buddy-cli:status`
→ Show status for all pipelines with recent activity

User: `/buddy-cli:status production`
→ Show detailed status for production pipeline

## Error Handling

- If no executions found: Inform user the pipeline hasn't been run recently
- If pipeline not found: List available pipelines
