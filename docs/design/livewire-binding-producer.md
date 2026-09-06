# Livewire Mounted Binding Producer Design

Status: approved architecture candidate for T-203 implementation planning.

## Goal

Produce fresh component-scoped Runtime Bindings for the exact currently mounted Livewire component instance and its explicit T-202 action exposures, without confusing PHP request teardown with browser component lifetime.

T-203 answers:

> Given a trusted mounted component object, which exact Runtime Bindings should be issued for its explicit SurfaceRelay exposures right now?

It does not execute bindings, resolve browser targets, register WebMCP tools, or claim that a PHP component object's request teardown means the browser component has disappeared.

## Critical Livewire lifecycle finding

Livewire's PHP component object is not a long-running representation of the mounted browser component.

On subsequent requests Livewire reconstructs the PHP object from the snapshot and reuses the snapshot memo component ID. Internally, Livewire also triggers a server-side `destroy` hook at the end of mount/update handling.

Therefore:

```text
PHP object destroy
        !=
browser component unmount
```

T-203 MUST NOT invalidate component-lifecycle bindings from the server-side `destroy` hook. Doing so would make normal Livewire updates invalidate bindings after every request.

The browser runtime has the actual component-lifetime signals:

- a Livewire component has an exact client-side ID;
- `$wire.$id` exposes that ID;
- `Livewire.find(id)` resolves the exact current component;
- `Livewire.hook('component.init', ({ component, cleanup }) => ...)` provides component-scoped cleanup when the client component lifetime ends.

Those browser signals belong to M3 registration/resolution work. T-203 prepares descriptors that can later be lifecycle-managed correctly.

## Decision boundary

Record the next free ACCEPTED decision with this meaning:

> Livewire PHP request teardown is not component-lifecycle authority. Server-side binding production derives the exact mounted component ID and issues fresh component-scoped bindings; browser registration cleanup and stale-target resolution are enforced later from client component lifecycle signals. No server `destroy` hook may silently revoke or retarget component bindings.

This decision does not weaken D-023/D-024/D-025. It assigns enforcement to the runtime layer that actually owns the lifecycle signal.

## Existing architecture

T-203 builds on:

```text
T-202 explicit exposure
        │
        ▼
LivewireActionExposure
├── exact ActionDefinition
└── method
        │
        + trusted mounted component identity
        + fresh binding ID
        ▼
LivewireRuntimeBinding::forComponent(...)
        │
        ▼
RuntimeBinding
├── lifecycle = component
├── target.componentId = exact mounted ID
└── target.method = explicit exposed method
```

No Action Definition fields change.

## Chosen architecture

Use three small responsibilities:

```text
BindingIdGenerator
        │
        ├── RandomBindingIdGenerator
        │
        ▼
LivewireComponentIdentityResolver
        │
        ▼
LivewireBindingProducer
```

### BindingIdGenerator

Protocol/runtime-neutral contract:

```php
interface BindingIdGenerator
{
    public function generate(): string;
}
```

Default implementation:

```text
RandomBindingIdGenerator
```

It generates opaque cryptographically random IDs locally using native PHP `random_bytes()` and a stable prefix/encoding that remains well within the frozen 240-character bindingId maximum.

Requirements:

- no caller-supplied binding ID at the producer boundary;
- no derivation from action ID, method, component name, or component ID;
- no deterministic reuse across repeated production calls;
- generated value must already satisfy RuntimeBinding bindingId lexical limits;
- no external UUID dependency.

Rationale: D-022 says a binding ID identifies one exact issued binding instance and must not be intentionally reused or retargeted. Deriving IDs from target fields risks resurrecting an old reference after an exposure disappears and later reappears.

The lower-level T-201 `LivewireRuntimeBinding::forComponent(bindingId: ...)` remains available as a descriptor factory; T-203 is the trusted issuance boundary that chooses the ID.

### LivewireComponentIdentityResolver

Livewire-specific trusted resolver contract:

