---
name: troubleshooting-pipelines
description: Diagnoses and resolves CI/CD pipeline failures. Use when builds fail, executions error, or users ask why a deployment didn't work.
---

# Troubleshooting Pipelines

Quick diagnostic workflow for Buddy.works pipeline failures.

## Diagnostic Steps

### 1. Check Execution Status
```bash
# Get recent executions
buddy executions:list --pipeline=<id>

# Check specific execution
buddy executions:show <exec-id> --pipeline=<id>
```

### 2. Get Failure Details
```bash
# Show failed actions with logs
buddy executions:failed <exec-id> --pipeline=<id>

# Or get all logs
buddy executions:show <exec-id> --pipeline=<id> --logs
```

### 3. Analyze Errors
```bash
# Parse errors automatically
buddy executions:show <exec-id> --pipeline=<id> --logs --json | \
  python ${CLAUDE_PLUGIN_ROOT}/scripts/extract_errors.py
```

## Common Failure Patterns

| Symptom | Likely Cause | Fix |
|---------|--------------|-----|
| "permission denied" | SSH key or credentials | Check Variables, verify keys |
| "connection refused" | Service not running | Check target server status |
| "authentication failed" | Expired token/password | Rotate credentials in Variables |
| "timeout" | Slow tests/builds | Increase timeout or optimize |
| "npm ERR!" / "composer error" | Dependency issues | Clear cache, check lock files |
| "out of memory" | Resource limits | Increase container resources |
| Exit code 1 | Generic failure | Check action logs for specifics |

## Decision Tree

```
Build failed?
├── Check logs → Clear error message?
│   ├── Yes → Fix based on error type
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
```

### Cancel a stuck execution
```bash
buddy pipelines:cancel <pipeline-id>
```

### Check configuration
```bash
buddy config:show
buddy vars:list --pipeline=<id>
```

## When to Escalate

- Infrastructure issues (networking, server access)
- Permission problems requiring admin access
- Recurring failures despite code fixes
- Resource limits that need adjustment
