# buddy-cli vs. the Official `bdy` CLI

> [!NOTE]
> Captured 2026-06-16. Compares this project (`buddy-cli`, PHP/Symfony, **v1.4.0**)
> against Buddy's official Node CLI **`bdy`** (npm, **v1.22.x** at time of writing).
> `bdy` is published under the `@buddy-works` npm scope with a `raphael@buddy.works`
> maintainer — it is the genuine, actively-maintained official tool.

## TL;DR

When this project started, Buddy had no CLI. They now do (`bdy`), and it is **much
broader** — it covers entire product areas we never touched (artifacts, distros/routing,
tunnels, sandboxes, agents, domains, crawl, visual/unit tests, plus a generic REST
passthrough). Where the two overlap, `bdy` is generally ahead on **run orchestration**
and **context management**, but `buddy-cli` is meaningfully **ahead on execution
debugging, variable management, and webhooks** — none of which exist in `bdy`.

The open strategic question (see the beads task): **stop reimplementing the Buddy API
and instead make `buddy-cli` a bolt-on/wrapper over `bdy`** — delegate to `bdy` wherever
it already does the job, and layer our differentiating commands on top.

## Install / identity

| | buddy-cli (this repo) | bdy (official) |
|---|---|---|
| Language | PHP + Symfony Console | TypeScript + commander |
| Install | Composer / `self:install` symlink | `npm i -g bdy` |
| Binary | `buddy` | `bdy` |
| Command style | `group:action` (e.g. `pipelines:run`) | `group action` (e.g. `pipeline run start`) |
| Output flag | `--json` | `--format text|json|jsonl` |
| Context flags | `-w/--workspace`, `-p/--project` | `-w/--workspace`, `-p/--project` |
| Install-time code | n/a | none (no npm lifecycle scripts) |

## Feature-by-feature: overlapping surface

### Pipelines

| Capability | buddy-cli | bdy | Edge |
|---|---|---|---|
| list | table/json, shows last-run | + pagination (`--page/--per-page`) | bdy |
| show | `pipelines:show` + `--yaml` lossless export | `pipeline get` + separate `pipeline yaml` | tie |
| run | `--branch/--tag/--revision/--comment`, `--var`, `--wait`, `--follow` | `run start` + `--pull-request`, **masked vars**, **vars-from-file**, `--schedule`, `--priority`, `--action` (run subset), `--clear-cache`, jsonl stream | **bdy** |
| retry | retries *last* execution (convenience) | `run retry <run-id>` (explicit) | different intent |
| cancel | cancels *running* one (convenience) | `run cancel <run-id>` (explicit) | different intent |
| create / update | YAML file or flags | inline yaml or `@path` | tie |
| settings | **`pipelines:settings`** (metadata + vars, yaml round-trip) | — | **buddy-cli only** |
| approve wait-points | — | `run approve` (APPLY / APPROVE_VT / SET_VARIABLES) | **bdy only** |

### Executions — buddy-cli's standout area

`bdy` folds execution inspection under `pipeline run status/logs/list` (run-id oriented).
`buddy-cli` has a **dedicated, debugging-oriented Executions group** with no `bdy` equivalent:

- `executions:failed --analyze` — regex-categorizes failures (heap/GC/OOM, build, test,
  dependency, auth, network/timeout, disk).
- `executions:show --summary` / `--logs`, `executions:actions`, `executions:action-logs`.
- **Hex-hash → numeric ID auto-resolution** (paste an ID straight from a Buddy URL).

This is the clearest place buddy-cli is genuinely better. bdy is run-orchestration first;
buddy-cli is failure-diagnosis first.

### Variables

- **buddy-cli (`vars:*`)**: full CRUD, scope-aware (action > pipeline > project > workspace),
  encrypted / settable / description.
- **bdy**: no variable-management commands — only `--variable` injected at `run`/`sandbox` time.

**buddy-cli only.**

### Webhooks

- **buddy-cli (`webhooks:*`)**: full CRUD.
- **bdy**: no `webhooks` command (would go through `bdy api post`).

**buddy-cli only.**

### Projects / Workspace / Config / Auth — where bdy leads

