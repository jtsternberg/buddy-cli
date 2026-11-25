---
name: deploy
description: Run a Buddy.works pipeline deployment by pipeline name, numeric ID, or app.buddy.works URL, optionally on a branch and waiting for the result.
argument-hint: "[pipeline-name-or-id-or-url] [--branch=BRANCH] [--wait]"
disable-model-invocation: true
---

# Buddy Deploy

Invoked with: $ARGUMENTS

Codex: the arguments are the text after the skill name in the request.

Run a Buddy.works pipeline deployment.

## Usage

```
/buddy-cli:deploy [pipeline-name-or-id-or-url] [--branch=<branch>] [--wait]     # Claude Code
$buddy-cli:deploy [pipeline-name-or-id-or-url] [--branch=<branch>] [--wait]     # Codex
```

Examples below use the Claude Code form.

## Arguments

- `pipeline-name-or-id-or-url` - Pipeline to run. Can be a name, numeric ID, or a Buddy.works URL (optional, will list available if omitted). Resolve it with `buddy resolve <input> --json`.
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

Resolve a Buddy.works URL or pipeline name with `buddy resolve <input> --json`, then pass its `flags` (plus `execution` where needed).

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
- If execution fails: Show error summary and suggest the logs skill
