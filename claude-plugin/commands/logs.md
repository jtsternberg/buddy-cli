# /buddy-cli:logs

Fetch and display pipeline execution logs.

## Usage

```
/buddy-cli:logs [execution-id-or-url] [--pipeline=<id>] [--action=<name>] [--errors-only]
```

## Arguments

- `execution-id-or-url` - Specific execution ID or a Buddy.works URL (optional, defaults to latest). If a URL is given, parse it per the `buddy-cli:url-parser` agent.
- `--pipeline=<id>` - Pipeline ID (required if execution-id provided, not needed if URL given)
- `--action=<name>` - Filter to specific action
- `--errors-only` - Only show failed actions

## Instructions

1. **Get execution**: If no ID provided, get the most recent execution
   ```bash
   buddy executions:list --pipeline=<id> --json | jq '.[0]'
   ```

2. **Fetch logs**: Get execution details with logs
   ```bash
   buddy executions:show <exec-id> --pipeline=<id> --logs
   ```

3. **For errors only**: Use executions:failed
   ```bash
   buddy executions:failed <exec-id> --pipeline=<id>
   ```

4. **Analyze errors**: Use extract_errors.php for summary
   ```bash
   buddy executions:show <exec-id> --pipeline=<id> --logs --json | \
     php ${CLAUDE_PLUGIN_ROOT}/scripts/extract_errors.php
   ```

## Output Format

For each action show:
- Action name and status
- Duration
- Relevant log output (truncated if very long)

For failed actions, highlight:
- Error messages
- Exit codes
- Suggestions for resolution

## URL Support

If a Buddy.works URL is provided, parse it using the `buddy-cli:url-parser` agent.

> **CRITICAL: URL Execution ID Resolution**
> Buddy URLs contain hex hash execution IDs (e.g., `698b463a`). The CLI requires integer IDs (e.g., `4099`). The url-parser agent resolves these automatically. Do NOT pass the hash directly to `executions:show` or `executions:failed` — it will silently fail or return "Execution not found".

## Example Interactions

User: `/buddy-cli:logs https://app.buddy.works/awesomemotive/lindris-frontend/pipelines/pipeline/506857/execution/698b463a`
--> Parse URL with url-parser agent (resolves hash to integer ID), show logs for resolved execution on pipeline 506857

User: `/buddy-cli:logs`
→ Show logs from most recent execution

User: `/buddy-cli:logs 12345 --pipeline=67890`
→ Show logs for specific execution

User: `/buddy-cli:logs --errors-only`
→ Show only failed action logs from recent execution

## Error Handling

- If execution not found: Show recent execution IDs to choose from
- If no logs available: Inform user (execution may still be starting)
