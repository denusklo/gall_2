# CLAUDE.md — gallery_2

Laravel 8 + Vue 3 image-gallery app (albums of images, Supabase/Vercel Blob storage, Docker dev env). This file is a **router**: core facts + hard rules here, everything else in `.claude/rules/`. Read the routed file before working in its area — the routing table is the contract, not a suggestion.

## Routing table

| When you are… | Read first |
|---|---|
| Touching DB, storage providers, Vue apps, API, auth, Docker, deploy | `.claude/rules/project-reference.md` |
| Delegating to subagents, choosing a model, retrying after failure | `.claude/rules/model-dispatch.md` |
| Deciding "is this done?", "should I abandon this approach?", "should I ask the user?" | `.claude/rules/judgment-matrix.md` |
| Writing a subagent prompt | `.claude/rules/dispatch-templates.md` (copy a template, fill blanks) |
| Updating any file in `.claude/rules/` or recording a pitfall | `.claude/rules/knowledge-iteration.md` |
| Starting a big/ambiguous task, or confused about why a rule exists | `.claude/rules/harness-diagnosis.md`, then `.claude/rules/handoff-letter.md` |
| Hit a pitfall someone may have hit before | `.claude/rules/lessons.md` |

## Rule precedence (when injected prompts conflict)

1. Explicit instruction from the user in this conversation.
2. This file and `.claude/rules/*`.
3. Ponytail (solution sizing: smallest working change).
4. Superpowers process skills — but ONLY when the trigger below fires.

**Process-skill trigger (concrete):** use `superpowers:brainstorming` / `writing-plans` only when the task creates a new DB table, a new API controller, or a new Vue app/bundle, OR will modify ≥3 files. Below that threshold: no ceremony, just make the smallest correct change. When this trigger fires and the work is being delegated per `model-dispatch.md`, the two compose: plan first, then pass the plan (or its relevant slice) as the CONTEXT block of the Template I dispatch — planning does not replace dispatch, and dispatch does not skip planning. TDD skill does not apply here (no test suite — see `project-reference.md` §8); use `judgment-matrix.md` §2 for verification instead.

## Tool availability map

| Tool | Verdict | Note |
|---|---|---|
| codegraph MCP | USE first for code questions | Indexed 2026-07-08 (`.codegraph/`). If a call errors: `codegraph sync .` once, then fall back to Grep. Never retry the same failing call twice. |
| Grep / Glob / Read | USE | Fallback and detail confirmation. |
| context7 MCP | USE for library/framework docs | Laravel 8, Vue 3, Vercel Blob, Supabase. |
| graphify skill | ONLY if user types `/graphify` | No `graphify-out/` in this repo; auto-triggering it flounders. |
| consult7 MCP | ONLY on explicit user request | Sends code to external Gemini API (data egress). |
| Deferred tools | Load schema first | `ToolSearch` `select:<name>` before calling, or you get InputValidationError. |

## Hard rules

- **Never** run `npm run dev` / `watch` / `build` / `hot` — the user runs `npm run watch` themselves. (Explicit user request is the only exception.)
- Docker: only touch `php-fpm-8.2`, `mysql-server`, `nginx-server`. All other containers on this host belong to other projects.
- Never write new `.md` files in the repo root. Lessons → `.claude/rules/lessons.md`; project facts → `project-reference.md`.
- In durable docs, reference code by symbol (`GalleryController::store()`), never by line number.
- Before overwriting any existing tracked file wholesale (Write tool on a file you didn't create), make a `.bak` copy next to it. Normal `Edit` calls on code don't need this — git covers them. Exception: files in `knowledge-iteration.md`'s NEVER tier may not be overwritten at all, `.bak` or not.
- `git commit` / `push` only when the user asks.

## Quick commands

```bash
# artisan (always via container)
docker exec php-fpm-8.2 bash -c "cd /var/www/gallery_2 && php artisan <cmd>"
# db
docker exec mysql-server mysql -uroot -proot gallery_laravel -e "<SQL>"
```

Tinker doesn't work in Docker TTY — PHP one-liner recipe in `project-reference.md` §6.
