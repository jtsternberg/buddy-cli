# /deploy

Run a Buddy.works pipeline deployment.

## Usage

```
/deploy [pipeline-name-or-id] [--branch=<branch>] [--wait]
```

## Arguments

- `pipeline-name-or-id` - Pipeline to run (optional, will list available if omitted)
- `--branch=<branch>` - Branch to deploy (optional, uses pipeline default)
- `--wait` - Wait for completion and report result

## Instructions

1. **If no pipeline specified**: List available pipelines and ask which one to run
   ```bash
   buddy pipelines:list
   ```

2. **Run the pipeline**: Execute with the specified options
   ```bash
   buddy pipelines:run <id> [--branch=<branch>] [--wait]
   ```

3. **Report status**: Show execution ID and status
   - If `--wait` was used, report final success/failure
   - If not waiting, provide the execution ID for checking later

## Example Interactions

User: `/deploy`
→ List pipelines, ask user to select, run selection

User: `/deploy production`
→ Find pipeline matching "production", run it

User: `/deploy 12345 --branch=main --wait`
→ Run pipeline 12345 on main branch, wait for completion

## Error Handling

- If pipeline not found: Show available pipelines and suggest closest match
- If already running: Warn user and ask if they want to queue another run
- If execution fails: Show error summary and suggest `/logs` command
