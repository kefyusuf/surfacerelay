# T-905 agent-led checkout acceptance

Executed locally on 2026-10-06 with the exact public `0.1.0-alpha.1` Laravel and
browser-runtime packages, Docker PHP 8.4.26 and the Codex in-app browser.
No bank, SMS service, card data or real financial side effect is involved.

## Executed behavior

| Scenario | Evidence and outcome |
| --- | --- |
| Agent payment before verification | Native WebMCP call returns canonical `confirmation_required`; separate local 3D page is linked. |
| Separate page, wrong then correct code | In-app browser navigation opens the distinct 3D document. Wrong fixture code keeps it pending with two attempts remaining; correct fixture code returns `verified`. |
| Complete and replay | Agent returns to the order desk and fetches the new document-bound tool. Native calls complete order 101 and replay the same safe result; SQLite confirms exactly one effect for that flow. |
| Verification alone | HTTP acceptance proves correct-code verification creates no effect. |
| Three incorrect codes | HTTP 423/failed on third attempt; later correct code and invocation cannot unlock it; no effect. |
| Flow and receipt expiry | Controlled persisted flow expiry rejects. A real 61-second receipt-clock wait also rejects when flow expiry is deliberately extended, proving the package receipt clock independently. |
| Ownership and scope | Guest/another session/another tenant, changed record and expired binding cannot verify or complete using old authority. |
| Forged tool authority | Caller code/receipt/confirmed fields reject; denied actor cannot start payment. |
| Duplicate verification | No second receipt/effect; duplicate verification rejects. |
| Concurrent completion | Two distinct PHP workers both return the same successful result with one effect. Proof combines app locking, the package ledger and the application effect guard. |
| Stale expiry observer | Deterministic database-listener regression shows an old read cannot overwrite a concurrently completed transition. |
| Output and audit | No raw code, plaintext receipt or stored receipt ciphertext in canonical result/status/audit; package redaction removes executor-only data. |

An earlier native attempt outlived its receipt TTL, surfaced a generic bridge
invocation error and left the persisted flow expired with zero effects. The
fresh successful path above was then executed within its TTL. State observation
and the ledger, not a generic bridge exception, establish the payment outcome.

## Verification

- Test-first checkout RED: 9 tests failed on missing HTTP routes, before implementation.
- First GREEN: all 9 checkout tests passed.
- Review-found expiry race RED: `expired` instead of `completed`, before the fix.
- Final Docker verification: 12 checkout HTTP tests (62.553 seconds), 19 preserved
  pilot HTTP tests (69.286 seconds), 6 Node DOM-wiring tests, browser build, PHP
  syntax, strict Composer and canonical/22 HTMX fixtures passed.
- Independent static review found no remaining blocker after conditional expiry
  updates and persisted-row reload. Node tests use the real published runtime
  with a deterministic DOM/HTTP stub; native evidence comes from the actual
  in-app browser tool calls and page interaction described above.
- No public package contract, version, core runtime or existing driver changed.

## Timing sample and limits

One local direct HTTP sample measured start 11.49 ms, verify 8.56 ms, completion
9.26 ms and replay 7.97 ms, with one effect. One in-app native tool sample measured
completion 7679 ms and replay 9453 ms. The latter includes browser/tool-bridge
orchestration; it is not server execution time. These are single local samples,
not a performance benchmark or general latency/stability guarantee.

The flow is app-owned HTTP integration. Native interoperability is qualified
only for this local pilot and observed in-app browser. It does not qualify a
real payment provider, bank 3DS protocol, independent human verification,
production durability, arbitrary multi-tab session writes or global uniqueness
of payment for a commerce order. Owner manual testing is optional.
