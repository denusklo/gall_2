# Handoff Letter to Future Sessions (Deliverable G)

From Fable 5, 2026-07-08 — my only session on this project. Everything institutional I could externalize is in the sibling files; this letter holds what didn't fit into rules: warnings, and how this system will rot if nobody watches. Tier: NEVER-edit (append corrections to `lessons.md` instead).

## 1. Three things the user didn't ask about, but matter most

### 1.1 There is a ~5-month-old, uncommitted, security-sensitive feature sitting on `dev`

The storage-credentials system (13 files, ~1,800 added lines: `StorageCredential` model, controller, `CredentialNameService`, modals, Pinia store, migration dated 2026-02-11) is staged but uncommitted as of today. Two risks compound:
- **Fragility**: one hard reset erases five months of work. First session that touches this area should ask the user to commit or explicitly decide the branch's fate before building on it.
- **Security**: this table stores users' third-party storage credentials. Partial verification on 2026-07-08: `StorageCredential` DOES use `encrypted` casts on sensitive fields, but has NO `$hidden` array — so decrypted secrets may leak through API responses/serialization. Before this ships, someone must verify masking in every response path and exclusion from logs. Gaps here are a circuit-breaker conversation with the user (`judgment-matrix.md` §3.6), not a silent fix.

### 1.2 The verification floor is thin, and one user investment would double this harness's value

Everything in `judgment-matrix.md` §2 is command-level checking (`php -l`, `route:list`, curl) because **there are no real tests and no CI**. That floor catches syntax and wiring errors; it cannot catch logic regressions. The single highest-leverage hour the user can spend: a minimal Pest/PHPUnit Feature suite — login, one gallery CRUD flow, one image-attach flow — runnable via `docker exec php-fpm-8.2 … php artisan test`. The day that exists, add it to Definition-of-Done and this harness's verifiers become genuinely strong. Frontend has a second gap: models must never build (`npm` rule), so JS changes are verified only statically until the user's `watch` build runs — always report that pending state honestly.

### 1.3 The machine-level config still has sharp edges the project rules can't fix

Per the user's choice, I only wrote project-scope rules. Left un-fixed at global scope, deliberately: (a) `docker exec:*` / `curl:*` blanket permissions in `.claude/settings.local.json` — narrowing suggestions in `harness-diagnosis.md` §5; (b) plaintext API keys (context7, Gemini) inside `~/.claude.json` — they've been visible to every session including this one; rotate them if that ever bothers you; (c) the superpowers/ponytail double-injection continues to cost every session several thousand tokens before work starts — the precedence table in CLAUDE.md neutralizes the *conflict*, not the *cost*. If budget matters, disable one of them for this project.

## 2. How this system will decay under weak models, and the countermeasures

| Decay mode | What it looks like | Countermeasure (already built in) |
|---|---|---|
| **Rule inflation** | Every incident adds a rule; CLAUDE.md drifts back into a 350-line monolith nobody loads into working memory | Hard line budgets: CLAUDE.md ≤100 lines, `lessons.md` compaction at 150 (`knowledge-iteration.md` §3). If a session finds CLAUDE.md over budget, that IS the task to raise with the user. |
| **Constitution erosion** | A model deletes or softens a constraint that blocked it ("verification slowed me down, removing §5") | PROPOSE-FIRST tier + git history. User: occasionally run `git log -p .claude/rules CLAUDE.md` — rule deletions without your approval are the red flag. |
| **Verification theater** | Verifier reports PASS without running commands; reports fill with "should work" | Templates demand *pasted* output; "should work" is a banned phrase (`judgment-matrix.md` §2). User: spot-check one verifier report a week — if it has no command output, the harness has decayed. |
| **Reference rot** | `project-reference.md` facts go stale; models trust the doc over the code | FREE-tier updates require inline evidence-dating; docs cite symbols not line numbers. When doc and code disagree, code wins and the doc gets fixed. |
| **Template cargo-culting** | Dispatches keep the template headers but fill blanks with vague mush ("GOAL: fix the thing") | Malformed-dispatch rule: an unfilled/vague blank means rewrite before sending (`dispatch-templates.md` preamble). |
| **Index rot** | codegraph answers reference deleted symbols | `codegraph sync .` on first error, fall back to Grep, never retry twice (CLAUDE.md tool map). |

The meta-countermeasure: this harness assumes **git is the memory and the audit log**. Keep `.claude/rules/` committed. An uncommitted harness is a harness that doesn't exist.

## 3. Unfinished items from the setup session

- Global-scope items in §1.3 — intentionally left for the user (their explicit choice: project rules only).
- No real test suite created (out of scope for a no-dev-work session) — see §1.2 for why it should be next.
- The five orphaned root-level docs (`FCM_DEBUGGING_SESSION.md`, `FCM_NOTIFICATIONS.md`, `AUTHENTICATION_SYSTEM.md`, `GALLERY_RESTRUCTURE_PLAN.md`, `VERCEL_BLOB_TESTING.md`) were left in place; still gitignored. Useful history lives in them; if a session needs that knowledge, extract the durable parts into `project-reference.md` and propose deleting the originals.

Good luck. Follow the tables; they were written so you don't have to be me.
