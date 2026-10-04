# Tasks

Open work only. Each task: scope, acceptance, verification. Completed tasks keep a
one-line entry; full history is in
[`docs/archive/TASKS-through-2026-10-04.md`](docs/archive/TASKS-through-2026-10-04.md).

## Open

### T-806 — HTMX tool calls report request failure and business result — NEXT

Found by the live WebMCP proof: `htmx.ajax()` resolves on HTTP error responses, so a
`422` from the server reaches the agent as a successful tool call, and a successful
call returns `undefined` instead of the Action's output.

- Scope: `HtmxBrowserDriver` / `GlobalHtmxBrowserRuntime` detect request failure
  (e.g. `htmx:afterRequest` `detail.successful`) and reject; decide how a business
  result (e.g. `itemId`) is returned without treating "HTML swapped" as success.
- Contract: a new failure code touches D-026 and the binding-failure fixtures; update
  schema/fixtures/conformance/decision together (AGENTS rule 13).
- Acceptance: the `test.fail()` in `examples/htmx-prep-list/tests/webmcp.spec.mjs`
  passes without the marker; negative tests for 4xx/5xx/network failure; no POST
  retry; human path unchanged.
- Verify: `packages/browser-runtime` `npm test` + `npm run typecheck`;
  `examples/htmx-prep-list` `npm test`; `python scripts/validate.py`.

### T-807 — Live WebMCP demo of the Filament vertical

The flagship story: a user selects three orders in a Filament table, an agent calls a
consequential refund tool through `document.modelContext`, receives a confirmation
challenge, and the human confirms in the Filament UI.

- Acceptance: Playwright run on flag-enabled Chromium covering register → call →
  challenge → human confirm → execute, plus caller-supplied `confirmed: true` and
  forged selection rejected; a short screen recording for the README.
- Depends on: Livewire driver result semantics reviewed the same way as T-806.

### T-804 — Release-facing documentation — Step 5 remaining

Steps 1–4 done (consumer guides, CHANGELOG, SECURITY, versioning, release checklist).
Step 5: README links/positioning and documentation command/claim audit.
Plan: [`docs/superpowers/plans/2026-10-02-release-facing-documentation-compatibility.md`](docs/superpowers/plans/2026-10-02-release-facing-documentation-compatibility.md).

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
