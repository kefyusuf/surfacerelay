# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

No implementation task selected. The repository-side queue is intentionally
empty — new task selections come from three signal sources only:
1. the owner's first outreach send
   ([DRAFTS.md](docs/consumers/outreach/DRAFTS.md), live-verified liberu
   candidate) and any filed
   [consumer-feedback](.github/ISSUE_TEMPLATE/consumer-feedback.yml) reports
   (intake process: [REPORT-INTAKE.md](docs/consumers/outreach/REPORT-INTAKE.md));
2. monthly entries in the
   [WebMCP momentum watch](docs/plans/2026-10-28-webmcp-momentum-watch.md)
   (each signal has a pre-agreed pivot);
3. an owner decision on any standing item.

## Done

| Milestone | Tasks |
| --- | --- |
| WebMCP momentum watch | T-918 [docs/plans/2026-10-28-webmcp-momentum-watch.md](docs/plans/2026-10-28-webmcp-momentum-watch.md): six signals with pre-agreed pivots (unflagged stable ship, second browser, DevTools MCP graduation, API changes, two-quarter stall → MCP-first re-weight, competing adoption) and an append-only monthly log; delivered via [PR #62](https://github.com/kefyusuf/surfacerelay/pull/62) |
| Windows test-harness portability | T-919 restore the CWD before `TemporaryDirectory` cleanup in the conformance review regression test — Windows removed the still-CWD temp directory on every run (WinError 32); verified locally (module 2/2, full 14-module Python suite OK, validate green) and hosted; delivered via [PR #63](https://github.com/kefyusuf/surfacerelay/pull/63) |
| WebMCP momentum watch |
| Outreach intake readiness | T-917 [REPORT-INTAKE.md](docs/consumers/outreach/REPORT-INTAKE.md) (triage categories, evidence rules, metrics, loop-closure) plus the standalone [docs link checker](scripts/check_docs_links.py) — 255 links verified, six broken pointers fixed, check added to the send checklist; delivered via [PR #61](https://github.com/kefyusuf/surfacerelay/pull/61) |
| When-to-use decision guide | T-916 [when-to-use.md](docs/consumers/when-to-use.md): honest exclusion of stateless CRUD up front, decision table, niche signals, per-action cost; POSITIONING and the quickstart link to it; delivered via [PR #60](https://github.com/kefyusuf/surfacerelay/pull/60) |
| Send-ready contact drafts | T-915 [DRAFTS.md](docs/consumers/outreach/DRAFTS.md) (two personalized drafts + generic template), append-only [contact ledger](docs/consumers/outreach/CONTACT-LEDGER.md), live-verification stamps on the candidate research (liberu pushed 2026-10-27 on Filament ^5.11 — exact stack match); delivered via [PR #59](https://github.com/kefyusuf/surfacerelay/pull/59) |
| External consumer outreach kit | T-914 [OUTREACH.md](docs/consumers/outreach/OUTREACH.md), 14-field [feedback form](docs/consumers/outreach/FEEDBACK-FORM.md), GitHub `consumer-feedback` issue template and dated [candidate research](docs/consumers/outreach/TARGETS.md); delivered via [PR #58](https://github.com/kefyusuf/surfacerelay/pull/58) |
| Verified minimal quickstart | T-913 `docs/consumers/getting-started.md` plus the verbatim [`examples/minimal-quickstart`](examples/minimal-quickstart/) overlay; fresh registry consumer CI: `PASS_T913_QUICKSTART_READ_ONLY` 5/5 and `PASS_T913_GLUE_NODE_ONLY` 3/3; delivered via [PR #57](https://github.com/kefyusuf/surfacerelay/pull/57) |
| Real Chrome native proof | A2-08 `document.modelContext` discovery/invocation on the A2-02 read-only consumer: local native receipts (discovery, invocation + audit delta, forged/missing fail-closed, server-owned denial, unmount, re-registration, guest no-tools) and hosted wiring receipt `PASS_A2_08_PAGE_WIRING_ONLY` with A2-02a/B1/B2 regressions; delivered via [PR #56](https://github.com/kefyusuf/surfacerelay/pull/56) |
| Filament notification drift repair | T-912 confirmation approval test registers the Filament support/notifications providers with array stores, restoring `assertNotified` under Filament >= 5.10.2's scoped `NotificationMailbox`; no production or constraint change; delivered via [PR #55](https://github.com/kefyusuf/surfacerelay/pull/55) |
| Independent A2 consumer gate | A2-01 registry-installed consumer gate ([PR #51](https://github.com/kefyusuf/surfacerelay/pull/51)); A2-02a server read-only dispatch (12 hosted Laravel tests) and A2-02b B1 mounted binding production (2 hosted tests) pass; A2-02 B2 descriptor emission (3/3) and browser wiring (15/15) receipts pass with A2-02a/B1 regressions, delivered via [PR #54](https://github.com/kefyusuf/surfacerelay/pull/54); [A2-02 gate](scripts/acceptance/a2-alpha3/A2-02-INTEGRATION-GATE.md), [B2 pilot](scripts/acceptance/a2-02-browser-pilot/README.md) |
| Coordinated alpha.3 publication | T-911 `0.1.0-alpha.3` published on npm/Packagist; immutable source/mirror/tags, 205 installed files, actual registry consumers, 23 HTTP/seven fault and nine native/SQL controls verified; [receipt](docs/reviews/alpha-0.1.0-alpha.3-publication.json) |
| Reusable Filament selection driver | T-910 accepted under D-075; Docker 508 browser/16 live browser/30 HTTP controls, clean artifacts, native SQL/replay and independent reviews pass; [acceptance](docs/reviews/t910-filament-acceptance.md); delivery/hosted checks: [PR #47](https://github.com/kefyusuf/surfacerelay/pull/47) |
| Coordinated alpha.2 publication | T-909 `0.1.0-alpha.2` published on npm/Packagist; exact source/mirror/tags/199 manifest files, registry consumers and native/SQL response-loss/replay controls verified; [receipt](docs/reviews/alpha-0.1.0-alpha.2-publication.json) |
| Native WebMCP error behavior | T-908 accepted under D-079; Docker/native/SQL checks and independent review pass; [PR #44](https://github.com/kefyusuf/surfacerelay/pull/44) merged and main CI passed; [evidence](docs/reviews/t908-native-acceptance.md) |
| Registry-installed Filament consumer | T-907 accepted through owner-authorized Chrome native tools and SQL proof; 23 HTTP checks, 3 generator tests, canonical validation and independent source/evidence review pass; scoped demo/stack/volumes/tabs removed; [evidence](docs/reviews/t907-filament-acceptance.md), [PR #43](https://github.com/kefyusuf/surfacerelay/pull/43) |
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





