# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

## M0 — Contract Foundation — DONE

| Task | Status | Outcome |
|---|---|---|
| T-001 — Lock v0.1 semantic vocabulary | DONE | Protocol-neutral scope/effect/risk/idempotency/context vocabulary. |
| T-002 — Add schema fixture matrix | DONE | Valid/invalid fixture manifest with intended-failure assertions. |
| T-003 — Lock RuntimeBinding lifecycle semantics | DONE | Extensible drivers, lifecycle/expiry/stale fail-closed semantics. |
| T-004 — Align RuntimeBinding ActionReference identity | DONE | Canonical Action ID grammar + drift guard. |
| T-005 — Align Invocation ActionReference identity | DONE | Canonical `id + version` across definition/binding/invocation. |

`spec/0.1` is provisional and re-frozen after M1.1.

## M1 — Laravel Kernel — DONE

| Task | Status | Outcome |
|---|---|---|
| T-101 — ActionDefinition value object | DONE | Immutable protocol-neutral PHP representation. |
| T-102 — ActionRegistry | DONE | Exact `id + version`, duplicate/missing fail loudly. |
| T-103 — Scalar PHP input schema compiler | DONE | Scalar/nullable reflection → JSON Schema. |
| T-104 — Enum/description schema compilation | DONE | Backed enums + explicit parameter descriptions. |
| T-105 — InvocationContext trust boundary | DONE | Trusted `ContextRequirement` entries separated from caller metadata/input. |
| T-106 — ActionBus pipeline shell | DONE | Canonical fail-closed six-stage orchestration + audit finalizer. |
| T-107 — Laravel validation stage | DONE | Explicit per-action rules; validated dataset only. |
| T-108 — Actor/tenant resolver contracts | DONE | Trusted runtime resolution before dispatch. |
| T-109 — Gate/Policy authorization adapter | DONE | Exact trusted actor via user-scoped Gate. |
| T-110 — Normalized ActionResult/Error model | DONE | Safe public result/status/error normalization. |

## M1.1 — Hardening — DONE / REVIEWED

| Task | Status | Outcome |
|---|---|---|
| H-000 — Public tool-neutral baseline | DONE | Local tool ignores removed from tracked repo; main root rewritten. |
| H-001 — Contract shape hardening | DONE | Result shapes, date-time enforcement, bindingId parity, extension grammar. |
| H-002 — Output classification redesign | DONE | `outputSensitivity` and `outputContentTrust` split; D-032. |
| H-003 — PHP/package hardening | DONE | mbstring requirement, enum alignment, safe halt details, RFC3339 challenge dates. |
| H-004 — Repo/CI/license/docs hardening | DONE | Full Apache-2.0, real CI matrix, browser CI, source-of-truth cleanup. |
| R-001 — Validator exception-path fix | DONE | Valid-fixture errors use imported `ValidationError`. |
| R-002 — Null result payload rejection | DONE | `error`/`confirmation` are object-only; negative fixtures added. |
| R-003 — Halt-detail list-shape enforcement | DONE | Associative arrays fail safe normalization. |
| R-004 — Source-of-truth final alignment | DONE | TASKS/STATUS/REVIEW_REQUEST + D-017 aligned. |
| R-005 — Browser CI reproducibility | DONE | Lockfile installs use `npm ci`. |

Reviewed M1.1 baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

---

## M2 — Livewire Binding — IN PROGRESS

### T-201 — Implement Livewire RuntimeBinding descriptor — DONE

**Goal:** Represent Livewire-specific execution target data only in RuntimeBinding/driver code.

**Implemented:**

- `BindingLifecycle` exact frozen vocabulary: `page/component/session/persistent`.
- immutable generic `RuntimeBinding` over exact `ActionDefinition.id + version`.
- construction validation for binding ID, driver grammar, non-empty object target, RFC3339 expiry, and namespaced extensions.
- `LivewireBindingTarget(componentId, method)` with no class/name fallback locator.
- `LivewireRuntimeBinding::forComponent()` fixes `driver=livewire` and `lifecycle=component`.
- deterministic serialization matches the existing Livewire RuntimeBinding fixture shape.
- no `livewire/livewire` dependency and no execution/discovery/lifecycle behavior.

