# T-910 — Reusable Filament Selection Driver

Status: Owner-approved design implemented; local acceptance passes under D-075.
Scope: one opt-in browser adapter in `@surfacerelay/browser-runtime`.
Baseline: alpha.2 is published; main CI passed and no PR is open at planning time.

## Problem and evidence

`examples/filament-orders-live/client.mjs` contains selection synchronization and
a component guard that consumers must currently copy. D-075 describes that
bounded example behavior; it does not authorize a package API.

The example validates exact component ownership, one owned table, a boolean
tracking flag and two Sets containing string record keys before three deferred
`$set` calls. Its guard covers selection and current-record calls until the exact
inner promise settles. Server selection resolution, tenant authorization,
confirmation and idempotency remain authoritative under D-049 and ADR 0007.

Extraction alone preserves problems: instance-local guards cannot coordinate two
wrappers; reference-only selection membership silently bypasses sync for unknown
bindings; mutable bindings can change targets after registration; synchronization
currently precedes Livewire driver validation. The T-907 generator rewrites a fixed
alpha.1 import list and copies source after a `// Filament` marker. T-908 reuses it.
Moving imports without migrating these fixtures could reference unavailable exports.

## Recommended decision

Provide a specialized `FilamentBrowserDriver` implementing the existing
`BindingDriver` interface. It owns binding membership, selection synchronization
and component exclusion. Reuse Livewire execution machinery through a **private
internal seam** after target/input/expiry/component/cancellation capability
validation and before dispatch. Keep the normal Livewire driver and its public
constructor behavior unchanged. Do not expose a generic public hook.

Approved public-interface shape; implementation acceptance remains pending:

```ts
const coordinator = new FilamentSelectionCoordinator();
const driver = new FilamentBrowserDriver(livewireRuntime, {
  tools: explicitlyExposedTools,
  selectionRuntime: new GlobalFilamentSelectionRuntime(),
  coordinator,
});
registry.register('livewire', driver);
```

No Filament runtime dependency, implicit exposure, core schema field, automatic
retry, queue, result envelope change or new package. Clock injection must preserve
the existing expiry test seam. The owner approved this scope before implementation.

### Membership and lifetime

Capture exact registered binding identities and owned descriptor snapshots at
construction. Derive selection requirements from trusted exposure definitions,
not invocation input or a caller boolean. Reject unknown objects, same-value
clones, conflicting duplicate registrations and descriptor mutations before writes.
Do not freeze or mutate application-owned objects. Specify comparison in tests,
including action identity, binding identity, driver, lifecycle, expiry, component,
method and ordered input metadata. No binding-ID authority fallback.

Require one explicitly shared coordinator for all helper instances in the same
Livewire environment. Store only transient component occupancy, never selections.
Two wrappers sharing it exclude same-component calls, including current-record
calls; different components remain independent. Separate coordinators provide no
shared guarantee. Examples must show this lifetime. Ordinary UI/Livewire calls
outside the helper remain outside the bounded guard.

### Execution order and failure boundary

1. Check membership/descriptors and reject an occupied component without releasing
   another call's guard.
2. Acquire the guard and run shared Livewire preflight. Invalid, expired,
   reserved-method, unmapped-input, pre-aborted, stale-component or unsupported
   cancellation-capability calls detectable during preflight cause zero deferred
   writes and no dispatch. Missing interception capability is such a preflight case;
   an exact-action capture failure after `$call` starts is not.
3. For selection-required bindings only, resolve the exact owned table and validate
   all state before writes. Snapshot both Sets and the flag; write the three
   existing deferred fields with `false`.
4. Recheck cancellation before dispatch if synchronous selection work changed it.
   Preserve exact-action cancellation interception and original input/context.
5. Preserve the value or rejection. Hold the guard until the actual execution
   promise settles, including after post-dispatch abort. Release on all pre-dispatch
   failures; never retry automatically. If `$call` starts but interception fails to
   capture the exact action, preserve the existing public rejection while treating
   dispatch as unknown. The private seam must retain the underlying call promise
   and hold occupancy until it settles, even if the public promise rejects earlier.

