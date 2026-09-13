<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 -->

---
name: engram-conventions
description: Engram persistent memory protocol - when to mem_save, topic_keys, conflicts, lifecycle. Triggers: every meaningful decision, bug fix, discovery, convention. End-of-session close.
---

# Engram Memory Conventions

## Trigger

Activate when:

- Any of the 11 mandatory `mem_save` triggers (see below).
- End of session (mem_session_summary - MANDATORY).
- Compaction / "FIRST ACTION REQUIRED" in context.
- Conflict detected by `mem_save` (candidates[]).

## The 11 mandatory save triggers

`mem_save` ALWAYS after ANY of these:

1. **Architecture / design decision** - `type: architecture`
2. **Team convention documented** - `type: pattern`
3. **Workflow change agreed** - `type: pattern`
4. **Tool / library choice made** - `type: decision`
5. **Bug fix completed** - `type: bugfix` (include root cause)
6. **Non-obvious feature** - `type: architecture`
7. **Notion/Jira/GitHub artifact created** - `type: discovery`
8. **Config / environment change** - `type: config`
9. **Non-obvious codebase discovery** - `type: discovery`
10. **Gotcha / edge case / unexpected behavior** - `type: bugfix`
11. **Naming / structure / convention pattern** - `type: pattern`

## Title rules

- **Verb + what** - short, searchable (< 50 chars).
- ✅ "Fixed N+1 in user list", "Chose Zustand over Redux", "JWT auth middleware"
- ❌ "Bug fix", "Update", "Work today"

## Content format

```yaml
content:
 **What**: one sentence - what was done
 **Why**: what motivated it (user request, bug, perf)
 **Where**: files/paths affected
 **Learned**: gotchas/edge cases (omit if none)
```

## Topic keys (for evolving decisions)

When the observation is part of a decision/topic that evolves, use a stable `topic_key`:

```yaml
topic_key: architecture/auth-model
topic_key: bugfix/cache-tenant-isolation
topic_key: pattern/named-args
```

Rules:

- Different topics → different keys (no overwrite cross-domain).
- Same topic evolving → same key (upsert).
- Unsure → `mem_suggest_topic_key` first.
- Use `mem_update` when you have an exact observation ID to correct.

## Conflicts (judgment_required)

After `mem_save`, check response envelope for `judgment_required: true`.

**Ask user** when:

- `confidence < 0.7` AND relation is `supersedes` or `conflicts_with` AND type is `architecture`, `policy`, or `decision`.

**Resolve silently** when:

- `confidence >= 0.7` AND relation is NOT `supersedes`/`conflicts_with`, OR
- relation is `related`, `compatible`, `scoped`, `not_conflict`.

How to ask:
> "I noticed memory #abc123 might conflict with what we just saved.
> Want me to mark the new one as superseding it, or are they about
> different scopes? I can also mark them as compatible if both still apply."

After resolution (both paths):
Call `mem_judge` with the chosen relation, a reason, and if the user
gave explicit direction, include their words as the evidence field.

## capture_prompt

- Default `true` for manual saves.
- `false` ONLY for: SDD artifacts, automated saves, skill-registry output, onboarding, init reports.
- If the schema doesn't have `capture_prompt`, omit the field.

## Session close (mandatory)

Before saying "done" / "listo" / "that's it":

```yaml
mem_session_summary(content: |
 ## Goal
 [What we were working on]
 
 ## Instructions
 [User preferences / constraints - skip if none]
 
 ## Discoveries
 - [Technical finding 1]
 - [Non-obvious learning 2]
 
 ## Accomplished
 - ✅ [Completed item - with details]
 - 🔲 [Identified but not done]
 
 ## Next Steps
 - [What remains]
 
 ## Relevant Files
 - path/to/file - [what changed]
)
```

**Skip = next session starts blind.** Not optional.

## Search before saving

Before saving something that looks duplicate:

1. `mem_search(query: "topic", project: "ecommerce")` → check existing.
2. If exists → consider `mem_update` (same topic_key) instead of new observation.
3. If not → `mem_save` with unique `topic_key`.

## Lifecycle (mem_review)

- `mem_review action=list` → observations with `review_after` passed.
- `needs_review` → stale memory, NOT trusted as fact.
- When a retrieved memory is marked `needs_review`, surface that stale
 context to the user and verify against current evidence before relying
 on it.
- **DO NOT** call `mem_review` with `mark_reviewed` automatically.
 Only call `mark_reviewed` after explicit user confirmation or through
 a dedicated memory maintenance command.

## Trigger patterns

Activate when:

- Architectural decision (a new service, a library change).
- Bug fix (root cause + fix).
- New convention (naming, structure, lint rule).
- Critical config (env, Docker, tenant).
- Session closing.
- "We decided X" / "Going with Y" / "Fixed Z" - save BEFORE responding.

## Anti-patterns

- ❌ `mem_save` of trivial changes (typo fix, import reorder).
- ❌ Generic title ("Update", "Fix").
- ❌ Without `topic_key` when it's an evolving decision.
- ❌ Skip `mem_session_summary` when closing.
- ❌ Marking `mark_reviewed` automatically.
- ❌ Trusting `needs_review` memory without verifying.

## Project conventions

- `project: "ecommerce"` (configured via git remote).
- Most used types in this repo: `architecture`, `decision`, `pattern`, `bugfix`.
- Frequent topic keys: `architecture/auth-model`, `architecture/auth-accent-palette`, `pattern/named-args`.

## Related skills

- `skill-creator` - for new skills (capture pattern first).
- `sdd-archive` - when closing an SDD change, archive artifacts in Engram.
