# Project Status

Current state only. History lives in git, PRs, and
[`docs/archive/`](docs/archive/STATUS-through-2026-10-04.md).

## Snapshot — 2026-10-04

- **Main:** `e7d61ef` — M0–M7 and T-801…T-803 merged; main CI green.
- **Open PR stack (merge in order, owner review):** #23 T-804 plan → #24 T-804 docs →
  #25 native WebMCP proof → #26 tracking cleanup → #27 T-806 → #28 T-804 README →
  #29 T-807a live Filament proof → T-807b approved retry.
- **Release:** nothing published; no public version selected.

## What works today

- Protocol-neutral Action Definition / Runtime Binding schemas (`spec/0.1`, provisional).
- Laravel kernel: validation, authorization, tenancy, confirmation receipts,
  idempotency, output redaction, audit.
- Livewire and HTMX browser drivers sharing one conformance matrix
  (7 PASS / 1 NOT_APPLICABLE).
- Filament vertical: current record, current selection, active filters as trusted context.
- Optional Laravel MCP projection and OpenAPI importer packages.
- Release-candidate artifacts for `surfacerelay/laravel` and `@surfacerelay/browser-runtime`
  with clean-consumer checks.
- Native WebMCP in real Chromium (`document.modelContext`, flag-enabled):
  - HTMX fixture ([details](examples/htmx-prep-list/README.md#native-webmcp-proof));
    server rejections and unsent requests reach the agent as failures (T-806, D-074).
  - Real Filament 5 panel ([details](examples/filament-orders-live/README.md)): agent
    hold on the current record; agent refund over the human-visible selection
    (D-075) → Filament confirmation modal → human approves → agent retry refunds
    exactly once (T-807a/b, D-076).

## Known gaps

- HTMX tool calls return no business output on success (T-808).
- Challenge id doubles as the receipt and is visible to page script before approval;
  bounded by the scope fingerprint (T-809, low).
- Chromium 153 drops `consequentialHint` from `getTools()`; consequential safety must
  keep relying on server-issued confirmation receipts.

## Needs decision

- T-808: HTMX business-output convention.
- D-074, D-075, D-076: promote to ACCEPTED or revise.

## Publication blockers

- GitHub private vulnerability reporting is disabled (owner must enable it in repo settings).
- T-805 integrated release verification not done.
- Publication needs an explicit owner go (D-073).

## Decisions

- Accepted through D-068. Proposed: D-026, D-069…D-076
  ([register](docs/DECISION-REGISTER.md)).

## Next

T-809 → T-808 (after decision) → T-805. See [`TASKS.md`](TASKS.md).
