# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

### T-903 — Publication eligibility preparation — NEXT

Scope and gates: follow [the alpha plan](docs/releases/0.1.0-alpha.1.md), review
applicable decisions/migration notes, security intake and registry authority,
then prepare reviewed publication metadata/tooling and dry-run evidence.
Settings, formal decision promotion and T-904 publication require their explicit
owner authorization. Do not begin automatically after T-902.

## Done

| Milestone | Tasks |
| --- | --- |
| Alpha preparation | T-901 scope/candidates verified; T-902 installed Laravel alpha: 11 HTTP tests, Gate mutation, two-process receipt/key races, Python/canonical/guardrails pass; [evidence](docs/reviews/alpha-installed-application.md); publication NO-GO |
| M0 Contract foundation | schemas, fixtures, validator |
| M1 / M1.1 Laravel kernel + hardening | ActionBus, policies, tenancy |
| M2 Livewire binding | binding producer, exposure |
| M3 Browser runtime / WebMCP | registry, projection, registration lifecycle |
| M4 Trust controls | confirmation receipts, idempotency, redaction, audit |
| M5 Filament vertical | record, selection, filters, confirmation bridge |
| M6 HTMX portability | T-601…T-604 |
| M7 Conformance / bridges | T-701 runner, T-702 adapter guide, T-703 Laravel MCP, T-704 OpenAPI importer |
| M8 Release readiness | T-801 artifact contract, T-802 Laravel artifact, T-803 browser artifact |
| M8 readiness | T-805 reviewed, PR #34 merged with owner approval; exact merged-main artifacts/both clean consumers, Docker Python 158 tests and main CI 20 checks pass; publication NO-GO |
| M8 docs | T-804 consumer guides, CHANGELOG, SECURITY, versioning, release checklist, README |
| T-809 review correction | Sorted challenge/receipt locks; three negative regressions; Docker Laravel 611 tests (2 skipped), canonical validation and independent real-store reproduction pass |
| T-807b review evidence | Receipt secrecy/protected-call controls and cross-session signed-snapshot replay preserve owner retry; storage mutation fails, restored Chrome + Docker 16 tests and scoped review pass; existing Laravel 612 tests (2 skipped) |
| T-807a review correction | Table-state guards and component-wide overlap exclusion prevent stale/overwritten selection; real RED before fixes, Chrome + Docker Filament 15 tests, independent guard matrices and canonical validation pass |
| T-808 review correction | Deferred confirmation remains unknown outcome; owner-approved value envelope isolates business control keys; RED first, Docker browser 398 tests/typecheck, 22 shared schema fixtures, real HTMX 14 tests and independent review pass |
| Post-M8 WebMCP | native `document.modelContext` proof; T-806 HTMX request-failure reporting (D-074 proposed); T-807a live Filament panel proof + selection sync (D-075 proposed); T-807b approved retry on the agent path (D-076 proposed); T-809 distinct confirmation receipt (D-077 proposed); T-808 HTMX business output via `HX-Trigger` (D-078 proposed) |