```php
interface LivewireComponentIdentityResolver
{
    public function resolve(object $component): string;
}
```

Reference implementation:

```text
MethodLivewireComponentIdentityResolver
```

It reads the component's framework-provided `getId()` method and requires a non-empty string.

It accepts no caller payload/metadata/componentId parameter.

This preserves D-007: binding target authority comes from the trusted runtime object, not action input.

T-203 does not require `livewire/livewire` as a Composer dependency. The resolver operates against the minimal runtime method contract; real Livewire components provide `getId()`.

If the object does not provide a callable `getId()` returning a non-empty string, production fails loudly through an adapter-specific configuration exception.

### LivewireBindingProducer

Interface shape:

```php
final class LivewireBindingProducer
{
    public function __construct(
        private LivewireActionExposureReader $exposureReader,
        private LivewireComponentIdentityResolver $componentIdentityResolver,
        private BindingIdGenerator $bindingIdGenerator,
    ) {}

    /** @return list<RuntimeBinding> */
    public function forComponent(object $component): array;
}
```

Algorithm:

```text
trusted mounted component object
        │
        ├── identity resolver → exact componentId
        │
        └── T-202 exposure reader → deterministic exposures
                          │
                          ▼
                  for each exposure
                          │
                          ├── generate fresh bindingId
                          └── LivewireRuntimeBinding::forComponent(
                                  bindingId,
                                  exact definition,
                                  LivewireBindingTarget(componentId, method)
                              )
                          │
                          ▼
                   list<RuntimeBinding>
```

The producer never invokes component methods.

## Fresh issuance semantics

Each producer call represents a new issuance operation.

For each exposure it requests a fresh binding ID.

Calling the producer twice for the same live component therefore yields descriptors with the same exact component target/action identity but different binding IDs.

This is deliberate:

```text
same target
+ new issuance
→ new binding instance
→ new bindingId
```

No API aliases an old ID to a newly issued descriptor.

## Duplicate generated IDs

A cryptographically random collision is extremely unlikely, but an injected generator can be faulty or maliciously configured.

Within one produced batch, duplicate generated binding IDs must fail loudly before returning a partial result.

Do not silently regenerate in a loop: deterministic fail-loud behavior is easier to test and avoids hiding a broken generator.

Cross-batch persistence/revocation is not implemented by T-203 because there is not yet a browser/current-binding registry. Later runtime registration owns current existence.

## Component replacement semantics

A newly mounted replacement Livewire component has its own exact component ID.

Producing bindings for it creates:

```text
new componentId
+ fresh bindingId
```

The producer never edits an old RuntimeBinding to point at the replacement.

Old descriptors retain their original exact target forever.

When browser resolution exists, an old descriptor whose component ID no longer resolves is stale and must fail closed under D-025.

T-203 does not fake that browser check on the PHP server.

## Navigation semantics

`wire:navigate` can replace the page without a full browser reload. Document-level listeners may survive navigation, while component instances are discovered/cleaned up independently.

Therefore navigation itself is not the binding authority. Exact component lifecycle is.

M3 must group component-lifecycle bindings by exact `componentId` and unregister/revoke current browser registrations from the `component.init` cleanup callback rather than guessing from URL changes.

A navigation that leaves a component alive must not invalidate it merely because the URL changed. Conversely, a component replacement must invalidate old registrations even when the route did not change.

## Output ordering

The producer preserves the deterministic T-202 exposure order:

1. action ID ascending;
2. version ascending;
3. method ascending.

Binding IDs are random and must not be used as a sorting key.

This gives deterministic semantic ordering even though issued reference values differ.

## Expiry

T-203 emits component-lifecycle bindings with:

```text
expiresAt = null
```

by default.

Component lifetime already provides the intended lifecycle bound. Introducing expiry policy here would mix T-203 with future policy/idempotency/security controls.

The T-201 lower-level factory retains optional expiry capability for callers/adapters that need the frozen contract field.

## Extensions

