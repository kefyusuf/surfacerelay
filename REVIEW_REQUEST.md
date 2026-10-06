# Registry-installed Agent Checkout Pilot Review

Branch: `feat/t-905-docker-browser-pilot`. Task: T-905.
[Draft PR #41](https://github.com/kefyusuf/surfacerelay/pull/41).

The loopback Docker application installs exact published Laravel/browser-runtime
alpha packages. It exposes tenant-scoped simulated refund and order payment
through an app-owned HTTP driver. A separate simulated 3D page validates fixture
codes, stores an encrypted runtime confirmation receipt server-side, then lets
the native agent tool complete and replay one payment effect per checkout flow.
No core/public contract changes, real payment provider or package publication.

Review discovery/invocation authorization, exact binding/session/tenant scope,
code attempt limits, flow and receipt expiry, receipt confidentiality, two-worker
replay and the conditional stale-expiry observer fix. Caller code/receipt/flags
cannot authorize payment. Verification alone creates no effect; failed/expired
flows cannot complete. Canonical results remain separate from app-owned status.

Verification: Docker 12 checkout and 19 preserved HTTP tests, 6 Node DOM-wiring
tests, PHP syntax, build, strict Composer and canonical/22 HTMX fixtures pass.
Missing-route tests and the expiry race were observed RED before fixes.
Independent static review has no remaining blocker. Actual Codex in-app native
WebMCP proves wrong/correct code on the separate page, completion and replay;
SQLite verifies one effect. See [executed evidence](docs/reviews/t905-checkout-acceptance.md).

Node tests use deterministic DOM/HTTP stubs; native evidence covers this pilot,
not general certification or real bank 3DS. Single HTTP/native timing samples
measure different layers and provide no performance guarantee. Updated-head CI
and integration review remain pending; owner manual testing is optional.
The `surfacerelay-t905-pilot` stack stays running for further experiments.
No new image/volume/worktree; existing Docker resources and user locks preserved.
