---
name: troubleshooting-pipelines
description: Diagnoses and resolves CI/CD pipeline failures. Use when builds fail, executions error, or users ask why a deployment didn't work.
---

# Troubleshooting Pipelines

Diagnostic workflow for Buddy.works pipeline failures.

> **DO NOT** try `buddy-cli`, `buddy execution logs`, `buddy api`, or browser automation. These do not exist. Use the commands below.

## FIRST: Get the Logs

The #1 priority is reading the actual build logs. Do NOT guess or theorize without them.

```bash
# Failed action logs (most useful for failures)
buddy executions:failed <exec-id> --pipeline=<id>

# ALL action logs (when you need full context)
buddy executions:show <exec-id> --pipeline=<id> --logs
buddy executions:show <exec-id> --pipeline=<id> --logs -v  # Surface errors for skipped actions

# Error pattern analysis
buddy executions:failed <exec-id> --pipeline=<id> --analyze

# Compact status overview
buddy executions:show <exec-id> --pipeline=<id> --summary

# Targeted: list actions to find action_execution_id, then fetch one action's logs
buddy executions:actions <exec-id> --pipeline=<id>
buddy executions:action-logs <exec-id> <action-execution-id> --pipeline=<id>
```

> **Tip**: For targeted debugging, use `executions:actions` to find the specific action's hex ID, then `executions:action-logs` to fetch just that one action's logs. Faster than `--logs` which fetches all actions sequentially.

## Finding the Execution ID

### From a URL

When the user provides a Buddy.works URL, parse it using the `buddy-cli:url-parser` agent to extract workspace, project, pipeline, and execution IDs.

> **Note**: URL execution IDs are hex hashes (e.g., `69c2d8c162305ac4bd6107fb`). The CLI resolves these to integer IDs automatically — you can pass either format.

### From the pipeline

```bash
# List recent executions (returns integer IDs)
buddy executions:list --pipeline=<id>

# Find the latest failed execution
buddy executions:list --pipeline=<id> --json | jq '[.[] | select(.status == "FAILED")] | .[0]'
```

## Common Failure Patterns

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| "heap out of memory" | Node.js memory limit | Set `NODE_OPTIONS=--max-old-space-size=4096` as pipeline variable |
| "permission denied" | SSH key or credentials | Check Variables, verify keys |
| "connection refused" | Service not running | Check target server status |
| "authentication failed" | Expired token/password | Rotate credentials in Variables |
| "timeout" | Slow tests/builds | Increase timeout or optimize |
| "npm ERR!" / "composer error" | Dependency issues | Clear cache, check lock files |
| "out of memory" | Container resource limits | Increase container resources or memory variable |
| Exit code 1 | Generic failure | Check action logs for specifics |

## Decision Tree

```
Build failed?
├── Get logs FIRST (executions:failed)
├── Clear error message?
│   ├── Yes → Fix based on error type (see table above)
│   └── No → Check last successful run, diff changes
│
├── Intermittent failure?
│   ├── Yes → Likely timeout/resource issue, retry first
│   └── No → Code or config change caused it
│
└── First-time setup?
    └── Check config: token, workspace, project, variables
```

## Quick Fixes

### Retry a failure
```bash
buddy pipelines:retry <pipeline-id>
# For wildcard pipelines, specify branch:
buddy pipelines:run <pipeline-id> --branch=<branch-name>
```

### Set an environment variable
```bash
# Pipeline-scoped variable (all options BEFORE --, then key and value)
buddy vars:set --pipeline=<id> -- KEY "value"
```

### Cancel a stuck execution
```bash
buddy pipelines:cancel <pipeline-id>
```

### Check configuration
```bash
buddy config:validate
buddy config:validate --test-api
buddy vars:list --pipeline=<id>
```

## When to Escalate

- Infrastructure issues (networking, server access)
- Permission problems requiring admin access
- Recurring failures despite code fixes
- Resource limits that need adjustment
