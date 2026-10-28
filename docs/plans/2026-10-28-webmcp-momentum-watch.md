# WebMCP momentum watch (Faz 5)

**Purpose.** SurfaceRelay's browser projection makes one conditional bet: that
`document.modelContext` (WebMCP) becomes a real, widely available agent
surface. The adapter isolates that bet, but the *roadmap* should follow
evidence. This note defines the signals, the pre-agreed pivot per outcome,
and an append-only observation log. **Cadence: a ten-minute check on the
first day of each month; one dated log entry per check, even when nothing
changed.**

Baseline at creation (2026-10-28): `document.modelContext` requires Chrome
150+ launched with `--enable-features=WebMCP` (verified live in
[A2-08](../../scripts/acceptance/a2-08-native-chrome/README.md) on Chrome
155); the official [chrome-devtools-mcp](https://github.com/ChromeDevTools/chrome-devtools-mcp)
exposes it behind `--categoryExperimentalWebmcp`; Edge/other browsers: not
verified.

## Signals and pre-agreed pivots

| # | Signal | Source to check | Outcome → pivot |
| --- | --- | --- | --- |
| 1 | WebMCP available **without a flag** in Chrome stable | Release notes / `chrome://flags` on a current stable | **Accelerate:** re-run the A2-08 native proof unflagged, then push outreach wider (the install friction that risked the first consumer disappears) |
| 2 | A second Chromium-family browser (Edge, Brave) ships `document.modelContext` | Vendor release notes | **Broaden:** add the verified browser to the consumer docs; no code change expected (the adapter is feature-detected) |
| 3 | `chrome-devtools-mcp` graduates the WebMCP category from experimental (or changes its tool shapes) | Package releases / `--help` | **Adapt:** update the acceptance harness pin and re-verify; no product change unless tool semantics change |
| 4 | The WebMCP proposal changes the `document.modelContext` API surface (e.g. registration, lifecycle) | Spec repository / Chromium intents | **Assess:** the contract is protocol-neutral by design (ADR-0005) — check whether only the projection adapter or also the browser-runtime types need updating; record a decision if a public contract is touched |
| 5 | WebMCP momentum stalls (no spec movement, no unflagged ship, no second browser for **two consecutive quarters**) | This log | **Re-weight:** prioritize MCP-first projection through the maintained Laravel MCP bridge ([packages/laravel-mcp](../../packages/laravel-mcp/README.md), ADR-0006) and mark browser outreach as secondary |
| 6 | A competing Filament/Livewire WebMCP integration gains real adoption | [COMPETITIVE-LANDSCAPE.md](../COMPETITIVE-LANDSCAPE.md) row updates | **Positioning review:** update when-to-use and the landscape table with what they do differently; respond with evidence, not claims |

## Rules

- The log records observations, never predictions dressed as facts.
- A pivot is a task selection recorded in `STATUS.md`/`TASKS.md`, not an edit
  of this note's promises.
- Two consecutive quarters of "no change" entries are themselves a signal
  (see #5) — do not skip logging them.

## Observation log (append-only)

| Date | Signal checked | Observation | Action taken |
| --- | --- | --- | --- |
| 2026-10-28 | All (baseline) | Chrome 155 flag-gated; chrome-devtools-mcp 1.10.1 experimental category; harness pin recorded | Watch note created (T-918) |
