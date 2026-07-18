# Judgment Externalization Matrix (Deliverable D)

Fable 5's judgment, flattened into checklists. Usage: when you feel uncertainty, find the matching row and do what the row says — do not re-derive the judgment yourself. ✅ = perfect positive example, ❌ = typical negative example.

## 1. Abandon-the-path signals

Any ONE of these means: stop patching the current approach, write down why it failed, and take a structurally different path (or escalate per `model-dispatch.md` §3).

| # | Signal | ✅ Right response | ❌ Wrong response |
|---|---|---|---|
| 1.1 | Your fix works only if you add a special case, and the special case then needs its own special case | "Two nested special cases in `ImageController::upload` — the branch on `storage_provider` belongs in the model. Restarting with a method on `Image` instead." | Adding a third `if ($provider === 'vercel' && $legacy && !$callback)` branch. |
| 1.2 | The same error message survives two genuinely different fixes | "419 persists after both the CSRF-token fix and the session-domain fix — my model of the cause is wrong. Re-diagnosing from the raw request/response instead of fixing again." | Trying a third variation of the CSRF fix because "it must be close". |
| 1.3 | You are editing framework/vendor internals, generated files (`public/js/*`, `mix-manifest.json`), or files git says you didn't need before | Stop; the correct change is in `resources/js/` or app code. Generated output is never the fix site. | "I'll just patch the compiled `public/js/galleryApp.js` since watch isn't running." |
| 1.4 | The diff keeps growing while the acceptance criteria stay unmet (>2× the size you estimated at start) | Revert to last green state (`git stash` / `git checkout -- <files>`), re-plan with the failure knowledge. | Keeping 400 lines of exploratory edits "since I'm close". |
| 1.5 | You're about to disable a safety mechanism to make the symptom disappear (auth middleware, CSRF, unique constraint, validation rule) | Treat the blocked mechanism as the diagnosis clue: WHY does it fire? Fix the caller. | Commenting out `->middleware('auth:sanctum')` to make the 401 go away. |
| 1.6 | The approach requires knowledge you've now failed to find twice (undocumented Vercel Blob behavior, Supabase quirk) | Check context7 docs once; if still unknown, mark `BLOCKED: unverifiable` and ask the user. | Guessing the token payload format and shipping it because it "looks right". |

## 2. Definition of Done — ALL boxes checked before claiming complete

A task is deliverable only when every applicable item passes **with command output as evidence in your report**. "Applicable" is determined by what the diff touches.

**Always (any change):**
- [ ] `git diff --stat` shows changes ONLY in files the task was expected to touch; every unexpected file is explained or reverted.
- [ ] Every acceptance criterion from the dispatch has an explicit PASS with pasted evidence.
- [ ] No debug artifacts left: no `dd(`, `var_dump(`, `console.log(` added by this change (`git diff | grep -E '^\+.*(dd\(|var_dump|console\.log)'` returns nothing you added on purpose-free lines).

**PHP touched:**
- [ ] `docker exec php-fpm-8.2 bash -c "cd /var/www/gallery_2 && php -l <each changed file>"` — no syntax errors.
- [ ] Routes touched → `php artisan route:list` (in container) shows the expected routes.
- [ ] Migration added → `php artisan migrate` runs clean AND the table/columns verified via one SQL query (`project-reference.md` §6).

**JS/Vue touched:**
- [ ] The user runs `npm run watch`; you do NOT build. Instead: confirm the changed files have no syntax errors (`node --check` for plain JS; for `.vue`, re-read the file checking template/script tag balance) and state in the report that runtime verification is pending the user's watch build. That pending state must be in the report — silence about it counts as a false completion claim.
- [ ] New endpoint consumed by frontend → verify with `curl` against the local API (auth per `project-reference.md` §5) that request/response shapes match what the store expects.

**❌ The canonical false-done:** "Implemented the endpoint and updated the store, everything should work now" — no route:list output, no curl, no lint. This exact sentence pattern ("should work") is banned from completion reports.

## 3. Circuit breaker — stop autonomous work and ask the user when:

| # | Condition (quantified) | Why user, not retry |
|---|---|---|
| 3.1 | Retry budget exhausted: 2 failed rounds on one issue (`model-dispatch.md` §3) | More attempts = more damage, not more progress. |
| 3.2 | The task requires destroying data or state you didn't create: dropping/renaming a DB table with rows, deleting user files, `migrate:fresh`, force-push | Irreversible; approval in one context doesn't extend to this one. |
| 3.3 | Two rule sources genuinely conflict for the concrete step AND the precedence order in CLAUDE.md doesn't resolve it | A silent choice hides the conflict forever. |
| 3.4 | The request has ≥2 defensible interpretations whose implementations don't overlap (e.g. "make credentials safer": encrypt-at-rest vs mask-in-UI) | Taste/intent boundary — see §4. |
| 3.5 | You'd need to touch anything outside this repo (other containers, global `~/.claude` config, other projects' DBs) | Explicitly out of scope for this project's harness. |
| 3.6 | Secrets: task needs a credential that isn't already in `.env`, or would print/commit one | Never invent, fetch, or expose credentials on your own. |

✅ Circuit-break message format: one paragraph — what was attempted, exact blocking condition (row number), the 2–3 options you see. ❌ Wrong: silently picking an interpretation, or asking "should I continue?" without options.

## 4. Taste boundary

Weak models must never solo-decide: visual design choices beyond copying an existing component's pattern, renaming public API routes, component API redesign, or anything the user might describe with "feels/looks/cleaner". Full protocol: `harness-diagnosis.md` §6 (present options → if forced to proceed, copy the nearest existing repo pattern + `HARNESS-TASTE` marker + lessons.md entry).

✅ "New CredentialCard needs an empty state; `GalleriesIndex.vue` has an empty-state block, reusing its exact markup/classes." (Copying an existing pattern is not a taste decision — it's the default.)
❌ Inventing a new card layout with different spacing/colors because it "looks better".
