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

## M2 — Livewire Binding — IMPLEMENTATION COMPLETE / REVIEW PENDING

### T-201 — Implement Livewire RuntimeBinding descriptor — DONE / REVIEWED

**Outcome:** generic immutable RuntimeBinding + typed Livewire component target; exact action identity; `driver=livewire`; `lifecycle=component`; RFC3339 contract parity; no execution/discovery/lifecycle producer.

**Verification:** PHP 227 tests / 577 assertions; contract 52 fixture entries + 12 conformance scenarios; browser 3 tests; full matrix green.

### T-202 — Implement explicit Livewire action exposure API — DONE / REVIEWED

**Outcome:** explicit method-level `#[ExposeAction]` allow-list, exact registry resolution, deterministic exposure ordering, fail-loud duplicate/visibility rules, no public-method auto-exposure.

**Verification:** PHP 242 tests / 667 assertions; full matrix green on reviewed/merged main checkpoint.

### T-203 — Implement Livewire binding lifecycle producer — DONE / REVIEWED

**Outcome:** trusted exact component identity, fresh opaque binding IDs, deterministic exposure → component-scoped RuntimeBinding production, no silent retargeting, and D-033 lifecycle separation.

**Verification:** PHP 256 tests / 709 assertions; full PHP 8.3/8.4 × Illuminate 12/13 matrix green on reviewed/merged main checkpoint.

### T-204 — End-to-end Prep List through shared ActionBus — DONE / REVIEW PENDING

**Goal:** Prove a normal human Livewire call and a binding-derived agent invocation use the same explicit component method, shared ActionBus, and single application mutation.

**Production implementation:**

- `ActionExecutor` protocol-neutral application execution port;
- `ActionExecutionStage` real canonical execution-stage adapter;
- no surface-specific executor registry or fallback;
- no agent-only endpoint, controller, transport, or business mutation path.

**Real integration harness:**

- Livewire `^4.4` and Testbench `^10|^11` are development-only dependencies;
- `livewire/livewire` remains absent from production `require`;
- library `composer.lock` removed so each supported Laravel/Testbench matrix cell resolves independently;
- CI explicitly pairs Illuminate 12 with Testbench 10 and Illuminate 13 with Testbench 11.

**Prep List reference proof:**

- real `Livewire\Component`;
- explicit `#[ExposeAction(id: 'prep_list.add_item', version: 1)]` on `addItem`;
- component obtains `PrepListActionGateway` through real Livewire `boot()` lifecycle DI;
- real `LaravelInputValidationStage`;
- real `AuthorizationStage`;
- real `ActionExecutionStage`;
- trusted BrowserSession provided only through `InvocationContext`;
- one `AddPrepListItem` business mutation service;
- binding-derived invocation calls exactly the T-203 target method through the real Livewire test harness;
- confirmation/idempotency/output-policy/audit placeholders are test-only and do not claim M4 completion.

**TDD / verification:**

```text
Execution RED:   868eb1f3dc89e47023af95217bd44279b7a80994
Execution GREEN: 7267a43d6ede657d52cffc0d8a96f047f6c885af
E2E RED:         75022ae6594dfcabfd33bec89825d51459d0b8fa
Prep fixture:    f1eca5290d4ddbbd4b36990feddf76e20cc76f1c
Testbench key:   e7e6a9809d647070aff78105285ac08da0b4a03b
PHP: 266 tests / 783 assertions
Contract: 52 fixture manifest entries + 12 conformance scenarios
Browser: typecheck + 3 tests
CI: PHP 8.3/8.4 × Illuminate 12/13 × Testbench 10/11 × Livewire 4.4, plus contract/lint/browser — all green
```

**Acceptance:**
- business mutation exists once;
- human and binding-derived paths converge at the same explicit component method;
- both traverse the same shared ActionBus/application execution path;
- invalid input halts before authorization/execution;
- caller input never manufactures BrowserSession authority;
- no T-301/T-304 browser runtime implementation mixed in;
- D-034 records the shared-path invariant without claiming that the M3 browser driver already exists.

---

## M3 — Browser Runtime / WebMCP — TODO

### T-301 — DriverRegistry — TODO
Register drivers by explicit name; unknown drivers fail closed.

### T-302 — WebMCP semantic projection — TODO
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
