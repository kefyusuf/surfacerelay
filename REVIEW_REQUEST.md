# Review Request — T-809 distinct confirmation receipt

Branch `fix/t-809-distinct-receipt`, top of the open stack (#23 → … → #30).

## What changed

- `ConfirmationService::approveChallenge()` returns a fresh random receipt, never the
  challenge id. Optional receipt token generator (default random); malformed output or
  output equal to the challenge fails closed.
- `ConfirmationStore::approvePending($challengeHash, $receiptHash, ...)`: atomically moves
  the approvable pending record to the receipt hash. `CacheConfirmationStore` deletes the
  challenge before writing the receipt (failed write → nothing approvable), refuses an
  occupied receipt hash, and rejects receipt hash == challenge hash.
- `InteractsWithSurfaceRelayConfirmation` drops the `receipt === challengeId` assertion.
- Proposed **D-077**; THREAT-MODEL T11 updated.

## Review focus

- Breaking interface change for custom `ConfirmationStore` implementations (unpublished;
  CHANGELOG "Changed").
- Delete-then-write ordering in the cache store: fail closed vs. lost approval.
- Ten tests encoded `receipt === challengeId`; they now read the real receipt (trust-control
  tests pull it from the approving page's server state, D-076).

## Verification

- RED first: 12 errors / 2 failures in the new service and cache-store tests.
- `packages/laravel`: PHPUnit 608 OK (2 skipped).
- `examples/filament-orders-live`: 10/10 live (approval → exactly-once retry unchanged).
- `scripts/validate.py`.
