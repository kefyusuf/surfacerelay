# External Review Record

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Reviewed scope:** `T-304 — Livewire browser driver`
- **Implementation/integration head:** `54f82762d06abb7913eb84e6f66593fd6d346146`
- **Result:** **PENDING FINAL CHECKPOINT REVIEW**
- **M3 status:** IN PROGRESS
- **Next task:** T-305 has **not** started.

## Reviewed architecture candidate

```text
Laravel server issuance

ExposeAction + exact ActionDefinition
        ↓
LivewireMethodCallPlanBuilder
        ↓
LivewireBindingProducer
        ↓
RuntimeBinding.target
  componentId + method + inputOrder + requiredCount

Browser execution

WebMCP tool
        ↓
DriverRegistry("livewire")
        ↓
LivewireBrowserDriver
        ↓
strict target / expiry / input mapping
        ↓
Livewire.find(exact componentId)
        ↓
verify exact $wire.$id
        ↓
$wire.$call(exact method, ...params)
```

## Production scope under review

Laravel:

```text
packages/laravel/src/Livewire/Binding/LivewireMethodCallPlan.php
packages/laravel/src/Livewire/Binding/LivewireMethodCallPlanBuilder.php
packages/laravel/src/Livewire/Binding/LivewireWireReservedNames.php
packages/laravel/src/Livewire/Binding/LivewireBindingProducer.php
packages/laravel/src/Livewire/Binding/InvalidLivewireBindingProduction.php
packages/laravel/src/Livewire/LivewireBindingTarget.php
```

Browser:

```text
packages/browser-runtime/src/livewire-errors.ts
packages/browser-runtime/src/livewire-reserved-names.ts
packages/browser-runtime/src/livewire-browser-runtime.ts
packages/browser-runtime/src/livewire-browser-driver.ts
```

Reference fixture/example changes are limited to returning Prep List semantic output, proving the executable target shape, and updating the illustrative binding descriptor.

## Invariants to review

1. Action input object order is never Livewire invocation authority; parameter order comes only from the server-issued ReflectionMethod call plan.
2. Action schema property set equals the caller-visible PHP parameter set exactly; no Action field is silently dropped and no extra PHP argument is invented.
3. Schema required set equals PHP parameters without defaults; required arguments form a positional prefix.
4. Variadic, by-reference, union/intersection and method-level dependency signatures fail executable binding issuance.
5. `$wire` public/proxy reserved names and public component-state collisions fail closed rather than bypassing documented APIs.
6. Non-null Action output plus explicit PHP `void`/`never` return fails binding issuance; no speculative full PHP/JSON-Schema type inference exists.
7. Trusted producer emits `inputOrder` + `requiredCount`; low-level legacy/manual target construction may omit them but the browser driver then rejects that descriptor as non-executable.
8. Browser target must contain exactly `componentId`, `method`, `inputOrder`, and `requiredCount` with strict value constraints.
9. Explicit RuntimeBinding expiry is parsed strictly and enforced before component lookup; malformed expiry is invalid, `expiresAt <= now` is expired.
10. Input mapping requires required own-properties, rejects unknown keys and positional holes, permits only trailing optional omission, and forwards values unchanged.
11. Exact component resolution uses only documented `Livewire.find(componentId)`.
12. The returned `$wire.$id` must equal the bound component ID.
13. Missing or mismatching exact identity is `binding_stale`; no first/name/DOM/class/record/method/replacement lookup is attempted.
14. Invocation uses documented `$wire.$call(method, ...params)` exactly once; private `fireAction`/request internals are not imported.
15. Raw successful method result is returned unchanged.
16. Arbitrary Livewire/server/network/application rejection is propagated unchanged and is not guessed to be a stale binding.
17. T-303 integration proves WebMCP registration → exact DriverRegistry → LivewireBrowserDriver execution.
18. Replacement test proves an old exact component binding never invokes a newly mounted similar component.
19. `DriverExecutionContext.signal` is intentionally not appended to `$call()` arguments; T-305 owns actual cancellation propagation and rollback non-claims.
20. `spec/0.1` remains unchanged; all new invocation metadata is driver-owned RuntimeBinding target data.
21. D-039/D-040/D-041 record the exact lookup, server-issued call-plan, and documented-API-only boundaries.
22. D-026 remains proposed as a complete generic failure vocabulary; T-304 only emits conditions it can prove locally.

## TDD evidence

```text
Design:                 98fda16676f667e63611a4470955195e324cf528
Plan:                   76a641a226168053fa056329023e4bb3e7f00e2a

Server call-plan RED:   49ca658250f7e39ab2db4ae524c3e6b51a1ec436
RED workflow:           34066761247
Server GREEN/fix:       9a1b1b302c32371429eda409e63f44a295324ea0
GREEN workflow:         34066974783

Producer RED:           2d289b1cd11997fdad7b01dbf720a2ffc00cf63f
RED workflow:           34067044151
Producer GREEN:         5ce49b140866d584b1c286d543cba53aa6b8db2b
GREEN workflow:         34067238627

Browser boundary RED:   af39243aabea1bbe66caf2af297d39d0cb53c647
RED workflow:           34067293183
Boundary GREEN:         1d01fb402087d28c1fa4e5d201af11e678fe5961
GREEN workflow:         34067343303

Driver RED:             7532c3e018b0751972e7dcc87406023979284f63
RED workflow:           34067410382
Driver implementation:  2fd575514ccd7a5f8f3faebbede0359da5128393
Type-narrowing fix:     01c7a19607a54c518641b5886d270780cf3409d4
Expiry fixture fix:     7d2783a3763a086558f19da39c618b001ec2b512

WebMCP integration:     723b77281890aff6e12c5566c4aa62340ca2f0c5
Integration head:       54f82762d06abb7913eb84e6f66593fd6d346146
Integration workflow:   34067710238 — all 7 jobs success
```

Intermediate failures did not loosen behavior:

- the first driver implementation required an explicit TypeScript number guard for `requiredCount`;
- one offset-expiry test fixture was mathematically in the past after conversion to UTC; only the test timestamp was corrected.

## Current evidence

```text
browser:  TypeScript typecheck + 90/90 Vitest tests
PHP:      283 tests / 815 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs success on exact integration head
Livewire: observed v4.4.3 in matrix
```

## Decision status

- D-039 ACCEPTED — exact `Livewire.find(componentId)` + `$wire.$id`; no fallback/retarget.
- D-040 ACCEPTED — server-issued `inputOrder`/`requiredCount` is the only object→positional invocation authority.
- D-041 ACCEPTED — documented `Livewire.find()` + `$wire.$call()` only; proxy/state collisions fail closed instead of private-API bypass.
- D-026 remains PROPOSED as a complete generic binding failure vocabulary.

## Explicit non-claims

T-304 does **not** implement:

- revocation persistence/current-binding authority beyond exact browser component existence and expiry;
- automatic component/router/DOM lifecycle observation beyond T-303 lease ownership;
- actual AbortSignal propagation into Livewire requests;
- cancellation/rollback guarantees;
- confirmation receipts;
- idempotency persistence;
- output redaction policy;
- structured production audit;
- Filament/HTMX browser drivers.

## Review gate

Before marking T-304 reviewed or merging:

1. compare `main...feat/livewire-browser-driver` for scope leakage;
2. confirm no `spec/0.1` changes;
3. confirm no private Livewire JS imports or lookup fallbacks;
4. run fresh CI on the exact documentation/review checkpoint;
5. require all 7 jobs green;
6. stop before T-305 until T-304 review/merge is complete.
