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

## M6 — HTMX Portability Proof — IN PROGRESS

### T-601 — Explicit HTMX binding descriptor — DONE / SELF-REVIEWED / READY FOR EXTERNAL REVIEW

**Outcome:** Browser-runtime now has a pure driver-owned HTMX RuntimeBinding target descriptor that demonstrates the frozen generic RuntimeBinding envelope can carry a materially different binding target without adding HTMX execution, Laravel coupling, or new wire-contract fields.

**Accepted boundaries:**

- `driver=htmx` and `lifecycle=page` are mandatory at the consumer parser boundary;
- target fields are exactly `sourceId`, `method`, `path`, `inputNames`, and `requiredInputNames`;
- `sourceId` is one opaque rendered-source identity, never record/tenant/authorization identity;
- supported methods are exactly `GET`, `POST`, `PUT`, `PATCH`, and `DELETE`;
- path is a bounded same-origin absolute-path reference: no scheme/authority, `//`, fragment, backslash, ASCII controls, relative form, or value longer than 2048 characters;
- input mapping is named, sorted deterministically, and derived only from the exact closed top-level ActionDefinition object schema;
- `additionalProperties` must be exactly `false`; top-level open/composed/reference keywords that can make the finite key set ambiguous fail with `input_schema_unsupported`;
- `required` must be a unique non-empty string subset of exact properties;
- nested values remain under their top-level names; no dotted/bracket flattening convention is invented;
- consumer parsing validates arbitrary RuntimeBinding JSON independently of producer construction;
- producer construction reuses the strict consumer validation path so primitive target rules cannot drift;
- parsed/produced targets and mapping arrays are defensive frozen snapshots and do not retain caller/schema arrays by reference;
- descriptor data carries no actor, tenant, roles, current record, current selection, browser-session, confirmation, idempotency, or authorization authority;
- source guard proves no `htmx.org`, `htmx.ajax()`, `window`, `document`, `fetch`, or Laravel runtime coupling in the descriptor module;
- no HTMX dependency, browser driver, fixture app, `packages/laravel/src/**`, or `spec/0.1/**` change is part of T-601;
- D-053 is **ACCEPTED for descriptor semantics only**;
- D-020 remains **PROPOSED** until T-604 shared Livewire/HTMX conformance.

**Design:** `docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md`

**Plan:** `docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md`

**TDD / verification evidence:**

```text
Design checkpoint:               cca98af90323819789a56d28a2174b9b7ea25059 / 34625130732 — 7/7 green
Implementation plan:             1e47c6bb4121bcc1fc43e69c73c213d17092b5c1 / 34629136719 — 7/7 green
Parser RED:                      e0fceb6d7cf277dd1a1693daefc9b593d1a5d6a6 / 34653908938 — expected browser failure
Parser shell GREEN:              cf8436710a21dbb5888d62cd26197e7e7fc3ac62 / 34653972487 — green
Primitive validation RED:        f9da2714d7be4890ea4cd49623352224d73415de / 34654093046 — expected browser failure
Primitive validation GREEN:      b1845aab1ed82b32a14f738be08ef668f9d45342 / 34654144660 — 7/7 green
List/freeze RED:                 cbb410aa926c464d4011caccc607215e1f9422ca / 34654294846 — expected browser failure
List/freeze GREEN:               4e640112d5b9d9129f4ea07feddba538384699a9 / 34654371743 — green
Mapping RED:                     07a86db5756c0d75e7e6b0b854b8d24765a5fca1 / 34654482062 — expected 2 missing-builder failures
Mapping valid-path GREEN:        92b392b5304c456465f9fa8ac68e0f2dad9e42a3 / 34654613401 — browser/typecheck green
Schema fail-closed RED:          2df42bee8c91dbb609f313a4720408045b8d5f8c / 34654717686 — expected browser failure
Schema fail-closed GREEN:        831496a119535c31e0d76890b59b8f7ce0fb508c / 34654782553 — browser/typecheck green
Immutability checkpoint:         757835dee45e44724feba1972dcacdce43c82228 / 34654885713 — 7/7 green
Type contract checkpoint:        acb82ef246f09cc427da53e20facb567b5093694 / 34654896859 — browser/typecheck green
Implementation/scope head:       b06204e2c7acf6be01df3fec5085e55ddd650329 / 34654979136 — 7/7 green
Browser:                         TypeScript typecheck + 172/172 Vitest tests
HTMX descriptor focused tests:  69/69
Contract:                        python scripts/validate.py green
PHP / lint / Composer matrix:    green
```

**Self-review:** all 20 T-601 acceptance criteria are covered by executable tests or exact diff proof. The final implementation diff is limited to the descriptor module plus its focused runtime/typecheck tests and design/tracking documentation. No T-602/T-603/T-604 implementation is present.

**Known limitation:** T-601 defines descriptor construction/validation only. It does not resolve DOM sources, revalidate live `hx-*` method/path state, call `htmx.ajax()`, define HTMX cancellation semantics, or prove non-Laravel/shared conformance. Those remain T-602–T-604.

- T-602 — HTMX browser driver — TODO / NOT STARTED.
- T-603 — Non-Laravel HTMX fixture app — TODO.
- T-604 — Shared conformance against Livewire + HTMX — TODO.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-601 is implementation-complete and self-reviewed on the feature branch. **Stop at the external-review gate.** Do not create a pull request, merge, delete the feature branch, promote D-020, or begin T-602 without explicit authorization.