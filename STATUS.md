# Project Status

> Current repository state for M6 / T-601 design review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-binding-descriptor`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; M5 DONE/REVIEWED/MERGED/MAIN REVALIDATED; **M6 IN PROGRESS — DESIGN ONLY**
- **Last completed task:** `T-505 — Multi-tenant order operations demo`
- **Current task:** `T-601 — Explicit HTMX binding descriptor`
- **T-601 status:** **DESIGN APPROVED IN CHAT / WRITTEN SPEC READY FOR REVIEW / IMPLEMENTATION NOT STARTED**
- **Base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Base validation:** `34621807162` — **7/7 green**
- **Design spec:** `docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md`
- **Decision:** `D-053` — **PROPOSED**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Implementation plan:** **NOT STARTED; requires written-spec approval first**
- **Production/test implementation changes:** **NONE**
- **PHP baseline:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser baseline:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract / lint / Composer baseline:** green

## T-601 objective

Define the smallest explicit HTMX RuntimeBinding target descriptor that demonstrates the existing generic RuntimeBinding envelope can carry a second materially different driver-owned target without contaminating ActionDefinition semantics or weakening exact-target/trusted-authority rules.

T-601 is descriptor-only. Browser execution begins in T-602.

## Approved design

Reference RuntimeBinding shape:

```text
driver = htmx
lifecycle = page

target = {
  sourceId,
  method,
  path,
  inputNames,
  requiredInputNames
}
```

### Target identity

- `sourceId` identifies one exact rendered HTMX source element instance.
- It is opaque, fresh per materially rendered/replaced source, and never a record/tenant/authorization identity.
- A replacement source does not inherit the old binding merely because it has the same endpoint, class, text, DOM position, or business record.
- Missing exact source will be stale in T-602; no rediscovery/retarget fallback is permitted.

### Request contract

- supported methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE` only;
- method matching is exact;
- `path` is a bounded same-origin absolute-path reference, optionally with query string;
- scheme/host authority, `//...`, fragments, backslashes, controls, and relative paths are rejected;
- T-602 must revalidate exact method/path against the resolved source before dispatch.

### Action-input mapping

- HTMX mapping is named, not positional;
- `inputNames` is the exact finite top-level caller-input field set;
- `requiredInputNames` is the exact required subset;
- caller/schema object key order has no authority meaning;
- optional fields may be independently omitted;
- the reference descriptor derives the sets from a closed top-level object `ActionDefinition.inputSchema`;
- schemas with open-ended top-level keys fail descriptor issuance rather than guessing;
- nested values remain nested; no bracket/dotted form-path convention is invented.

### Trust boundary

The descriptor never carries or manufactures:

```text
authenticated actor
tenant
roles / permissions
current record
current selection
browser-session authority
human confirmation
confirmation receipt/challenge
raw idempotency key
authorization decisions
```

Ordinary HTMX form/request values such as hidden `order_id`, `tenant_id`, or CSRF fields remain ordinary untrusted request data from SurfaceRelay's perspective. Server-side trusted context and authorization remain independent.

## Architecture boundary

T-601 production code is expected only in browser-runtime as pure descriptor construction/validation:

```text
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/src/index.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
```

T-601 must not add or change:

```text
packages/laravel/src/**
packages/browser-runtime/src/htmx-browser-driver.ts
examples/htmx/**
spec/0.1/**
HTMX runtime/package dependencies
DOM/network/cancellation execution
```

If implementation appears to require any of those, stop and reopen the design gate.

## D-053 proposed wording

> HTMX RuntimeBindings use `driver=htmx` with `page` lifecycle and an explicit driver-owned target that pins one opaque rendered source-element identity, one same-origin HTMX request method/path, and an allowlisted named Action-input mapping. The binding carries no trusted actor/tenant/record/selection authority and no confirmation/idempotency capability. Execution must resolve the exact source instance, verify that its request contract still matches the binding, and fail stale rather than rediscovering or silently retargeting a replacement element.

D-053 remains `PROPOSED` through the design gate and is eligible for `ACCEPTED` only after T-601 executable descriptor tests pass. D-020 remains proposed until shared Livewire/HTMX conformance in T-604.

## Design alternatives ruled out

- **Custom HTMX event binding:** rejected because it pushes input semantics into host event/`hx-vals` conventions and weakens request/result determinism.
- **Method/path-only raw HTTP binding:** rejected because it detaches agent execution from the exact human-facing HTMX source and weakens stale-target semantics.
- **Laravel-side HTMX factory:** rejected because the second portability binding must not depend on the first framework runtime.

## Verification baseline

Current base before T-601 implementation:

```text
main:                           5b22eef928d2fb1f8fac8ab13507e2f22661d3df
closure validation:             34621807162 — 7/7 green
PHP:                             595 tests / 3164 assertions
Browser:                         TypeScript typecheck + 103/103 Vitest
Contract / lint / Composer:      green
```

No T-601 code/tests have been added yet.

## Current boundary

The in-chat architecture is approved and the written spec is committed. **Stop before `writing-plans` and before implementation.** The next gate is explicit user review/approval of `docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md`.