# /buddy-cli:deploy

Run a Buddy.works pipeline deployment.

## Usage

```
/buddy-cli:deploy [pipeline-name-or-id-or-url] [--branch=<branch>] [--wait]
```

## Arguments

- `pipeline-name-or-id-or-url` - Pipeline to run. Can be a name, numeric ID, or a Buddy.works URL (optional, will list available if omitted). If a URL is given, parse it per the `buddy-cli:url-parser` agent.
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

## URL Support

If a Buddy.works URL is provided, parse it per the `buddy-cli:url-parser` agent.

## Example Interactions

User: `/buddy-cli:deploy https://app.buddy.works/awesomemotive/lindris-frontend/pipelines/pipeline/506857 --wait`
--> Parse URL, run: buddy pipelines:run 506857 -w awesomemotive -p lindris-frontend --wait

User: `/buddy-cli:deploy`
→ List pipelines, ask user to select, run selection

User: `/buddy-cli:deploy production`
→ Find pipeline matching "production", run it

User: `/buddy-cli:deploy 12345 --branch=main --wait`
→ Run pipeline 12345 on main branch, wait for completion

## Error Handling

- If pipeline not found: Show available pipelines and suggest closest match
- If already running: Warn user and ask if they want to queue another run
- If execution fails: Show error summary and suggest `/buddy-cli:logs` command
