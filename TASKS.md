# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

### T-902 — Installed-application alpha acceptance — NEXT

Scope: use the installed alpha candidates in a separate Laravel application with
real authentication/tenant resolution and actual policy/store wiring. Implement
the negative acceptance matrix in [the alpha plan](docs/releases/0.1.0-alpha.1.md).

Acceptance: actor/tenant switch, receipt expiry/replay and concurrent receipt/key
attempts fail closed without duplicate effects; output/audit and binding lifecycle
remain safe. Retain installed versions, policy wiring and exact-source evidence.

Verification: executable application-level positive/negative tests and real shared
store concurrency evidence; canonical validation and full diff review. Pin commands
before implementation. Do not substitute the existing pass-through consumer smoke.
T-903/T-904 remain future tasks; no publication or settings changes are authorized.

## Done

| Milestone | Tasks |
| --- | --- |
| Alpha preparation | T-901 owner-selected 0.1.0-alpha.1/two-package scope frozen; exact-source local artifacts/both consumers, Docker Python 158 tests and independent review pass; publication NO-GO |
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





