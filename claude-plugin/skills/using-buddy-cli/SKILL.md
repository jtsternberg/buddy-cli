---
name: using-buddy-cli
description: Manages CI/CD pipelines via the buddy CLI tool. Use when running deployments, checking pipeline status, viewing execution logs, or managing Buddy.works configuration.
---

# Using buddy-cli

The `buddy` CLI manages Buddy.works CI/CD pipelines. Resource commands take `--json` for machine-readable output, and `--workspace`/`--project` to override the configured context. `buddy <command> --help` lists every option.

## Setup

```bash
export BUDDY_TOKEN=<token>     # or: buddy config:set token <token>, or OAuth: buddy login
export BUDDY_WORKSPACE=<name>  # or: buddy config:set workspace <name>
export BUDDY_PROJECT=<name>    # or: buddy config:set project <name>
buddy config:validate --test-api   # needs WORKSPACE_READ on a narrowed OAuth login
```

OAuth options (narrow `--scopes`, headless `--paste` over SSH): COMMANDS.md → login.

## Buddy.works URLs

Turn a pasted URL, pipeline name or ID into flags with `buddy resolve <input> --json`. It returns `workspace`, `project`, `pipeline`, `execution` and `action_execution` (whichever apply) and a ready `flags` string. Use them for the next command only; don't `config:set` them. Hex execution IDs work as-is. An ambiguous name fails and lists the candidates, so ask which one.

## Core commands

```bash
buddy pipelines:list
buddy pipelines:run <id> [--branch=<b>] [--wait|--follow]
buddy executions:list --pipeline=<id> [--status=FAILED]
buddy executions:failed <exec-id> --pipeline=<id>        # Failed action logs
buddy executions:show <exec-id> --pipeline=<id> --logs   # All action logs
```

For a failed build, use the `buddy-cli:troubleshooting-pipelines` skill.

## Reference

- **COMMANDS.md**: every command and option: pipelines, executions, actions, variables, webhooks, projects, config, login.
- **WORKFLOWS.md**: multi-step patterns (deploy and monitor, YAML export/edit, variables, scripting with `--json`).

## Gotchas

- Pipeline ID is positional for `pipelines:run/retry/cancel/show`, but `--pipeline=<id>` for `executions:*` and `vars:*`.
- There is no `buddy api` command. Use the subcommands.
- `vars:set` values starting with `--` need options first: `buddy vars:set --pipeline=X -- KEY "--value"`.
