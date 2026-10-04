# Project Status

Current state only. History lives in git, PRs, and
[`docs/archive/`](docs/archive/STATUS-through-2026-10-04.md).

## Snapshot — 2026-10-04

- **Main:** `e7d61ef` — M0–M7 and T-801…T-803 merged; main CI green.
- **Open PRs:** #23 (T-804 plan, ready) → #24 (T-804 docs, draft, stacked on #23).
- **Working branch:** `feat/webmcp-live-browser-proof`, stacked on #24's branch.
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
- **New:** native WebMCP proof — the HTMX fixture registers on Chromium's own
  `document.modelContext` and is invoked through `executeTool()`
  ([details](examples/htmx-prep-list/README.md#native-webmcp-proof)).

- **New:** HTMX driver reports server rejections and unsent requests as failures
  instead of success (T-806, D-074 proposed).

## Known gaps

- HTMX tool calls return no business output on success (T-808).
- No live WebMCP demo of the Filament/Livewire vertical yet (T-807).
- Chromium 153 drops `consequentialHint` from `getTools()`; consequential safety must
  keep relying on server-issued confirmation receipts.

## Publication blockers

- GitHub private vulnerability reporting is disabled (owner must enable it in repo settings).
- T-805 integrated release verification not done.
- Publication needs an explicit owner go (D-073).

## Decisions

- Accepted through D-068. Proposed: D-026, D-069…D-074
  ([register](docs/DECISION-REGISTER.md)).

## Next

T-807 → T-808 → T-805. See [`TASKS.md`](TASKS.md).