- **Projects**: bdy adds `link` (scaffold `.buddy/`, git credential helper, set origin) and
  `set`/`get` to switch the default. buddy-cli is list/show only. **bdy.**
- **Workspace**: bdy has first-class `workspace list/get/set`. buddy-cli handles workspace
  only via flag/config. **bdy.**
- **Config**: comparable for token/workspace/project; bdy's `config` is overloaded with
  tunnel setup; buddy-cli adds `config:validate --test-api`. ~tie.
- **Auth**: both OAuth + browser. bdy adds `register` and `whoami`, and manages an OAuth
  app (creates/deletes). **bdy (slightly).**

### bdy-only product areas (out of scope for us)

`artifact`, `distro` (+ `route`, the static-hosting/CLOAKING routing feature), `tunnel`,
`sandbox`, `agent`, `domain`, `crawl`, `tests` (visual/unit), and a generic `api`
passthrough (`bdy api get|post|put|patch|delete|request`).

## Verdict

**Concede to bdy** (they do it natively + better): pipeline run orchestration (masked vars,
scheduling, partial-action runs, approve), workspace/project switching, project scaffolding.

**buddy-cli's moat** (no bdy equivalent): the **Executions debugging suite**
(`failed --analyze`, hex-ID resolution, summary/logs ergonomics), **variable-management CRUD**,
**webhooks CRUD**.

**Easy ports from bdy** (if we keep the standalone tool): pipeline-list pagination, masked
variables on run, `--var-from-file`.

## Strategic direction under consideration: bolt-on / wrapper

Rather than continue reimplementing the Buddy REST API in PHP, make `buddy-cli` a **thin
opinionated layer over `bdy`**:

- **Delegate** to `bdy` for everything it already does well (auth, pipeline run, projects,
  workspace, artifacts, distros, tunnels, sandboxes, the generic `api` passthrough).
  buddy-cli shells out to `bdy` and reuses its auth/config/context.
- **Bolt on** our differentiators where bdy has gaps: `executions:failed --analyze`,
  hex-ID resolution, the variable-management CRUD, webhooks CRUD — implemented either by
  driving `bdy api ...` under the hood or by keeping the SDK calls only for those paths.

### Why this is attractive
- Stops the maintenance treadmill of mirroring a fast-moving official API.
- We inherit new bdy features for free.
- Keeps the genuinely-better debugging UX as the reason buddy-cli exists.
- **Shrinks the maintenance surface to just the moat.** Because `bdy api <method>
  <endpoint>` is a generic REST passthrough, the moat commands
  (`executions:failed --analyze`, hex-ID resolution, vars/webhooks CRUD) can be
  reimplemented as thin layers that drive `bdy api ...` rather than the PHP SDK.
  If every API call goes through `bdy`, we can **drop the `buddy-works-php-api`
  SDK dependency entirely** and maintain only the opinionated logic on top —
  not the API client.

### Open questions to resolve before committing
1. **Dependency posture**: is requiring `bdy` (a Node global) on the user's machine
   acceptable for a PHP tool, or does that defeat the point? Hard dep vs. optional/detected.
2. **Auth/config sharing**: can we read bdy's stored token/context (`~/.bdy/...`) or do we
   re-auth separately? Reusing bdy's config is the cleaner UX.
3. **Surface mapping**: which existing `buddy-cli` commands become pure pass-throughs to
   `bdy`, which get deprecated with a "use `bdy ...`" notice, and which stay native.
4. **Output contract**: bdy uses `--format`, we use `--json`. Pick one and/or translate.
5. **Versioning/compat**: pin a minimum `bdy` version; handle flag drift across bdy releases.
6. **Is it still worth shipping at all?** If bdy closes the executions-debugging gap, the
   moat disappears. Decide whether to (a) wrapper, (b) keep standalone-but-narrowed to the
   moat commands, or (c) sunset and propose the `--analyze`/hex-ID features upstream to bdy.

## Reproducing this comparison

```bash
# Inspect the official package without installing/executing it:
cd /tmp && npm pack bdy && tar -xzf bdy-*.tgz
# command surface lives in:  package/distTs/src/command/**
```
