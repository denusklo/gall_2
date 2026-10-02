# Standardized Dispatch Prompt Templates (Deliverable E)

Copy the template, replace every `{{BLANK}}`, delete the lines marked `(optional)` if unused. A dispatch with an unfilled `{{BLANK}}` is malformed. All templates already embed the three-piece package from `model-dispatch.md` §2 — do not strip sections to "save tokens"; the sections ARE the error prevention.

Every template's context block starts with this line (it stops subagents from re-deriving the world):

> Project: Laravel 8 + Vue 3 gallery app at /home/denusklo/workspace/gallery_2. Before working, read CLAUDE.md and .claude/rules/project-reference.md. Obey CLAUDE.md hard rules (no npm builds; Docker only via php-fpm-8.2/mysql-server; report by symbol+path).

---

## Template R — Research / search  (agent: `Explore`, model: sonnet)

```
Project: Laravel 8 + Vue 3 gallery app at /home/denusklo/workspace/gallery_2. Read-only task. Report by symbol+path, quote ≤10 lines per finding.

GOAL: Answer this question: {{ONE_SENTENCE_QUESTION}}
WHY: {{WHAT_DECISION_THIS_ANSWER_FEEDS}}
KNOWN ALREADY (do not re-verify): {{FACTS_COMMANDER_ALREADY_HAS}}
SEARCH BREADTH: {{medium | very thorough}}
START POINTS (optional): {{FILES_OR_SYMBOLS_TO_START_FROM}}

ACCEPTANCE CRITERIA:
- Every claim cites file path + symbol name (+ line number for the current state).
- If the answer is "not found", say NOT FOUND and list where you looked. Never infer an answer from naming alone.

REPORT FORMAT (fill exactly):
ANSWER: <2-5 sentences>
EVIDENCE: <bullet list: path — symbol — what it shows, ≤10 quoted lines each>
NOT FOUND / UNCERTAIN: <anything you could not confirm>
```

---

## Template I — Feature implementation  (agent: `general-purpose`, model: sonnet)

```
Project: Laravel 8 + Vue 3 gallery app at /home/denusklo/workspace/gallery_2. Before working, read CLAUDE.md and .claude/rules/project-reference.md. Obey CLAUDE.md hard rules.

GOAL: {{WHAT_TO_BUILD_ONE_PARAGRAPH}}
CONTEXT: {{3-8 FACTS: branch, relevant symbols, related endpoints, prior attempts, patterns to copy}}
IN SCOPE FILES (expected): {{FILE_LIST}}
OUT OF SCOPE: do not modify anything outside the expected files without listing it under DEVIATIONS. Never edit public/js/*, mix-manifest.json, vendor/, node_modules/.
PATTERN TO FOLLOW: {{EXISTING_FILE_OR_SYMBOL_TO_IMITATE}} — copy its structure/style; do not invent new patterns.

ACCEPTANCE CRITERIA (each must be a runnable check):
1. {{e.g. php artisan route:list (in container) includes POST /apiv/_1/...}}
2. {{e.g. curl -s http://localhost/... returns JSON with fields a,b,c}}
3. php -l passes on every changed PHP file (run in php-fpm-8.2 container).
4. git diff --stat touches only in-scope files (or deviations explained).
{{MORE_CRITERIA}}

Do NOT self-certify beyond running these commands; verification is done separately by another agent.

REPORT FORMAT (fill exactly):
STATUS: DONE | BLOCKED: <reason>
CHANGED: <path — symbol — one-line what/why, per file>
COMMANDS RUN: <command → key output lines, pasted not paraphrased>
DEVIATIONS: <out-of-scope touches + justification, or "none">
PENDING: <anything unverifiable now, e.g. "runtime check pending user's npm watch">
```

---

## Template F — Refactoring  (agent: `general-purpose`, model: sonnet)

```
Project: Laravel 8 + Vue 3 gallery app at /home/denusklo/workspace/gallery_2. Read CLAUDE.md and .claude/rules/project-reference.md first.

GOAL: Refactor {{TARGET_SYMBOLS_OR_FILES}} to {{DESIRED_SHAPE}} with ZERO behavior change.
WHY: {{MOTIVATION}}
CONTEXT: {{CALLERS/CONSUMERS — get from codegraph_callers or provide here}}

INVARIANTS (behavior that must not change — check each before AND after):
1. {{e.g. GET /apiv/_1/galleries response JSON shape unchanged (curl before/after, diff the output)}}
2. {{e.g. all N callers of <symbol> still resolve — codegraph_callers or grep list before/after}}

ACCEPTANCE CRITERIA:
1. Each invariant has pasted before/after evidence.
2. php -l passes on all changed PHP files (in container).
3. No public route, model attribute, or exported store action renamed unless listed in GOAL.
4. Net diff is not larger than the code it replaces unless GOAL says otherwise.

REPORT FORMAT: same block as Template I (STATUS/CHANGED/COMMANDS RUN/DEVIATIONS/PENDING) plus:
INVARIANT EVIDENCE: <before/after output per invariant>
```

---

## Template V — Verification / code review  (agent: fresh `general-purpose` or `superpowers:code-reviewer`, model: sonnet)

Use after ANY delegated implementation. The verifier gets acceptance criteria + changed-file list ONLY — never the implementer's report.

```
Project: Laravel 8 + Vue 3 gallery app at /home/denusklo/workspace/gallery_2. You are a fresh-context verifier. You did NOT write this change; trust only what you read and run yourself. Read CLAUDE.md first.

CHANGED FILES (verify from disk, read them fully): {{FILE_LIST}}
TASK THE CHANGE CLAIMS TO ACCOMPLISH: {{ONE_PARAGRAPH_GOAL}}

CHECK EACH CRITERION BY ACTUALLY RUNNING IT (paste real output):
1. {{CRITERION_1_AS_COMMAND}}
2. {{CRITERION_2_AS_COMMAND}}
3. git diff --stat — flag any file outside the changed-files list.
4. Scan the diff for: added dd(/var_dump/console.log; commented-out safety code (middleware, validation, constraints); hardcoded secrets; edits to public/js or vendor.

ALSO REVIEW (judgment, cite evidence): does the change break any caller of the modified symbols? (use codegraph_callers or grep each modified public symbol)

REPORT FORMAT (fill exactly):
VERDICT: PASS | FAIL
PER CRITERION: <#N: PASS/FAIL — pasted output>
DEFECTS: <path — symbol — defect — how to reproduce; or "none">
NOT VERIFIABLE: <criteria you could not run and why>
```

Multi-sample review variant (taste-adjacent output only, max 2 reviewers per `model-dispatch.md` §5): dispatch Template V twice with different FOCUS lines — reviewer A: "FOCUS: correctness and caller breakage"; reviewer B: "FOCUS: consistency with existing patterns in resources/js/components/". Commander arbitrates.
