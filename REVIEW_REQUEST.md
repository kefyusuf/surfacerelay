# External Review Request — T-504 Filament Confirmation Bridge

## Current status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-504 — Confirmation bridge`
- **Feature branch:** `feat/filament-confirmation-bridge`
- **Base / merge-base:** `main@66f1d5db7e7902b6d7f09306be021119a6d96086`
- **Implementation verification head:** `e7954abe7a696c7a05ed32a6995fb3b7ae98400c`
- **Implementation verification CI:** `34561470349` — **7/7 green**
- **PHP:** **577 tests / 3011 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4 service coverage
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Filament / Livewire:** **5.8.1 / 4.4.4**
- **Decision:** `D-051` — **ACCEPTED**
- **Review state:** **IMPLEMENTATION COMPLETE / EXTERNAL REVIEW PENDING**
- **Merge state:** **NOT AUTHORIZED; separate explicit gate required**
- **Next task:** `T-505 — Multi-tenant order operations demo` — **NOT STARTED**

## Summary

T-504 implements a narrow human-facing Filament bridge for the T-401 confirmation state machine.

A real typed `confirmation_required` halt may be presented as a Filament modal on an explicitly opted-in page. Approve mutates only the exact locked T-401 challenge from pending to approved. It does **not** execute, redispatch, or reconstruct the business action. The requesting caller must retry through the existing `FilamentActionGateway → ActionBus` path with the original opaque challenge token as `confirmationReceipt`; trusted context and invocation intent are freshly resolved before receipt consumption.

```text
ActionBus
   │
   └── confirmation_required + typed ConfirmationChallenge
                 │
                 ▼
      FilamentConfirmationBridge
                 │
                 ▼
      opted-in Filament Page
                 │
                 ▼
      Livewire #[Locked] state
                 │
                 ▼
          [Cancel] [Approve]
                 │
        Approve only changes
        pending → approved
                 │
                 ▼
 caller explicitly retries normal action
                 │
                 ▼
 fresh scope resolution + receipt consumption
                 │
                 ▼
       existing business execution path
```

## Production files for review

Only four production files are in the T-504 implementation surface:

```text
packages/laravel/src/Filament/Confirmation/FilamentConfirmationBridge.php
packages/laravel/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php
packages/laravel/src/Filament/Confirmation/InvalidFilamentConfirmationBridge.php
packages/laravel/src/Filament/Invocation/FilamentActionGateway.php
```

Production code under the following areas is intentionally unchanged:

```text
spec/0.1/**
packages/browser-runtime/src/**
packages/laravel/src/Confirmation/**
packages/laravel/src/Idempotency/**
packages/laravel/src/Livewire/**
```

## Review focus

Please review the following trust boundaries carefully:

1. **Typed challenge provenance** — the bridge must present only an exact Confirmation-stage `confirmation_required` halt carrying a real `ConfirmationChallenge`; no reconstruction from generic details or caller data.
2. **Challenge substitution resistance** — browser Action arguments, request/query/route values, invocation metadata, or mutable Livewire state must not choose which challenge gets approved.
3. **Locked state semantics** — challenge ID/summary/expiry are server-authored `#[Locked]` properties and direct browser mutation is rejected.
4. **No hidden execution path** — Approve must not call ActionBus, `FilamentActionGateway::dispatch()`, application mutation code, or a second executor callback.
5. **Explicit retry** — approval alone must execute zero business side effects; only the later normal caller retry may consume the receipt and execute.
6. **Fresh-scope enforcement** — record, current selection, applied active filters, actor, tenant, binding, surface, and validated-input drift must reject the old receipt without spending the exact-scope receipt.
7. **Idempotency interaction** — exact same key/intent post-approval retry executes once; completed lost-response retry replays without another side effect or another confirmation.
8. **Presentation concurrency** — same challenge is idempotent/remountable; different challenge or unrelated mounted Filament Action conflicts rather than being replaced.
9. **Failure behavior** — missing `ConfirmationService` and store failure are fail-closed; expired/non-pending approval is generic and must not become a token oracle.
10. **Secret hygiene** — raw bearer tokens, hashes, scope fingerprints, selected records, filters, business input, and trusted identities must not enter logs/audit/notifications/exceptions/events.
11. **Framework boundary** — no reflection/private Filament raw mounted-state access, no new RuntimeBinding driver, no eager Filament registration in `SurfaceRelayServiceProvider`.

