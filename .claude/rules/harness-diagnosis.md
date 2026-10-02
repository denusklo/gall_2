# Harness Leak Diagnosis Report (Deliverable A)

Written by Fable 5 on 2026-07-08. This is the foundation document: every other file in `.claude/rules/` traces back to a leak identified here. If a rule elsewhere seems arbitrary, its reason is in this file.

## 1. Environment inventory (as of 2026-07-08)

**Global (`~/.claude/`)**
- `settings.json`: `model: opus`, `effortLevel: medium`, `alwaysThinkingEnabled: true`. Hooks = WSL sound notifications only (harmless). Plugins enabled: `superpowers`, `codex`, `ponytail`; `claude-mem` disabled.
- `~/.claude/CLAUDE.md`: one pointer to the `graphify` skill.
- MCP servers (in `~/.claude.json`, global scope): `codegraph` (stdio), `context7` (http), `consult7` (uvx → Google Gemini API).
- Personal skills: graphify, vercel-*, web-design-guidelines.

**Project (`gallery_2`)**
- `.claude/settings.json`: attribution config only. `.claude/settings.local.json`: permission allowlist (see §5). Also an inactive `settings.zai.json` (alternate-provider profile, not loaded by Claude Code).
- `CLAUDE.md`: was a 349-line monolith (backup: `CLAUDE.md.bak`), now a router (see §4, Leak 3).
- No project agents, commands, or skills. Claude memory dir was empty.
- Tests: only Laravel's two `ExampleTest.php` stubs — **no real test suite**.
- Docker env: `php-fpm-8.2`, `mysql-server`, `nginx-server` running (plus ~11 unrelated containers on the same host).
- Root directory contains five orphaned session-narrative docs (`FCM_DEBUGGING_SESSION.md`, `FCM_NOTIFICATIONS.md`, `VERCEL_BLOB_TESTING.md`, `GALLERY_RESTRUCTURE_PLAN.md`, `AUTHENTICATION_SYSTEM.md`) — all gitignored, all rotting.

## 2. Leak #1 — Conflicting mandatory frameworks injected every session

**Symptom.** Every session starts with two large injected prompts that give contradictory orders:
- `superpowers` injects "if there is even a 1% chance a skill applies you MUST invoke it" plus ~30 skills (brainstorming, TDD, writing-plans…).
- `ponytail` (SessionStart hook) injects a full "lazy senior dev" persona: skip ceremony, shortest diff, no scaffolding.

A strong model reconciles these silently. A weak model does one of three failure modes: (a) invokes `brainstorming` for a one-line fix and burns 10k tokens on ceremony; (b) skips a genuinely needed plan because "ponytail says be lazy"; (c) oscillates between the two mid-task and loses the thread. This is the single largest source of token waste and focus loss on this machine.

**Physical block (implemented).** `CLAUDE.md` §"Rule precedence" defines a strict order and a concrete size threshold that decides which framework applies (≥3 files or new table/endpoint/Vue-app → process skills; otherwise ponytail sizing). Weak models follow the table, not their judgment.

**Not implemented (needs user action):** trimming plugins globally was declined for this session (project-rules-only chosen). If thrash persists, disable `ponytail` OR `superpowers` for this project.

## 3. Leak #2 — Broken or trap tool surfaces

**Symptom.** Tools that advertise themselves aggressively but fail or mislead here:

| Tool | Trap (before 2026-07-08) | Status now |
|---|---|---|
| `codegraph` MCP | Instructions say "consult BEFORE writing code", but project had **no index** → every call errored. Weak models retry errors. | **FIXED**: `codegraph init` run; index at `.codegraph/` (191 files, 1,554 nodes). If it errors again: run `codegraph sync .`, then `codegraph unlock .`, then fall back to Grep. Never retry the same failing MCP call more than once. |
| `graphify` skill | Description claims "any question about a codebase", but no `graphify-out/` exists here → skill flounders. | Rule: invoke graphify ONLY when the user literally types `/graphify`. |
| `consult7` MCP | Sends repo code to an external Gemini API. | Rule: only on explicit user request; it is data egress. |
| Deferred tools | Calling a deferred tool without loading its schema → `InputValidationError`. | Rule: always `ToolSearch` with `select:<name>` first. |
| `superpowers` TDD skill | Demands test-first, but this repo has no test harness wired up and CI does not run tests. | Rule: verification = the checks in `judgment-matrix.md` §2, not TDD, until a real test suite exists. |

