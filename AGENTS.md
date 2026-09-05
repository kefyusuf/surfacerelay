# SurfaceRelay Agent Instructions

This file is the authoritative workspace instruction file for coding agents and contributors.

## Mission

Build SurfaceRelay as a protocol-neutral action contract plus reference runtimes for stateful/server-driven web applications. Preserve application trust boundaries while allowing actions to be projected to WebMCP and other agent surfaces.

## Source-of-truth order

Before changing code, read in this order:

1. `STATUS.md` — current repository state and active task.
2. `TASKS.md` — task scope, acceptance criteria, and verification.
3. Relevant architecture/security docs under `docs/`.
4. Relevant ADRs under `docs/adr/`.
5. Existing tests and nearby implementation.

Do not rely on chat/session memory or assumptions when repository documents resolve the question.

## Work one task at a time

- Select exactly one task ID from `TASKS.md` unless the user explicitly groups tasks.
- Set one verifiable goal for the session.
- Do not begin the next task automatically after finishing the current task.
- Avoid unrelated refactors, formatting sweeps, dependency upgrades, and speculative features.
- If a required architecture decision is genuinely unresolved, record it in `STATUS.md` under `Needs decision` and stop before encoding a new public contract.

## Non-negotiable architecture rules

1. `ActionDefinition` must not import or encode WebMCP, MCP, Livewire, Filament, HTMX, HTTP, browser APIs, or driver-specific fields.
2. `RuntimeBinding` is a separate object and lifecycle from `ActionDefinition`.
3. Caller input is untrusted. Trusted actor, tenant, roles, permissions, confirmation authority, current record, and current selection come from trusted runtime context.
4. Discovery authorization and invocation authorization are separate checks.
5. Browser annotations are projections of core semantics; they are not core semantics.
6. Unknown or unsupported binding drivers fail closed.
7. Stale or expired bindings fail closed.
8. Consequential actions require an opaque runtime-issued confirmation receipt; a caller-supplied boolean such as `confirmed: true` is never sufficient.
9. Idempotency is enforced server-side for actions that require it.
10. Explicit exposure is the default. Do not create “expose everything” shortcuts.
11. Do not implement a new MCP protocol stack. Use an existing maintained implementation when MCP projection is added.
12. Do not add a WebMCP polyfill to core. Browser compatibility belongs behind the browser adapter.
13. Contract changes require schema, fixture, conformance, migration-impact, and decision updates in the same change.

## Security rules

Treat these as security-sensitive:

- authorization and tenant resolution;
- confirmation receipts;
- idempotency and replay protection;
- output redaction;
- runtime binding identity/lifecycle;
- cross-origin exposure;
- tool metadata generated from untrusted content;
- audit records containing sensitive inputs or outputs.

Any change touching one of these must include at least one negative test.

## Implementation discipline

Before editing:

- state the selected task ID;
- list the files likely to change;
- list the acceptance criteria you will prove;
- run or inspect the relevant baseline test when practical.

While editing:

- prefer the smallest implementation that satisfies the task;
- preserve public API compatibility unless the task explicitly changes it;
- keep framework-specific code inside adapters/drivers;
- do not silently broaden scope because another cleanup looks convenient.

Before completion:

1. Run the task's required verification commands.
2. Run `python scripts/validate.py`.
3. Review the full diff for architecture drift and security regressions.
4. Update the task status in `TASKS.md` only after verification passes.
5. Update `STATUS.md` with changed files, verification evidence, known limitations, and next task.
6. Update `REVIEW_REQUEST.md` with a concise external-review handoff.
7. Do not push, merge, tag, or publish unless the user explicitly requests it.

## Documentation discipline

- Documentation describes implemented behavior unless clearly marked `Proposed` or `Future`.
- Never claim conformance that is not backed by executable fixtures/tests.
- Keep `README.md` positioning conservative: this project is not an official W3C/WebMCP/MCP integration.
- New protocol-neutral vocabulary belongs in `docs/GLOSSARY.md` and `docs/DECISION-REGISTER.md`.

## Review checklist

A task is not complete until the answer to each relevant question is satisfactory:

- Did Action Definition remain independent of binding/surface details?
- Could caller-controlled data become trusted actor/tenant/selection/confirmation state?
- Does a stale, unknown, or malformed binding fail closed?
- Is discovery being mistaken for authorization?
- Did a new write/consequential path gain negative tests?
- Are protocol-specific annotations isolated to projection code?
- Did a public contract change without schema/fixture/ADR migration evidence?
- Are logs/audit records exposing sensitive values unnecessarily?
- Were all required verification commands actually run and recorded?
