# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

T-805 source isolation and T-809 receipt-address concurrency corrections are verified. Remaining gate:
external [integration PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) review
and finding disposition; no next feature is selected. See [STATUS.md](STATUS.md#owner-actions).

## Done

| Milestone | Tasks |
| --- | --- |
| M0 Contract foundation | schemas, fixtures, validator |
| M1 / M1.1 Laravel kernel + hardening | ActionBus, policies, tenancy |
| M2 Livewire binding | binding producer, exposure |
| M3 Browser runtime / WebMCP | registry, projection, registration lifecycle |
| M4 Trust controls | confirmation receipts, idempotency, redaction, audit |
| M5 Filament vertical | record, selection, filters, confirmation bridge |
| M6 HTMX portability | T-601…T-604 |
| M7 Conformance / bridges | T-701 runner, T-702 adapter guide, T-703 Laravel MCP, T-704 OpenAPI importer |
| M8 Release readiness | T-801 artifact contract, T-802 Laravel artifact, T-803 browser artifact |
| M8 readiness | T-805 integrated verification and exact archived-source correction; tests and Docker consumer proofs pass; integration review pending |
| M8 docs | T-804 consumer guides, CHANGELOG, SECURITY, versioning, release checklist, README |
| T-809 review correction | Sorted challenge/receipt locks; three negative regressions; Docker Laravel 611 tests (2 skipped), canonical validation and independent real-store reproduction pass |
| Post-M8 WebMCP | native `document.modelContext` proof; T-806 HTMX request-failure reporting (D-074 proposed); T-807a live Filament panel proof + selection sync (D-075 proposed); T-807b approved retry on the agent path (D-076 proposed); T-809 distinct confirmation receipt (D-077 proposed); T-808 HTMX business output via `HX-Trigger` (D-078 proposed) |