**Verification:**

```text
TDD RED: 66041e403e423b010dc28efd84b5761fd37b2772
Implementation: 28364e137b0b052eeaa0ea878739963e387ba7ed + 1e572894e8f0338465fa58593130d01d736fa68b
PHP: 219 tests / 569 assertions
Contract: 52 fixture manifest entries + 12 conformance scenarios
Browser: typecheck + 3 tests
CI: all matrix jobs green
```

**Acceptance:**
- component instance identity and callable mapping remain outside `ActionDefinition`;
- exact Action Definition `id + version`;
- explicit binding identity;
- no silent target/version fallback;
- no T-202/T-203/T-304 implementation mixed in.

### T-202 — Implement explicit Livewire action exposure API — TODO

**Goal:** Mounted components explicitly expose actions/bindings.

**Acceptance:** no reflection-based “expose all public methods” path.

**Do not begin until T-201 external review completes.**

### T-203 — Implement Livewire binding lifecycle producer — TODO

**Goal:** Issue mounted binding descriptors and invalidate them on lifecycle/navigation replacement.

**Acceptance:** stale/replaced component bindings fail closed; no silent retargeting.

### T-204 — End-to-end Prep List through shared ActionBus — TODO

**Goal:** Human Livewire UI and agent binding execute the same application action.

**Acceptance:** business logic exists once and tests prove equivalent state transition.

---

## M3 — Browser Runtime / WebMCP — TODO

### T-301 — DriverRegistry — TODO

Register drivers by explicit name; unknown drivers fail closed.

### T-302 — WebMCP semantic projection — TODO

Project protocol-neutral semantics behind the browser adapter:

- `effect == read` → read-only hint;
- `outputContentTrust == contains_untrusted_content` → untrusted-content hint;
- `risk == consequential` → consequential hint.

`outputSensitivity` remains independent and belongs to server-side output policy/redaction.

### T-303 — Async registration lifecycle — TODO

Register current tools through the browser API, handle errors, and clean up with lifecycle/AbortController semantics.

### T-304 — Livewire browser driver — TODO

Execute Livewire RuntimeBindings; stale/unknown bindings return explicit failures with no guessing.

### T-305 — Cancellation propagation — TODO

Propagate cancellation as far as supported without claiming transactional rollback.

---

## M4 — Production Trust Controls — TODO

### T-401 — Confirmation challenge/receipt — TODO

Opaque runtime-issued, scoped, expiring confirmation receipts. Caller `confirmed=true` never grants authority.

### T-402 — Idempotency store — TODO

Server-side deduplication for required/recommended keys.

### T-403 — Output policy/redaction — TODO

Use `outputSensitivity` for redaction and preserve `outputContentTrust` for downstream untrusted-content handling.

### T-404 — Structured audit events — TODO

Record safe action identity/context references/outcome/correlation evidence without storing secrets by default.

---

## M5 — Filament Vertical — TODO

- T-501 — Record context binding.
- T-502 — Current-selection trusted context.
- T-503 — Active-filter context.
- T-504 — Confirmation bridge.
- T-505 — Multi-tenant order operations demo.

M5 must prove selection/tenant authority cannot be forged through action input.

## M6 — HTMX Portability Proof — TODO

- T-601 — Explicit HTMX binding descriptor.
- T-602 — HTMX browser driver.
- T-603 — Non-Laravel HTMX fixture app.
- T-604 — Shared conformance against Livewire + HTMX.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner.
- T-702 — Adapter author guide.
- T-703 — Laravel MCP projection using a maintained MCP implementation.
- T-704 — Optional OpenAPI importer as a secondary adapter.

## Deferred until evidence exists

- Rails/Hotwire runtime package.
- Phoenix LiveView runtime package.
- Blazor/Vaadin runtime packages.
- Public registry/discovery service.
- Browser automation/reverse-engineered tool generation.
