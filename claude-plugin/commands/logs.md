# /buddy-cli:logs

Fetch and display pipeline execution logs.

## Usage

```
/buddy-cli:logs [execution-id] [--pipeline=<id>] [--action=<name>] [--errors-only]
```

## Arguments

- `execution-id` - Specific execution (optional, defaults to latest)
- `--pipeline=<id>` - Pipeline ID (required if execution-id provided)
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

## Example Interactions

User: `/buddy-cli:logs`
→ Show logs from most recent execution

User: `/buddy-cli:logs 12345 --pipeline=67890`
→ Show logs for specific execution

User: `/buddy-cli:logs --errors-only`
→ Show only failed action logs from recent execution

## Error Handling

- If execution not found: Show recent execution IDs to choose from
- If no logs available: Inform user (execution may still be starting)
