# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

### T-807b — Approved retry on the agent path — NEEDS DECISION

T-807a proves agent call → `confirmation_required` in a real Filament panel. Closing
the loop (human approves in the Filament modal → agent's retry executes) is blocked by
design: `ListOrders::refundSelected(reason)` is initial-invocation-only and the opaque
receipt must never be a page-method argument (D-040/D-051).

- Needs decision: where the approved receipt lives between approval and retry.
  Candidate: the page holds it as `#[Locked]` server state bound to the exact
  challenge scope (actor, tenant, selection, filters, input hash); the retry consumes
  it from trusted page state, never from caller input. Also needs a persistent
  confirmation store across Livewire requests (`CacheConfirmationStore`).
- Acceptance: real-browser retry executes exactly once; drift in selection/filters/
  tenant/input requires a new confirmation; caller-supplied receipt or
  `confirmed: true` has no effect; receipt never reaches page JS or audit rows.

### T-808 — Business output for HTMX-backed Actions

After T-806 a successful HTMX tool call proves the server accepted the request but
returns no output (`itemId`), because D-054 forbids synthesizing output from HTML.

- Needs a decision: an app-owned, opt-in response convention. Candidate: the server
  emits `HX-Trigger: {"surfacerelay:result": <ActionResult data>}` on the business
  route and the driver returns that payload, validated against the Action's
  `outputSchema`; absent payload stays `undefined`.
- Acceptance: payload only from the exact issued request; schema-invalid payload
  fails closed; no output from HTML; human path unchanged.

### T-805 — Integrated release-readiness verification

One revision/version input, both candidate artifacts, full regression, clean-consumer
evidence, hashes, publication go/no-go handoff. Publication itself stays outside
(D-073) and needs an explicit owner go.

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
| M8 docs | T-804 consumer guides, CHANGELOG, SECURITY, versioning, release checklist, README |
| Post-M8 WebMCP | native `document.modelContext` proof; T-806 HTMX request-failure reporting (D-074 proposed); T-807a live Filament panel proof + selection sync (D-075 proposed) |

