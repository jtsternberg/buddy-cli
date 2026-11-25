---
description: "Pre-release readiness gate: tests, lint, and docs/skill sync"
allowed-tools: Bash(composer test), Bash(composer lint), Bash(composer format), Bash(git *), Read, Grep, Glob
---

Pre-release readiness gate. Verify the working tree is releasable: code is green
AND every surface that documents the CLI has kept pace with the code. Run this
before `/publish-release` (which invokes it as its first step).

This is a **report**, not an auto-fixer. Surface every problem; only change files
when the user confirms.

## What "release-ready" means here

A new command, option, argument, or behavior is not done until the places that
describe it agree with the code. This repo has several such surfaces, and they
drift silently. Preflight's job is to catch that drift.

## Steps

Work through every check. Don't stop at the first failure — collect all results,
then report a single checklist so the user sees the full picture.

1. **Determine the release delta.**
   - Last tag: `git describe --tags --abbrev=0`
   - Changed files since: `git diff --name-only $(git describe --tags --abbrev=0)..HEAD`
   - Commit summaries: `git log $(git describe --tags --abbrev=0)..HEAD --oneline`
   - Also account for uncommitted work: `git status --short`.
   - From the diff, identify what changed in the **public CLI surface**: new/renamed/
     removed commands (`src/Application.php` registrations, `setName(...)`), and
     new/changed/removed options or arguments (`addOption`/`addArgument` in
     `src/Commands/**`). This list drives checks 4, 5 and 7. If the surface is unchanged,
     mark those checks N/A (state why), don't skip silently.

2. **Tests** — `composer test`. All must pass. On failure, show the failing test
   names + assertion output. Do NOT fix unless asked.

3. **Lint** — `composer lint` (`pint --test`). On failure, list flagged files and
   offer `composer format` (wait for confirmation before running it).

4. **README sync** — for each CLI-surface change from step 1, confirm `README.md`
   reflects it (the command reference around the relevant section, e.g. the
   `### Variables` block). Flag any new command/option absent from the README, and
   any README example that references a removed/renamed command or option.

5. **Public skill sync** — the bundled skill is the agent-facing source of truth and
   ships to installers. Check:
   - `claude-plugin/skills/using-buddy-cli/COMMANDS.md` — the full command reference.
     Every command must have an entry; new options must appear in its usage block.
   - `claude-plugin/skills/using-buddy-cli/SKILL.md` — overview/command list.
   - `claude-plugin/skills/using-buddy-cli/WORKFLOWS.md` — only if the change adds or
     alters a multi-step workflow.
   - `claude-plugin/skills/troubleshooting-pipelines/SKILL.md` — only if the change
     touches failure/diagnosis behavior.

6. **Full drift sweep (whole plugin, not just the delta).** Checks 4–5 only see
   this release's changes, so drift left by earlier releases survives them. Every
   release also sweeps everything the plugin ships, `claude-plugin/**`: skills,
   agents, slash commands and the plugin README.
   - `tests/Unit/SkillDocsTest.php` (part of `composer test`) must pass. It fails
     when a command or option is missing from the using-buddy-cli skill, or when
     any `claude-plugin/**` doc names a `buddy ns:cmd` that isn't registered. It
     matches names only, so the checks below still apply.
   - Every `buddy <command> --<option>` written anywhere in `claude-plugin/**` must
     exist (`buddy <command> --help`). Ignore lines that deliberately name things
     that don't exist ("There is NO `buddy api`") and values after `--`.
   - Every `${CLAUDE_PLUGIN_ROOT}/...` path referenced must exist in the plugin.
   - Behaviour claims must still be true. For each command whose behaviour changed
     since its docs were written (`git log` on the command file vs the doc), re-read
     the skill's description of it against `--help` and the code.
   - Report each hit as `file:line` with what the code actually does.

7. **Deeper docs** — if the change affects an area with a dedicated guide
   (`docs/*.md`, e.g. `Debugging-Pipeline-Executions.md`), confirm that guide is
   still accurate.

8. **Plugin manifest** — report the current `version` in
   `claude-plugin/.claude-plugin/plugin.json`. Preflight does NOT bump it
   (`/publish-release` owns the bump); just surface the current value and whether
   it still matches the last tag, so a drift is visible.

9. **Shell completion** — N/A by design: `buddy` has no static completion script;
   Symfony Console derives completion dynamically from command definitions, so new
   commands/options are picked up automatically. If a checked-in completion script
   is ever added, verify it here.

## Reporting

Emit a checklist with one line per check: ✅ pass / ❌ fail / ⚠️ needs attention /
➖ N/A — followed by the specifics for anything not green. Example:

```
Preflight — release delta v1.6.2..HEAD (3 commits)
✅ Tests          271 passed, 668 assertions
✅ Lint           pint clean
⚠️ README         vars:set --action documented; new `webhooks:test` missing from Variables/Webhooks section
❌ Skill COMMANDS  webhooks:test has no entry in COMMANDS.md
❌ Drift sweep     claude-plugin/.claude-plugin/README.md:161 uses `executions:list --failed` (no such option; use executions:failed)
➖ docs/           no guide touches this change
➖ Completion      dynamic (Symfony) — nothing to update
ℹ️ plugin.json    1.6.2 (matches last tag; /publish-release will bump)
```

End with a one-line verdict: **READY** (everything green/N-A) or **NOT READY**
(list the blocking items). For doc/skill gaps, offer to write the missing entries;
for lint, offer `composer format`. Never claim a check passed without the
command output or file contents to back it.