## Acceptance evidence

The test suite covers:

- completed/non-confirmation outcome no-op;
- malformed confirmation halt fail-closed;
- explicit page opt-in requirement;
- exact typed challenge delegation;
- real Filament reserved Action lifecycle;
- `#[Locked]` browser tamper rejection;
- direct mount without server-authored state failing closed;
- same-challenge remount;
- different-challenge overwrite rejection;
- unrelated mounted action preservation;
- exact locked-token approval even when browser arguments contain a different valid challenge token;
- Cancel preserving core Pending state;
- missing service binding failure;
- expired/non-pending generic handling;
- confirmation-store failure propagation;
- optional gateway bridge backward compatibility;
- exact original `ActionPipelineOutcome` returned unchanged;
- first consequential call producing zero side effects + challenge;
- approval producing zero side effects;
- explicit exact-scope retry executing once;
- consumed receipt failing a fresh attempt;
- lost-response idempotency replay without duplicate side effect;
- current-record drift;
- current-selection drift;
- applied-filter drift, while deferred filter edits remain non-authoritative;
- actor/tenant/binding/surface/validated-input drift;
- production dependency/source boundary guards.

## TDD / verification evidence

```text
Design / D-051 checkpoint:     a3e60544308ea5f3072d5fb813e939bfe4924def
Plan checkpoint:               46426cbccc1258664ebe6e3a5177cb841582732e / 34533606536 — 7/7 green
Task 1 RED:                    2df61dfa8c079c9f97b9d90c4c7ec096374910ba / 34559167747
Task 1 GREEN:                  8370402eb8d752eae4c329523e584820c28341c3 / 34559287792 — 7/7 green
Task 2 RED:                    9563b6108066cdde9396b8bfd044e9c2669604a2 / 34559561986
Task 2 final GREEN:            6fbaba5e6215e723d921618072b44931c7ba0264 / 34560122329 — 7/7 green
Task 3 RED:                    1f0f75f316955e03c6aaf61a9ddb3f8c6b062fa2 / 34560370550
Task 3 GREEN:                  f1bea851269cbae932b792d26c2f7731b303aebc / 34560453025 — 7/7 green
Task 4 RED:                    f9822e4e725eb0997633557383c1902adae3c8c5 / 34560647891
Task 4 GREEN:                  babc80bb30426748765fc2dc79c095ab5ae4967b / 34560733159 — 7/7 green
E2E initial proof:             acd4e7fb5129696a6e863e8d1a276ec8a4cfe9ca / 34561151879 — one harness-only selection-cache failure
E2E GREEN:                     f83db1a0e54760c2b4bfb97992bdfb93b7610c90 / 34561337612 — 7/7 green
Boundary / review-prep:        e7954abe7a696c7a05ed32a6995fb3b7ae98400c / 34561470349 — 7/7 green
PHP:                           577 tests / 3011 assertions
Browser:                       TypeScript typecheck + 103/103 Vitest
Contract:                      python scripts/validate.py green
```

The initial E2E selection-drift failure was isolated to the direct-object test harness. Filament caches selected records within one Livewire request; the test reused one PHP Page instance across synthetic requests. A fixture-only cache reset now models a fresh Livewire request boundary. No production T-502 or T-504 resolver change was required.

## Known non-goals / limitations

- no automatic agent/caller resume after approval;
- no generic approval endpoint/tool/ActionDefinition;
- no proof-of-human, WebAuthn, step-up authentication, `approvedBy`, delegated/supervisor approval, or cross-user approval contract;
- no durable standalone approval-decision audit subsystem;
- no modal persistence/recovery across arbitrary full navigation, browser refresh, component replacement, or multiple tabs;
- no T-505 order-operations demo work in this branch;
- two pre-existing moderate npm advisories remain in browser dependency maintenance scope; browser source/lockfile are not modified by T-504.

## Requested review outcome

Please classify any findings as correctness, security/trust-boundary, compatibility, test adequacy, or documentation/tracking. A successful review does **not** authorize merge. Merge remains a separate explicit gate after review findings are resolved and the exact final feature head is revalidated.