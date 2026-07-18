# Model Dispatch & Escalation Protocol (Deliverable C)

Contract for the main-conversation model ("commander") and its subagents in this project. Written for Sonnet-level readers: follow the tables literally; do not improvise around them.

## 1. Commander role: decide, don't grind

The commander's context window is the scarcest resource in a session. Protect it.

**The commander itself does:** talking to the user, choosing what to do, writing subagent prompts (from `dispatch-templates.md`), small surgical edits (≤2 files AND ≤50 changed lines — 2 files but >50 lines is also a delegation, not a self-edit), judging reports, and final summaries.

**The commander MUST delegate (never do inline):**
- Any search/read task expected to open more than ~5 files or whose answer is unknown-shape ("where is X handled?", "find all callers of Y across the repo") → `Explore` agent, or 2–3 direct codegraph calls if the question maps to one symbol.
- Implementation touching ≥3 files → `general-purpose` agent with Template I.
- Verification of any delegated implementation → a **fresh** agent with Template V (see §5).

**Report-size rule for all subagents:** conclusions, file paths and symbol names, and ≤10 quoted lines per finding. Never paste whole files back to the commander. If the commander needs full source, it reads that one file itself.

*Positive example:* "Find how the frontend refreshes expired Supabase URLs" → spawn Explore with Template R; receive back: "`image.js` `refreshSignedUrl()` calls `GET /apiv/_1/images/{id}/signed-url`, handled by `ImageController::refreshSignedUrl()`; triggered from `GalleryIndex.vue` on image error event."
*Negative example:* commander greps, opens 14 files, pastes 3 of them into its own context, and by the time it edits, the original user request has been compacted away.

## 2. The three-piece dispatch package

Every subagent prompt MUST contain all three pieces. A dispatch missing any piece is malformed — rewrite it before sending.

1. **Goal + context**: what to achieve, why, and the 3–8 facts the agent cannot discover cheaply itself (branch, relevant symbols, constraints from `project-reference.md`, prior failed attempts).
2. **Acceptance criteria**: binary checks, each verifiable by a command or a file read. "Works correctly" is not a criterion; "`php artisan route:list | grep storage-credentials` shows 5 routes" is.
3. **Report format**: the exact fill-in-the-blank block from `dispatch-templates.md` — paths + symbols + line numbers of *changes made*, commands run with their real output, explicit `DONE` / `BLOCKED: <reason>` status.

## 3. Escalation / de-escalation ladder

Default worker model: **Sonnet**. Default commander: whatever the user launched (usually Opus).

| Trigger (exact) | Action |
|---|---|
| Haiku agent produces 1 tool-call error, syntax error, or hallucinated path | Re-dispatch the same task to **Sonnet** immediately. Do not give Haiku a second attempt. |
| Sonnet agent fails the **same subtask twice** (two verification failures, or two `BLOCKED` reports) | Escalate to **Opus** with the complete failure trace: both prompts, both reports, verifier output. Never resend to Sonnet with minor prompt tweaks a third time. |
| Opus (or user) solves it and the fix is a repeatable pattern (e.g., "all 6 modals need the same prop change") | De-escalate: write the solved pattern as an explicit recipe in the dispatch prompt and batch-apply via Sonnet/Haiku. Record the pattern in `lessons.md`. |
| Any model hits a taste/ambiguity boundary (`harness-diagnosis.md` §6) | Do NOT escalate models — escalate to the **user**. A bigger model cannot read the user's mind either. |

**Retry budget: one issue gets at most 2 retry rounds total across all models.** There is only ONE counter: a verification FAIL (§5) and a `BLOCKED` report each consume one retry round, and "fails the same subtask twice" in the table above is the same event as "retry budget exhausted" — not a second, separate counter. After round 2 fails, stop, write a `BLOCKED` summary (what was tried, exact errors, current hypothesis), and ask the user. Round counting is per *issue*, not per agent — reshuffling agents does not reset the counter.

Use Haiku only for mechanical batch work with a proven recipe (rename across files, apply an identical diff pattern). Never give Haiku open-ended investigation.

## 4. What escalation must carry

When escalating Sonnet→Opus, the dispatch MUST include, verbatim (not summarized): the original goal + acceptance criteria; attempt 1 prompt→result; attempt 2 prompt→result; verifier failure output; commander's current hypothesis. An escalation without the failure trace just makes Opus repeat attempt 1 at higher cost.

## 5. Isolated verification (non-negotiable)

**The agent that implemented a change never verifies its own work**, and the commander does not accept "I tested it and it works" from an implementer as evidence.

Procedure after any delegated implementation:
1. Spawn a fresh-context subagent (Sonnet) with Template V. It gets the acceptance criteria and the list of changed files — **not** the implementer's report or reasoning.
2. The verifier must (a) read the changed files back from disk, (b) actually run the acceptance-criteria commands (`git diff --stat`, `php -l`, `php artisan route:list`, curl against the local site, SQL checks per `project-reference.md` §6), and (c) return PASS/FAIL per criterion with pasted command output.
3. Any FAIL → counts as one retry round (§3); fix goes back to an implementer, then a **new** fresh verifier.
4. For taste-adjacent output (UI copy, component layout) where no command can decide: spawn 2 reviewers with Template V's review variant and different focus (correctness vs. consistency-with-existing-UI); the commander arbitrates their findings. This is the budget cap for "multi-sample review" — never more than 2 parallel reviewers.

*Negative example (forbidden):* implementer reports "all criteria pass", commander marks task complete without a verifier run. This is the #1 way silent breakage ships.
