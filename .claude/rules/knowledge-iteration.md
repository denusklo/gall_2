# Knowledge Iteration & Reflection Protocol (Deliverable F)

How future models update this harness without corroding it. The harness improves through small, evidenced appends — not through rewrites by whoever is running today.

## 1. Edit-permission tiers

| Tier | Files | Who may change, and how |
|---|---|---|
| **FREE** (edit without asking) | `lessons.md` (append entries; run compaction per §3); `project-reference.md` (update facts you have just verified by command/read — e.g. new endpoint added, container renamed) | Any model, any time. Every FREE edit must cite its evidence inline ("verified via `route:list` 2026-08-01"). |
| **PROPOSE-FIRST** (show diff, get user consent) | `CLAUDE.md`; `model-dispatch.md`; `judgment-matrix.md`; `dispatch-templates.md`; `knowledge-iteration.md` (this file); `.gitignore`; anything in `.claude/settings*.json` | Propose the exact diff + reason in the conversation; apply only after the user agrees. These files are the constitution — a weak model editing its own constraints is the primary decay vector (see `handoff-letter.md`). |
| **NEVER** | `harness-diagnosis.md`, `handoff-letter.md` (historical record from the 2026-07-08 Fable 5 session); `*.bak` files; anything in `~/.claude/` (global — affects other projects) | Read-only. If a statement in them has become false, do not edit it — append a lessons.md entry noting the correction, e.g. "diagnosis §3 says X; as of <date> that changed to Y". |

One template exception: **appending a brand-new template** to `dispatch-templates.md` for a task type that has none is FREE, provided existing templates are untouched and the new one follows the same section structure. Modifying an existing template stays PROPOSE-FIRST.

## 2. Lesson record format

Append to `lessons.md`, newest last. One entry = one pitfall or one proven pattern. Exactly this shape:

```markdown
### 2026-07-20 — Vue prop not reactive after credential edit
- **Context:** editing EditCredentialModal.vue while adding masked-display mode
- **Symptom:** modal showed stale credential name after save; no console error
- **Wrong path taken:** added a watch() on the prop (2 attempts, didn't fix)
- **Root cause / fix:** parent passed a spread copy `{...cred}`, so the object identity never updated; pass the store object directly
- **Rule of thumb:** stale-but-silent Vue UI → check object identity at the parent binding BEFORE adding watchers
- **Cost:** ~2 failed rounds
```

Required fields: date+title, Context, Symptom, Root cause / fix, Rule of thumb. `Wrong path taken` and `Cost` strongly encouraged — they are what saves the next model's retries. Do NOT record: generic best practices ("validate inputs"), one-off typos, anything already stated in `project-reference.md`.

Before appending, grep `lessons.md` for a keyword of your symptom. If a matching entry exists, update THAT entry (add a `Recurrence: <date>` line and refine the rule of thumb) instead of appending a duplicate.

## 3. Compaction trigger

When `lessons.md` exceeds **150 lines** (check with `wc -l` whenever you append), run compaction — this is FREE-tier work:

1. Copy `lessons.md` → `lessons.md.bak` (gitignored; git history also retains the old version).
2. Group entries by theme (Vue reactivity, Docker/env, storage providers, auth…).
3. Entries whose rule-of-thumb has held ≥3 times or is clearly general: distill to a single rule line under a `## Distilled rules` section at the top, then delete the long entries.
4. Entries that never recurred and are >6 months old: delete.
5. Result must be ≤80 lines. If a distilled rule is important enough to be constitution-level (belongs in `judgment-matrix.md` or CLAUDE.md), PROPOSE that promotion to the user — don't just move it.

## 4. Update procedure (any tier)

1. FREE tier: for wholesale rewrites (Write tool), make a `.bak` first; ordinary appends/Edits don't need one (git covers them).
2. PROPOSE-FIRST tier: always `.bak` before applying an approved diff.
3. Make the edit; read the changed section back from disk; confirm no truncation.
4. Never delete a rule as part of an unrelated task ("while I'm here…"). Rule changes are their own task with their own justification.
5. Committing harness changes follows the normal rule: only when the user asks.