T-203 emits no extensions by default.

Do not add transport/browser-specific extension metadata merely to prepare for M3.

## Error model

Create a focused adapter exception such as:

```text
InvalidLivewireBindingProduction
```

Expected cases:

```text
componentIdentityUnavailable
componentIdentityInvalid
duplicateGeneratedBindingId
```

Exposure configuration errors from T-202 continue to propagate as `InvalidLivewireActionExposure`.

RuntimeBinding construction errors remain `InvalidRuntimeBinding`.

Do not normalize these installation/runtime-configuration problems into ActionResult.

## No Livewire Composer dependency yet

The current package remains usable without forcing every Laravel consumer to install Livewire.

T-203 needs only the stable component identity method exposed by actual Livewire components. The reference identity resolver uses the minimal `getId()` contract.

If later integration requires Livewire service/container/hook APIs, introduce a real package dependency at that task rather than speculatively here.

## Security invariants

1. `componentId` is never accepted as an action/caller parameter by the producer.
2. Binding IDs are generated inside the trusted runtime boundary.
3. Binding ID remains a reference, not authorization proof.
4. Exact T-202 ActionDefinition objects are reused.
5. Exact exposed method names are reused; no method inference/fallback.
6. New issuance never intentionally reuses an old binding ID.
7. Replacement never retargets an old descriptor.
8. PHP request teardown is never treated as browser unmount authority.
9. Producer never invokes component code except the minimal trusted identity accessor.
10. No browser/WebMCP semantics enter ActionDefinition or RuntimeBinding core fields.

## Non-goals

T-203 must not implement:

- browser `Livewire.find()` target resolution;
- `component.init` registration/cleanup hooks;
- WebMCP tool registration;
- browser binding registry;
- binding invocation;
- ActionBus execution;
- stale/error ActionResult normalization;
- D-026 error-code finalization;
- server-side persistence/revocation store;
- discovery authorization;
- confirmation/idempotency/audit controls;
- current record/selection resolution;
- Livewire method callability/signature validation;
- component class/name fallback;
- URL/navigation-based target substitution.

These remain later tasks, primarily T-204 and M3.

## Acceptance tests

Minimum coverage:

```text
✓ producer derives component ID from trusted resolver, not a caller argument
✓ exact T-202 exposures become RuntimeBindings
✓ target uses exact componentId + explicit method
✓ driver always livewire
✓ lifecycle always component
✓ exact ActionDefinition object/identity retained
✓ each produced exposure receives a fresh bindingId
✓ repeated production for same component yields new binding IDs
✓ replacement component ID yields new exact target and never mutates old binding
✓ empty exposure list returns empty binding list
✓ output preserves deterministic exposure order independent of random IDs
✓ missing getId identity fails loudly
✓ empty/non-string getId result fails loudly
✓ duplicate generated IDs in one batch fail loudly with no partial return
✓ exposure-reader errors propagate fail-loud
✓ producer never invokes exposed component methods
✓ expiresAt remains null by default
✓ no extensions emitted by default
✓ no livewire/livewire dependency introduced
✓ no server destroy-hook invalidation exists
```

## Verification

T-203 implementation completion requires:

```bash
cd packages/laravel
composer test -- --filter "LivewireBindingProducer|BindingIdGenerator|ComponentIdentity"
composer test
composer validate --strict
cd ../..
python scripts/validate.py

cd packages/browser-runtime
npm ci
npm run typecheck
npm test
cd ../..
```

GitHub Actions must remain green across:

```text
contract
PHP 8.3/8.4 × Illuminate 12/13
php-lint
browser
```

## Completion boundary

T-203 is DONE when mounted component identity resolution, fresh binding issuance, replacement/no-retarget semantics, tests, decision documentation, and review are complete.

Actual browser lifecycle cleanup/stale-target resolution remains deliberately deferred to M3 because that runtime owns the lifecycle authority signal.

Then stop before T-204 unless T-203 review passes.
