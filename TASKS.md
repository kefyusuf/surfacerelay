# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

> Historical per-task evidence from the pre-closure board is preserved in `docs/archive/TASKS-through-T505-preclosure.md`. Design specs, implementation plans, `STATUS.md`, and `REVIEW_REQUEST.md` remain the authoritative detailed evidence sources.

## M0 — Contract Foundation — DONE

- T-001 through T-005 — DONE.

## M1 — Laravel Kernel — DONE

- T-101 through T-110 — DONE.

## M1.1 — Hardening — DONE / REVIEWED

- Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

- T-201 through T-204 — DONE / REVIEWED.
- Reviewed checkpoint: `068347ac6d1bba645ab1c311daf918f87298b2e8`.

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

- T-301 — DriverRegistry — DONE / REVIEWED.
- T-302 — WebMCP semantic projection — DONE / REVIEWED.
- T-303 — Async registration lifecycle — DONE / REVIEWED / MERGED.
- T-304 — Livewire browser driver — DONE / REVIEWED / MERGED.
- T-305 — Cancellation propagation — DONE / REVIEWED / MERGED.

## M4 — Production Trust Controls — DONE / REVIEWED / MERGED

- T-401 — Confirmation challenge/receipt — DONE / REVIEWED.
- T-402 — Idempotency store — DONE / REVIEWED.
- T-403 — Output policy/redaction — DONE / REVIEWED.
- T-404 — Structured audit events — DONE / REVIEWED.

The M4 controls are exercised together by the merged T-505 executable Filament vertical.

## M5 — Filament Vertical — DONE / REVIEWED / MERGED / MAIN REVALIDATED

- T-501 — Record context binding — DONE / REVIEWED.
- T-502 — Current-selection trusted context — DONE / REVIEWED.
- T-503 — Active-filter context — DONE / REVIEWED / MERGED / MAIN REVALIDATED.
- T-504 — Confirmation bridge — DONE / REVIEWED / MERGED / MAIN REVALIDATED.
- T-505 — Multi-tenant order operations demo — DONE / REVIEWED / MERGED / MAIN REVALIDATED.

### T-505 final outcome

T-505 is the executable D-052 reference vertical proving that trusted Filament record, selection, tenant, and applied-filter context compose with the existing authorization, confirmation, idempotency, execution, output-policy, and structured-audit pipeline without introducing a second execution path.

Key accepted boundaries:

- caller input and metadata cannot manufacture tenant, record, selection, applied-filter, confirmation, binding, or target authority;
- Filament resource query scoping is defense-in-depth rather than mutation authorization;
- exact current record / selected records are independently re-authorized against trusted tenant authority before execution;
- mixed-tenant selection fails atomically;
- null authentication identifiers do not materialize trusted `authenticated_actor` context;
- confirmation remains approval-only and exact retry uses freshly resolved trusted state;
- `ListOrders::refundSelected(string $reason)` intentionally exposes business input only; `confirmationReceipt` and `idempotencyKey` remain invocation-envelope candidates owned by `FilamentActionGateway`;
- wrong-scope receipt attempts do not spend the exact valid receipt;
- confirmed required-key refunds execute once and exact completed retries replay without a second executor call;
- human and agent paths converge on the same exposed page methods and existing `driver=livewire` binding;
- durable audit excludes raw trusted/business/capability marker material;
- no T-505 implementation change occurred under `packages/laravel/src/**`, `packages/browser-runtime/src/**`, or `spec/0.1/**`.

Verification / review closure:

```text
Design spec:                    502b3916c124086089f2eb560a49f064cb00c65f
Implementation plan:            1f3444e8560fc20eb04797a6762a3c8cad663f4f
Initial review-prep head:       0a74a07266dca54a87b975ac9771746fc2946aab / 34616818751 — 7/7 green
Initial PR #9 CI:               34618387763 — 7/7 green
CodeRabbit review:              96cfc4ce-fc5c-47a7-a0e7-b0cf9c2c87d6 — 3 Major + 2 Minor
Review-hardening RED:           081b43627552bbcb1470f88a980e3d7b34bdaa86 / 34619817207
Review-hardening GREEN:         991a108c6f1226860f671029c509b4c9edb09a97 / 34620155445 — 7/7 green
Review-hardening PR CI:         34620158752 — 7/7 green
Final feature head:             85570928b5e20277d94d2a95ec30028779966112
Final feature push CI:          34620681591 — 7/7 green
Final PR CI:                    34620685078 — 7/7 green
Merge commit:                   7b95a82423012bf2824e55ba052ce78106f52e9a
Post-merge main CI:             34620944364 — 7/7 green
PHP:                            595 tests / 3164 assertions
Browser:                        TypeScript typecheck + 103/103 Vitest
Contract / lint / Composer:     green
Open review threads:            0
```

CodeRabbit findings were individually verified: the null-actor finding was reproduced and fixed TDD-first; two Major suggestions were disproven/withdrawn after executable evidence and plan-contract verification; both Minor tracking findings were fixed; all five threads are resolved/confirmed.

**M5 is closed.**

## M6 — HTMX Portability Proof — TODO

- T-601 — Explicit HTMX binding descriptor — TODO / NOT STARTED.
- T-602 — HTMX browser driver — TODO.
- T-603 — Non-Laravel HTMX fixture app — TODO.
- T-604 — Shared conformance against Livewire + HTMX — TODO.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

Do not start T-601 automatically. The next action is an explicit M6/T-601 scope/design gate when requested.