**Physical block (implemented).** The "Tool availability map" in `CLAUDE.md` is the single source of truth for what to call. If a tool is not in the map's USE column, a weak model needs a reason written in the task before calling it.

## 4. Leak #3 — Zero durability; knowledge evaporates

**Symptom.** Before 2026-07-08:
- `.gitignore` excluded `*.md` and `/.claude/*` → `CLAUDE.md` and every future harness file were **untracked**. One `git clean -fdx` or disk loss would erase the entire institutional memory.
- Lessons from past debugging sessions were dumped as new root-level `.md` files (also gitignored) — write-only memory nobody reads.
- `CLAUDE.md` contained rot magnets: hard line-number references ("GalleryController.php lines 72-88"), narrative history, and triple-duplicated Docker snippets. Line numbers rot on the next edit; a weak model trusting them edits the wrong lines.

**Physical block (implemented).**
1. `.gitignore` now: `!CLAUDE.md`, `!/.claude/rules/**` (version-controlled), `.codegraph/` and `*.bak` ignored. Backup: `.gitignore.bak`.
2. Rule: new lessons go into `.claude/rules/lessons.md` (format in `knowledge-iteration.md`), never as new root-level files.
3. Rule: durable docs reference code by **symbol name** (`GalleryController::store()`), never by line number.
4. `CLAUDE.md` rewritten as a router; heavy detail lives in `project-reference.md`.

## 5. Secondary findings (flagged, NOT changed — need user consent)

- **Over-broad permissions** in `.claude/settings.local.json`: `Bash(docker exec:*)` grants root shell into all 14 containers on this host (including other projects' databases); `Bash(curl:*)` allows arbitrary network calls. Recommended narrowing: `Bash(docker exec php-fpm-8.2*)`, `Bash(docker exec mysql-server*)`, `Bash(docker exec -it mysql-server*)`.
- **Plaintext secrets**: `~/.claude.json` embeds the context7 API key and a Google Gemini API key in MCP config; every session (and this report's author) can read them. Rotate if this machine is ever shared; consider env-var indirection.
- **Stale doc claims**: old CLAUDE.md said "No test suite currently exists" — technically true; the stubs in `tests/` are Laravel boilerplate. Preserved accurately in `project-reference.md`.
- `API_TIMEOUT_MS=3000000` (50 min) is unusually high; assumed intentional for long codex/consult7 calls.

## 6. Honesty clause — hard limits of this harness under weak models

Decomposition + isolated verification (per `model-dispatch.md`) approximates high-level quality for **mechanical** work: CRUD endpoints, migrations, store wiring, bug fixes with reproducible symptoms. It does **not** and cannot substitute for judgment on:

1. **Taste decisions**: UI layout/spacing choices, component API design, naming that must "read well", UX flows with multiple defensible options.
2. **Ambiguous product decisions**: what the user *meant* when the request underspecifies behavior (e.g., "make credentials safer" — encrypt at rest? mask in UI? both?).
3. **Cross-cutting architecture bets**: e.g., merging the three Vue apps into one SPA, switching storage providers.

**Mandatory response standard when a weak model hits one of these** (this overrides autonomy rules):
1. STOP implementation on that decision point (other independent work may continue).
2. Present 2–3 concrete options to the user with one-line trade-offs (use AskUserQuestion). Never resolve silently.
3. If the user is unavailable and the task cannot pause: pick the option that most resembles an **existing pattern in this repo**, mark the site with a `// HARNESS-TASTE: chose X over Y, unconfirmed` comment, and append a lessons.md entry so the choice is revisited.
4. If a fact cannot be verified with the tools available: report "not found / unverified". Fabricating a plausible answer is the worst failure mode this harness has; a wrong confident answer costs more than a stopped task.
