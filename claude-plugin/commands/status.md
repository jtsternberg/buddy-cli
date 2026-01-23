# /status

Show current and recent pipeline execution status.

## Usage

```
/status [pipeline-name-or-id]
```

## Arguments

- `pipeline-name-or-id` - Filter to specific pipeline (optional)

## Instructions

1. **Get executions**: Fetch recent execution status
   ```bash
   buddy executions:list --pipeline=<id> --json
   ```

2. **Parse and display**: Use format_status.php for readable output
   ```bash
   buddy executions:show <exec-id> --pipeline=<id> --json | \
     php ${CLAUDE_PLUGIN_ROOT}/scripts/format_status.php
   ```

3. **Highlight important info**:
   - Currently running executions (show progress)
   - Failed executions (suggest `/logs` to investigate)
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

## Example Interactions

User: `/status`
→ Show status for all pipelines with recent activity

User: `/status production`
→ Show detailed status for production pipeline

## Error Handling

- If no executions found: Inform user the pipeline hasn't been run recently
- If pipeline not found: List available pipelines