Deferred writes are not transactional. A throwing `$set` can leave partial local
state; that failed call must not dispatch. A later selection-required helper call
must fully revalidate and overwrite all three fields. Do not promise rollback or
unrelated UI protection.
Do not include record keys, inputs, receipts or raw runtime errors in new diagnostics.
Missing/ambiguous/foreign-owned tables fail closed. Current-record calls need no
table but participate in the shared guard.

## Alternatives

| Option | Assessment |
| --- | --- |
| Example decorator around arbitrary driver | Cannot guarantee preflight before writes; duplicated Livewire validators risk drift. |
| Wrap runtime `find` / wire `$call` | Loses exact binding and definition identity; component/method matching can merge exposure policies. |
| Specialized driver with private shared executor | Recommended: preserves exact policy and ordering without broadening the generic public driver API; requires cancellation regression coverage. |
| Keep application-owned glue | Compatible fallback if productization is declined; copying and coordination remain application responsibilities. |

## TDD sequence after approval

1. Add failing public-adapter tests for exact membership/mutation, zero-write
   preflight failures and cross-instance exclusion. Record actual RED evidence.
2. Extract private Livewire execution machinery with existing tests green; add the
   opt-in driver/runtime and prove synchronization/dispatch/cancellation ordering.
3. Add DOM/Alpine negatives and partial-write failures. Prove rejected contenders
   cannot release active guards; preserve direct and T-908 envelope behavior.
4. Migrate the live example. Keep historical registry recipes pinned to published
   versions with compatible glue. Add a separate local-candidate path for the new
   export and test generated module imports/builds, beyond path-safety assertions.
   Never import a future export from alpha.1/alpha.2. No publication in T-910.
5. Add adapter conformance/consumer fixtures, D-075 disposition and migration/export
   documentation. Core schemas remain unchanged; a new core-contract need stops
   this scope and requires a separate decision.
6. Run Docker example/clean local-artifact acceptance and Chrome native calls with
   SQL effect checks. Use a dedicated task branch and the authorized TDD/commit/PR
   workflow; preserve the user's untracked Composer locks.

Likely implementation files: browser driver/runtime/exports and tests; example
client/Playwright tests; affected T-907/T-908 generators and fixtures; artifact
consumer tests; browser consumer guide; D-075 and tracking files. No Laravel
authorization, confirmation storage, business-rule or dependency-version changes.

## Acceptance matrix

| Control | Required proof |
| --- | --- |
| Table validation | Missing/ambiguous/foreign-owned table and invalid flag/Set/key: zero writes/dispatch. |
| Membership | Exact object; clone/unknown/mutation/conflicting registration rejection. |
| Preflight | Malformed/expired/reserved/input/abort/stale/cancellation failures: zero writes/dispatch. |
| Exclusion | Same/different/selection-free bindings, two drivers with one coordinator, independent components. |
| Release | Sync/partial-write/inner failure and success release only the owned guard at settlement. |
| Cancellation | No early release after dispatch, including failed exact-action capture with an underlying pending call; original public result/reason compatibility. |
| Selection drift | Record 101 approved, selection changed to 102 with overlap: no unauthorized refund; SQL establishes actual effect. |
| Compatibility | Normal Livewire/current-record/passthrough/envelope behavior and historical consumers. |
| Packaging | Public import/types/build and generated candidate consumer; no future exports assumed in registry packages. |
| Native | Actual Chrome tool invocation; inspect nested business output and SQL, not Completed alone. |

## Verification and cleanup

Planning checks: source inspection, independent design review, full diff and
`python scripts/validate.py`. These do not qualify an implemented API.
Implementation checks: browser `npm run typecheck`, `npm test`, `npm run build`,
relevant generator tests, live Filament `npm test`, clean artifact/consumer checks,
adapter conformance and canonical validation. Reuse existing CI. Capture actual
test counts and runtime versions when run; do not add runners or expand releases.

Use task-owned Docker project `surfacerelay-t910`; retain sanitized evidence and
remove owned stacks/volumes/images and demos at completion. Preserve unrelated
resources/user locks. Use Chrome native acceptance; embedded loopback repair is
outside scope.

## Decision gate

The owner approved the specialized driver, exact membership/snapshots and explicitly
shared coordinator as the D-075 package design. T-910 remains open until executable
implementation acceptance; [executable acceptance now passes](../reviews/t910-filament-acceptance.md).
