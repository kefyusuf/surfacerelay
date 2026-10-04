# Project Status

Current state only. History lives in git, PRs, and
[`docs/archive/`](docs/archive/STATUS-through-2026-10-04.md).

## Snapshot — 2026-10-04

- **Main:** `e7d61ef` — M0–M7 and T-801…T-803 merged; main CI green.
- **Open PR:** #34 integrates the former stack #23…#33 against `main` (one review, one merge).
- **Open tasks:** none. Everything left is an owner action (below).
- **Release:** nothing published; no public version selected.

## What works today

- Protocol-neutral Action Definition / Runtime Binding schemas (`spec/0.1`, provisional).
- Laravel kernel: validation, authorization, tenancy, confirmation receipts,
  idempotency, output redaction, audit.
- Livewire and HTMX browser drivers sharing one conformance matrix
  (7 PASS / 1 NOT_APPLICABLE).
- Filament vertical: current record, current selection, active filters as trusted context.
- Optional Laravel MCP projection and OpenAPI importer packages.
- Release candidates for `surfacerelay/laravel` and `@surfacerelay/browser-runtime`, with
  integrated same-revision readiness verification and clean-consumer proofs
  ([record](docs/RELEASE-READINESS.md), pre-merge).
- Native WebMCP in real Chromium (`document.modelContext`, flag-enabled):
  - HTMX fixture ([details](examples/htmx-prep-list/README.md#native-webmcp-proof)):
    failures reach the agent as failures (T-806, D-074); declared business output
    reaches the agent (T-808, D-078).
  - Real Filament 5 panel ([details](examples/filament-orders-live/README.md)): agent
    hold on the current record; agent refund over the human-visible selection
    (D-075) → Filament confirmation modal → human approves → agent retry refunds
    exactly once (T-807a/b, D-076); receipts are distinct from challenge ids (T-809, D-077).

## Known gaps

- Chromium 153 drops `consequentialHint` from `getTools()`; consequential safety must
  keep relying on server-issued confirmation receipts.
- WebMCP evidence uses a flag-enabled Chromium with the page as caller, not a real agent.

## Owner actions

- Review and merge #34 (agent merges are blocked by policy).
- Promote D-074 … D-078 to ACCEPTED or revise them.
- Enable GitHub private vulnerability reporting (repo settings) — publication blocker.
- After merging: re-run release readiness on the merged `main` revision.
- Publication needs approved public version, registry namespace/credentials and an
  explicit go (D-073).

## Decisions

- Accepted through D-068. Proposed: D-026, D-069…D-078
  ([register](docs/DECISION-REGISTER.md)).
