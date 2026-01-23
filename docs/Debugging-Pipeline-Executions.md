# Debugging Pipeline Executions

This guide walks through a real-world scenario encountered while building and testing this CLI tool. The specific example below came from trying to manually run a PR validation pipeline—the exact debugging session that led to adding the `--var` option.

> [!TIP]
> This iterative debugging workflow is ideal for pairing with an LLM assistant (Claude, ChatGPT, etc.). Let it run commands, interpret output, check `--help` for options, and suggest next steps. The back-and-forth of "run → check logs → diagnose → try fix → repeat" is exactly the kind of conversation LLMs excel at.

## Scenario

You have a PR validation pipeline that works when triggered by GitHub PRs but fails when run manually via the CLI.

## Step 1: Run a Pipeline

```bash
buddy pipelines:run 12345 --project=my-project --branch=my-feature-branch
```

Output:
```
Execution Started

  Execution ID  101
  Status        INPROGRESS
  Branch        my-feature-branch
  Started       just now
```

## Step 2: Check Execution Status

```bash
buddy executions:show 101 --pipeline=12345 --project=my-project
```

Output:
```
Execution Details

  ID        101
  Status    FAILED
  Branch    my-feature-branch
  ...

Actions:
+------------------------------------+------------+----------+
| Action                             | Status     | Duration |
+------------------------------------+------------+----------+
| Get GitHub Info & Setup Vars       | FAILED     | 3s       |
| Run NPM Install                    | ENQUEUED   | -        |
| ...                                | ...        | ...      |
+------------------------------------+------------+----------+
```

## Step 3: Get Failed Action Logs

Use `executions:failed` to see details and logs for the failed action:

```bash
buddy executions:failed 101 --pipeline=12345 --project=my-project
```

Output:
```
Failed Action: Get GitHub Info & Setup Vars
  Type      GIT_HUB_CLI
  Started   5 min ago
  Finished  5 min ago

Logs:
Integration: abc123|abc123
my-project
fatal: bad revision 'origin/..HEAD'
Action failed: see logs above for details
```

## Step 4: Diagnose the Issue

The error `fatal: bad revision 'origin/..HEAD'` indicates a git diff is failing because of an empty base branch reference.

**Root cause:** This pipeline is designed for PR events (`refs/pull/*`). When triggered by a PR, Buddy automatically provides `BUDDY_EXECUTION_PULL_REQUEST_BASE_BRANCH`. When run manually, this variable is empty.

## Step 5: Pass Required Variables

Use the `--var` option to provide the missing context:

```bash
buddy pipelines:run 12345 --project=my-project \
  --branch=my-feature-branch \
  --var="BUDDY_EXECUTION_PULL_REQUEST_BASE_BRANCH=master"
```

Output:
```
Execution Started

  Execution ID  102
  Status        INPROGRESS
  Branch        my-feature-branch
  Started       just now
```

## Step 6: Verify the Fix

```bash
buddy executions:show 102 --pipeline=12345 --project=my-project --logs
```

Output:
```
Actions:
+------------------------------------+------------+----------+
| Action                             | Status     | Duration |
+------------------------------------+------------+----------+
| Get GitHub Info & Setup Vars       | SUCCESSFUL | 3s       |
| Run NPM Install                    | SKIPPED    | -        |
| ...                                | ...        | ...      |
+------------------------------------+------------+----------+

--- Logs: Get GitHub Info & Setup Vars ---
...
IS_DRAFT: 'true'
...
```

The first action passes, but subsequent actions are skipped. The `--logs` output shows two clues:

1. The first action's logs show `IS_DRAFT: 'true'`
2. The skipped actions' logs say "Action has been skipped because trigger conditions were not met"

To inspect trigger conditions, use `--json` and look at the `trigger_conditions` array:

```bash
buddy pipelines:show 12345 --project=my-project --json | jq '.actions[1].trigger_conditions'
```

```json
[
  {
    "trigger_condition": "VAR_IS_NOT",
    "trigger_variable_value": "true",
    "trigger_variable_key": "IS_DRAFT"
  }
]
```

This confirms the action only runs when `IS_DRAFT` is NOT `"true"`. (Your pipeline may have different logic.)

## Step 7: Provide Full PR Context

To fully simulate a PR run, pass additional variables. Here, `42` represents a non-draft PR number in the repo:

```bash
buddy pipelines:run 12345 --project=my-project \
  --branch=my-feature-branch \
  --var="BUDDY_EXECUTION_PULL_REQUEST_BASE_BRANCH=master" \
  --var="BUDDY_EXECUTION_PULL_REQUEST_NO=42"
```

Now the logs show:
```
BUDDY_EXECUTION_PULL_REQUEST_NO: '42'
IS_DRAFT: ''
```

And all actions run instead of being skipped.

## Step 8: Retry a Failed Execution

If an execution fails for a transient reason (flaky test, network issue), retry it:

```bash
buddy pipelines:retry 12345 --project=my-project
```

Output:
```
Execution Retried

  Execution ID  103
  Status        INPROGRESS
```

The retry preserves all original variables and settings.

## Quick Reference

| Command | Purpose |
|---------|---------|
| `buddy pipelines:run <id> --branch=<name>` | Run pipeline on a branch |
| `buddy pipelines:run <id> --var="KEY=value"` | Run with custom variables |
| `buddy executions:show <id> --logs` | View execution with action logs |
| `buddy executions:failed <id>` | Show failed actions with logs |
| `buddy pipelines:retry <id>` | Retry the last failed execution |

## Tips

1. **Export pipeline config** to understand its structure:
   ```bash
   buddy pipelines:get <id> --project=<name>
   ```

2. **Check pipeline variables** in the YAML output to see what the pipeline expects.

3. **Use `--logs` liberally** - action logs contain the actual commands and errors.

4. **Retry vs. Re-run**: Use `retry` for transient failures (same config). Use `run` with new variables if you need to change inputs or just want to run it from scratch.
