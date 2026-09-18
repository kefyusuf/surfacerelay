# T-703 External Review Handoff — Laravel MCP Projection

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/t-703-laravel-mcp-projection`
- **Task:** `T-703 — Laravel MCP projection using a maintained MCP implementation`
- **State:** **IMPLEMENTED / REVIEW PENDING**
- **Review base:** `912bcbdcc53f4b2340c56e2b940f2e1521f7de37`
- **Verified documentation / review-prep head:** `ca03e02abfb53c1b260e05f02014bb11c2787d58`
- **Validate:** #863 / `35400607926` — **11/11 SUCCESS**
- **D-063 / D-064:** **PROPOSED**
- **D-026:** **PROPOSED** and unchanged
- **T-704:** **NOT STARTED**

This is an external-review handoff, not a merge or decision-promotion request. Do not treat discovery as authorization, do not widen trusted authority, and do not broaden T-701 conformance while reviewing this change.

## Review scope

T-703 adds an optional `packages/laravel-mcp` bridge that projects explicitly exposed SurfaceRelay Actions through the maintained `laravel/mcp` package.

Review the following boundaries:

1. **Dependency direction** — `packages/laravel` must remain MCP-independent; the optional bridge may depend on `surfacerelay/laravel` and `laravel/mcp`, never the reverse.
2. **Explicit exposure** — only exact `Action id + version` entries explicitly added to `McpActionExposureRegistry` may appear in MCP discovery. No registry-wide, wildcard, reflection, or "latest" exposure path may exist.
3. **Eligible scopes** — only `portable` and `headless` Actions may be exposed. `page_scoped` and `browser_local` must fail closed.
4. **Exact projection** — tool identity must be deterministic and fail closed; canonical `inputSchema`, title, and description must not be widened or rebuilt through a second schema model.
5. **Trust boundary** — MCP arguments remain untrusted Action input. Caller actor/tenant/current-record/current-selection fields must not become trusted context.
6. **Metadata authority** — `io.surfacerelay/confirmationReceipt` and `io.surfacerelay/idempotencyKey` are bounded, namespaced candidates only. Existing server-side confirmation/idempotency stages remain authoritative.
7. **Invocation convergence** — every tool call must converge on the existing `ActionBus` and `ActionResultNormalizer`; there must be no second business execution path.
8. **Result/error boundary** — normalized `ActionResult` semantics, including rejection, failure, and `confirmation_required`, must remain structured and unchanged.
9. **Annotations** — v1 maps only `effect=read -> readOnlyHint=true`; no unsupported semantics should be inferred.
10. **Host authority** — route placement, authentication, OAuth, transport/network policy remain host-owned; the service provider must not auto-register them.
11. **Conformance isolation** — T-701's closed browser conformance profile, targets, model, and runner must remain unchanged.
12. **Decision state** — D-063/D-064 and D-026 must remain PROPOSED until a separate post-review decision gate.

## Implemented surface

Primary bridge files:

- `packages/laravel-mcp/src/Exposure/**`
- `packages/laravel-mcp/src/Projection/**`
- `packages/laravel-mcp/src/Invocation/**`
- `packages/laravel-mcp/src/Server/**`
- `packages/laravel-mcp/src/SurfaceRelayMcpServiceProvider.php`
- `packages/laravel-mcp/README.md`

Test evidence covers:

- dependency isolation;
- exact exposure and unsupported-scope rejection;
- deterministic tool projection and schema/annotation mapping;
- bounded MCP metadata extraction;
- trusted-context separation;
- authorization denial before execution;
- sensitive-output fail-closed behavior and trusted redaction;
- structured audit minimization;
- maintained Laravel MCP tools/list + tools/call integration;
- confirmation challenge/approval/single-use authority;
- idempotency required-key, exact replay, and changed-intent conflict behavior.

## Full verification

Verified at `ca03e02abfb53c1b260e05f02014bb11c2787d58`:

```text
Validate:                         #863 / 35400607926 — 11/11 SUCCESS
Laravel MCP compatibility:        4/4 matrix jobs SUCCESS
Laravel MCP bridge suite:         47 tests / 337 assertions
Bridge composer validate:         PASS
Base Laravel compatibility:       4/4 matrix jobs SUCCESS
Base Laravel suite:               595 tests / 3164 assertions
Base Laravel composer validate:   PASS
PHP lint:                         PASS
Contract / scripts/validate.py:   PASS
Browser typecheck:                PASS
Browser Vitest:                   20 files / 328/328 PASS
Python conformance:               47/47 PASS on CPython 3.12.14
Harness build:                    PASS
Canonical runtime matrix:         7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

Canonical runtime result remains unchanged:

```text
BIND-EXACT-TARGET-EXECUTES       Livewire PASS / HTMX PASS
BIND-EXPIRED-NOT-EXECUTABLE     Livewire PASS / HTMX PASS
BIND-COMPONENT-STALE             Livewire PASS / HTMX NOT_APPLICABLE
BIND-NO-SILENT-RETARGET          Livewire PASS / HTMX PASS
```

## Scope audit

`main...ca03e02abfb53c1b260e05f02014bb11c2787d58` was audited across every changed path.

The required forbidden-diff set is empty:

```text
packages/laravel/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

No base Laravel production semantics, canonical spec semantics, or T-701 conformance semantics changed.

The Task 8 documentation commit also corrected one stale class-level comment in `SurfaceRelayActionTool` that still described the pre-Task-4 discovery-only state. The change is comment-only and introduces no behavior.

## Self-review

**PASS** on:

- scope alignment;
- design / decision / invariant consistency;
- one-way dependency direction;
- explicit exposure and discovery-vs-authorization separation;
- caller-input vs trusted-context authority;
- confirmation/idempotency authority;
- ActionBus/result convergence;
- no unnecessary second protocol/runtime abstraction;
- brownfield safety: optional package, no automatic route/auth/exposure registration;
- verification evidence and forbidden-diff audit.

No self-review blocker remains before external review.

## Stop boundary

T-703 is **implemented but not closed**.

External review must complete before any decision-promotion or merge gate. Until then:

- keep D-063 / D-064 PROPOSED;
- keep D-026 PROPOSED;
- do not merge T-703;
- do not mark T-703 DONE/REVIEWED;
- do not begin T-704.
