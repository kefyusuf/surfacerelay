# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

### T-907 — Temporary registry-installed Filament acceptance (HTTP verified; native blocked)

- Scope: A real Filament panel in a temporary demo inside `.tmp/t907-filament-demo/`,
  using exact published alpha packages and the existing Filament trust pipeline.
- Acceptance: Agent-led native Codex in-app calls prove current record and multiple
  selected records, approval-only behavior, bounded idempotency, permission/tenant
  changes and stale page authority rejection. Include negative tests.
- Verification: Test-first real mounted HTTP checks, registry install/build,
  canonical validation, independent review and native browser/SQL effect evidence.
- Cleanup: Remove the temporary demo and task-owned Docker stack after testing;
  retain reproducible test assets and evidence, preserve T-905 and user lockfiles.
- Likely files: `scripts/acceptance/t907-filament/`, temporary demo, acceptance
  evidence and current tracking. No publication, merge or next task is selected.
- Current evidence: Fresh locked install, 20 signed HTTP checks, 3 generator safety
  checks and required validation pass; mixed-selection and superseded
  modal failures were observed RED and fixed in the consumer. Native Codex in-app
  initialization fails before browser control; acceptance remains incomplete.
- Cleanup verified: temporary demo/Compose file and owned container/network removed.

## Done

| Milestone | Tasks |
| --- | --- |
| Registry-installed Livewire consumer | T-906 real mounted consumer accepted; 15 signed HTTP checks, native agent/SQL one-effect proof, required validation and independent review pass; [evidence](docs/reviews/t906-livewire-acceptance.md), [PR #42](https://github.com/kefyusuf/surfacerelay/pull/42) |
| Registry-installed browser pilot | T-905 local Docker/native-agent refund and separate simulated 3D checkout accepted; 31 HTTP and 6 DOM-wiring tests, required validation and independent review pass; [evidence](docs/reviews/t905-checkout-acceptance.md), [PR #41](https://github.com/kefyusuf/surfacerelay/pull/41) |
| First alpha publication | T-904 `0.1.0-alpha.1` published on npm/Packagist; exact source/tag/mirror/hash provenance, 187 Python tests and local/tagged/registry consumers verified; [receipt](docs/reviews/alpha-0.1.0-alpha.1-publication.json) |
| Publication preparation | T-903 metadata/dry-runs, mirror provenance, private intake/decision dispositions and kefyusuf browser/CLI owner authority verified; owner approved interactive 2FA route, deferring unattended scoped-token/OIDC provisioning |
| Alpha preparation | T-901 scope/candidates and T-902 installed Laravel HTTP/authorization/shared-store races verified; [preparation evidence](docs/reviews/alpha-installed-application.md); final publication recorded under T-904 |
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





