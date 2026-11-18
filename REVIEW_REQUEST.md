# Review handoff

Open PR: T-925 from `fix/t925-filament-live-drift` — repairs the red
`filament-live` browser proof on `main` (red since 2026-11-17, 9/16 Playwright
tests failing on the approval modal). Ships:

- [OrderDemoBrowserServiceProvider.php](packages/laravel/tests/Browser/OrderDemoBrowserServiceProvider.php):
  pins `filament.cache_store` to the persistent `file` store. Root cause:
  Filament >= 5.10.2 delivers `Notification::send()` through a scoped
  `NotificationMailbox` flushed to that cache store; the demo's `array`
  store is per request, so mailbox pulls never saw earlier flushes and
  approval toasts never rendered. Filament 5.9 (the last green resolution,
  2026-10-08) delivered toasts without the mailbox, so the drift was silent
  until a fresh `composer update` pulled >= 5.10.2.
- [filament-webmcp.spec.mjs](examples/filament-orders-live/tests/filament-webmcp.spec.mjs):
  the approval-toast assertion keeps its exact text but waits up to 15s —
  the first approval pays mailbox write + pull latency and the 5s default
  flaked one test that passes in isolation.
- [filament-live.yml](.github/workflows/filament-live.yml): monthly
  schedule so fresh-resolution dependency drift surfaces within a month
  instead of hiding until an unrelated path trigger.
- [docs/consumers/laravel.md](docs/consumers/laravel.md): documents the
  Filament >= 5.10.2 mailbox cache requirement for consumers of the
  confirmation bridge.
- `STATUS.md` / `TASKS.md` / `CHANGELOG.md` updated.

**Evidence chain (all reproduced locally in Docker, Filament 5.11.4 /
Livewire 4.4.8 / Laravel 13.43):** exact CI signature reproduced (9 failed /
7 passed) → server side proven healthy (approve response carried
`filamentNotificationBatch` + header) → session files proven to never hold
the mailbox address → array store identified as per-request in-memory →
manual `pullNotifications` with the real batch still empty → `file` store
pin → manual pull renders → full-suite 15/16 (one 5s-timeout flake that
passes in isolation) → assertion timeout widened → full-suite rerun 16/16.

No runtime, schema, fixture or release bytes change; no publish commands
added (guardrails green).
