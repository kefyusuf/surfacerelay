# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/livewire-browser-driver`
- **Reviewed main baseline before T-304:** `7ab8c2f5ef538affa4ac8f2f6224412412ca5765`
- **Reviewed T-304 checkpoint:** `20ac963871a2ffc7730c0cd42747c8a02de72fab`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Last completed/reviewed task:** `T-304 — Livewire browser driver`
- **Next task:** `T-305 — Cancellation propagation` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-304.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **283 tests / 815 assertions**.
- **Browser baseline:** TypeScript typecheck + **90/90 Vitest tests**.
- **Review CI:** workflow `34067907647` on exact checkpoint `20ac963871a2ffc7730c0cd42747c8a02de72fab`, all 7 jobs green.
- **Observed Livewire integration version:** `v4.4.3`.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with fail-closed invalid/unknown handling. D-035 ACCEPTED.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

Independent deterministic projection of the supported WebMCP hints. D-036 ACCEPTED.

### T-303 — Async registration lifecycle — DONE / REVIEWED

Deterministic versioned tool projection, whole-snapshot preflight, sequential browser registration, one AbortController-backed lease per generation, and partial-failure cleanup. D-037/D-038 ACCEPTED.

### T-304 — Livewire browser driver — DONE / REVIEWED

T-304 adds the first real framework-specific browser execution adapter.

Server side:

```text
exact ExposeAction + ActionDefinition.inputSchema
        ↓
LivewireMethodCallPlanBuilder
        ↓
inputOrder + requiredCount
        ↓
trusted LivewireBindingProducer
        ↓
RuntimeBinding.target
```

Browser side:

```text
WebMCP tool execution
        ↓
DriverRegistry.requireDriver("livewire")
        ↓
LivewireBrowserDriver
        ├── strict descriptor/expiry/input checks
        ├── exact Livewire.find(componentId)
        ├── exact $wire.$id verification
        └── documented $wire.$call(method, ...params)
```

Reviewed invariants:

1. Trusted producer emits a deterministic positional call plan derived from PHP reflection order, never JSON object/property order.
2. Action schema top-level property set exactly matches caller-visible PHP method parameters; schema `required` exactly matches PHP parameters without defaults.
3. Variadic, by-reference, union/intersection and method-level dependency parameters are rejected for browser-executable bindings.
4. Livewire `$wire` reserved names and public component-property collisions fail binding issuance.
5. `outputSchema` plus explicit `void`/`never` method return fails binding issuance; full PHP-return-type/JSON-Schema inference is intentionally not attempted.
6. Prep List returns the shared ActionBus semantic output instead of discarding it.
7. Browser target shape is exact: `componentId`, `method`, `inputOrder`, `requiredCount`; low-level legacy targets without a call plan are not browser-executable.
8. Explicit `expiresAt` is checked strictly before component resolution; malformed values fail closed and `expiresAt <= now` is `binding_expired`.
9. Caller input requires own required properties, rejects unknown keys, allows only trailing optional omission, and rejects positional holes.
10. Component resolution uses only exact `Livewire.find(componentId)`; missing/mismatching identity is `binding_stale` with no first/name/DOM/class/record/replacement fallback.
11. Invocation uses documented `$wire.$call(exactMethod, ...mappedParams)` exactly once and returns the raw resolved result.
12. Arbitrary Livewire/server/network/application rejections propagate unchanged; the driver does not mislabel them as stale.
13. `DriverExecutionContext.signal` is not passed as a fake `$call()` argument; real cancellation propagation remains T-305.
14. Ambient Livewire access is isolated behind the browser compatibility adapter; no private `fireAction`/request internals are imported.
15. T-303 integration proves WebMCP registration → exact DriverRegistry → LivewireBrowserDriver execution and proves old bindings do not retarget to replacement components.
16. Browser expiry validation mirrors the frozen PHP RuntimeBinding RFC3339 acceptance boundary.

## TDD / verification evidence

```text
Design:                 98fda16676f667e63611a4470955195e324cf528
Implementation plan:    76a641a226168053fa056329023e4bb3e7f00e2a

Server call-plan RED:   49ca658250f7e39ab2db4ae524c3e6b51a1ec436 / run 34066761247
Task-1 GREEN/fix:       9a1b1b302c32371429eda409e63f44a295324ea0 / run 34066974783
Producer RED:           2d289b1cd11997fdad7b01dbf720a2ffc00cf63f / run 34067044151
Producer GREEN:         5ce49b140866d584b1c286d543cba53aa6b8db2b / run 34067238627
Browser boundary RED:   af39243aabea1bbe66caf2af297d39d0cb53c647 / run 34067293183
Boundary GREEN:         1d01fb402087d28c1fa4e5d201af11e678fe5961 / run 34067343303
Driver RED:             7532c3e018b0751972e7dcc87406023979284f63 / run 34067410382
Driver implementation:  2fd575514ccd7a5f8f3faebbede0359da5128393
Type-narrowing fix:     01c7a19607a54c518641b5886d270780cf3409d4
Expiry fixture fix:     7d2783a3763a086558f19da39c618b001ec2b512
Integration proof:      54f82762d06abb7913eb84e6f66593fd6d346146 / run 34067710238
Review checkpoint:      20ac963871a2ffc7730c0cd42747c8a02de72fab
Review run:             34067907647 — all 7 jobs green
```

Final evidence:

```text
browser:  TypeScript typecheck + 90/90 Vitest tests
PHP:      283 tests / 815 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs green on exact review checkpoint
```

## Decisions

- D-039 ACCEPTED — exact `Livewire.find(componentId)` resolution, exact `$wire.$id` verification, no fallback/retarget.
- D-040 ACCEPTED — object input maps to positional Livewire calls only through a server-issued call plan; object/schema order is not authority.
- D-041 ACCEPTED — documented `Livewire.find()` + `$wire.$call()` only; public `$wire`/component-state collisions fail closed instead of using private APIs.
- D-026 remains PROPOSED as a complete generic error vocabulary. T-304 concretely emits proven `binding_stale` and `binding_expired` conditions but does not implement a universal revocation/not-found authority.

## Next task boundary

`T-305 — Cancellation propagation`

**Status: TODO / not started.** T-304 deliberately makes no claim that `$wire.$call()` accepts AbortSignal or that cancellation rolls back already-started server/application effects.
