# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

### T-905 — Registry-installed Docker browser pilot (Local acceptance complete; PR integration pending)

- Scope: A standalone local Laravel application using both published exact
  `0.1.0-alpha.1` packages, browser UI and isolated Docker orchestration. No core
  contract changes, new package publication or production qualification.
- Acceptance: Localhost port serves a usable pilot; registry dependencies have
  no monorepo path coupling; trusted authentication/tenant, confirmation and
  idempotency paths have meaningful negative checks; the agent executes the
  native in-app browser flow. Manual browser instructions are optional support.
  General native-agent qualification remains separate from this pilot evidence.
- Owner-directed extension: Agent-led simulated order payment with a separate
  3D code page, wrong/correct code, retry, expiry and attempt-limit checks. Code
  validation issues a real runtime confirmation receipt; caller flags/code do
  not authorize tool execution. No bank, card data or real payment integration.
- Verification: Pilot acceptance checks, PHP syntax, browser build, HTTP smoke,
  `python scripts/validate.py`, full diff review. Keep only the pilot resources
  needed for further pilot experiments and document their shutdown.
- Current evidence: 12 checkout and 19 preserved HTTP tests, 6 Node DOM-wiring
  checks and required validation pass. Native Codex in-app WebMCP proves wrong
  then correct code on a separate page, completion/replay and one persisted
  effect; refund/changed-selection checks also pass. Test-first missing routes
  and review-found expiry race were RED before fixes. Independent static review
  has no remaining blocker. Updated-head PR CI/review is pending; owner manual
  testing is optional. [Acceptance evidence](docs/reviews/t905-checkout-acceptance.md),
  [run/test guide](examples/alpha-pilot/README.md).

## Done

| Milestone | Tasks |
| --- | --- |
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





