# /buddy-cli:status

Show current and recent pipeline execution status.

## Usage

```
/buddy-cli:status [pipeline-name-or-id-or-url]
```

## Arguments

- `pipeline-name-or-id-or-url` - Pipeline name, numeric ID, or Buddy.works URL (optional). If a URL is given, parse it per the `buddy-cli:url-parser` agent.

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
   - Failed executions (suggest `/buddy-cli:logs` to investigate)
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

If a Buddy.works URL is provided, parse it per the `buddy-cli:url-parser` agent.

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
