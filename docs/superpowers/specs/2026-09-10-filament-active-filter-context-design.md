# T-503 — Filament Active-Filter Trusted Context Design

## Status

Approved design for M5 / T-503.

- Base: `main@00cf05d48b4c02e0eeaa0d8413683d39db4e0f65`
- Branch: `feat/filament-active-filter-context`
- Target vertical: Filament 5.x on the existing Laravel 12/13 + Livewire 4 runtime
- Verified predecessor: T-502 `current_selection` is DONE / REVIEWED / MERGED / main-revalidated
- Protocol impact: none; `spec/0.1/**` remains frozen
- Execution-driver impact: none; Filament continues to use the existing Livewire RuntimeBinding execution path
- Out of scope: search/sort/tab/group/pagination context, confirmation UI bridge (T-504), order-operations demo (T-505), HTMX vocabulary promotion

## Objective

T-503 lets trusted Filament adapter code expose the exact **applied table-filter state** that constrains the current table view without allowing caller input, generic invocation metadata, pending filter-form state, route/query data, or raw Livewire properties to manufacture that authority.

The task must preserve the frozen `spec/0.1` `ContextRequirement` vocabulary. `active_filters` has not yet earned protocol-neutral core vocabulary across materially different bindings, so it is represented as a namespaced trusted runtime extension in the Laravel reference runtime rather than being added to `ContextRequirement`.

The resulting state is an invocation-time snapshot. It represents the filter state Filament itself supplies to its table-query filter pipeline at that moment. It does not attempt to interpret whether a custom filter is semantically “active” from truthiness, UI indicators, labels, or filter-specific conventions.

```text
Action Definition       = unchanged; no Filament field and no new context requirement
Exact Filament Page     = owns authoritative current table state
Exposure policy         = trusted server code explicitly requests active-filter context
Filter resolver         = reads Filament public applied-filter APIs only
Trusted extension       = carries canonical applied-state snapshot + provenance/scope key
ActionBus               = existing authorization/confirmation/idempotency/execution path
Livewire RuntimeBinding = remains the only execution binding for this vertical
```

## Locked decision — D-050

Adapter-specific trusted UI authority that has not earned frozen core `ContextRequirement` vocabulary is represented by a physically separate, namespaced **trusted runtime context extension** channel, never by `InvocationContext::metadata` and never by silently expanding `spec/0.1`.

For T-503:

- the extension key is exactly `filament/active_filters`;
- it exists only when trusted server-side Filament adapter wiring explicitly requests it;
- its authority source is the exact active Filament table page and Filament's public **applied** filter-state API;
- deferred/pending filter-form state is never authority until Filament applies it;
- every present trusted runtime extension participates in confirmation scope and idempotency intent by default;
- audit may persist only the extension key and provider, never raw filter values, canonical bytes, or scope keys;
- this reference-runtime extension mechanism does not add a new Action Definition field, RuntimeBinding driver, browser argument, or protocol requirement.

## Why `active_filters` is not a new `ContextRequirement`

The frozen `spec/0.1` vocabulary currently contains:

```text
authenticated_actor
tenant
current_record
current_selection
browser_session
human_confirmation
```

Adding `active_filters` to the Laravel enum without changing the schema would make the runtime diverge from the contract. Changing the schema during T-503 would require a protocol migration with fixtures/conformance evidence and would promote a Filament-shaped concept before the HTMX portability proof.

Neither is justified by current evidence.

T-503 therefore uses a generic runtime extension seam with strict trust semantics. A later cross-binding task may promote a concept into protocol-neutral vocabulary only if implementation evidence shows that the same semantic requirement survives Filament and another materially different binding.

## Why generic invocation metadata is forbidden

`InvocationContext::metadata` is explicitly non-authoritative. A metadata key such as `active_filters`, `filters`, `status`, or `dateRange` can be caller-controlled and must never influence authorization, confirmation scope, idempotency identity, or application authority merely because its name resembles trusted UI state.

Putting T-503 data in metadata would collapse the trust boundary established by D-007 and D-027.

Trusted runtime extensions are therefore stored separately from:

- action input;
- generic metadata;
- binding ID;
- confirmation receipt;
- idempotency key;
- route/query/request parameters;
- browser/WebMCP arguments.

## Filament evidence — applied state versus form state

Filament 5 exposes two materially different public reads:

```php
getTableFilterState(string $name): ?array
getTableFilterFormState(string $name): ?array
```

Filament's own table query application path calls `getTableFilterState()` when it applies each configured filter to the query.

When deferred filters are enabled, `getTableFilterFormState()` reads the pending form state from `tableDeferredFilters`, while `getTableFilterState()` continues to read the applied `tableFilters` state. `applyTableFilters()` is the framework transition that copies deferred state into applied state.

Therefore the T-503 authority boundary is:

```text
pending/deferred form state ──X──> SurfaceRelay authority

Filament apply transition
        │
        ▼
applied table filter state
        │
        ▼
getTableFilterState(name)
        │
        ▼
SurfaceRelay trusted snapshot
```

This rule remains true when a table uses live filters: in that mode the framework keeps the applied state current and `getTableFilterState()` remains the query-facing read.

## Authority source

The only page-level authority is the exact active `Filament\Resources\Pages\Page` instance already supplied by trusted adapter code to the T-501/T-502 gateway path.

Active-filter context requires the page to implement `Filament\Tables\Contracts\HasTable`.

The resolver uses only public Filament APIs from that exact page/table:

- `Page instanceof HasTable`;
- `$page->getTable()`;
- `$page->getTable()->getFilters()` with Filament's normal visibility semantics;
- `$page->getTableFilterState($filterName)`.

The resolver does **not** read or reconstruct authority from:

- `$page->tableFilters` directly;
- `$page->tableDeferredFilters` directly;
- `getTableFilterFormState()`;
- filter indicators or human-readable labels;
- Livewire property arrays;
- request/query/route values;
- session persistence keys;
- SQL strings/bindings;
- frontend DOM or Alpine state;
- caller-provided filter names or values.

Filament's table query implementation itself iterates `getFilters()` and calls `getTableFilterState()`. T-503 mirrors that public semantic seam instead of reimplementing filter activation rules.

## Visibility semantics

T-503 uses `Table::getFilters()` with Filament's default argument, not `getFilters(withHidden: true)`.

This matters because Filament's normal table-query filter path also uses the default visible-filter set. SurfaceRelay must model the same applied state that Filament's table query consumes, not hidden configuration that the query path does not currently apply.

No separate SurfaceRelay concept of hidden-filter authority is introduced in T-503.

## Explicit exposure

Active-filter context is **not** attached to every Filament invocation automatically.

Trusted application code opts in through a typed adapter-side exposure value, conceptually:

```text
FilamentContextExposure::activeFilters()
```

The exposure value is server-side adapter configuration for one gateway/factory invocation. It is not part of Action input or the wire invocation envelope and is never derived from metadata or browser arguments.

Default behavior remains:

```text
no explicit exposure
→ no `filament/active_filters` trusted extension
→ existing T-501/T-502 invocation context remains byte-for-byte/security-semantically compatible
```

When exposure is explicitly requested:

```text
exact Page is not a table page
→ fail closed

exact Page is a table page
→ resolve applied filter snapshot
→ attach `filament/active_filters` trusted extension
```

An exposed table with no configured/applied filters still produces a present trusted extension with an empty canonical snapshot. This deliberately distinguishes:

```text
extension absent = application did not expose active-filter authority
extension present + empty snapshot = application exposed it and the effective filter state is empty
```

## Generic trusted runtime extension channel

T-503 introduces one generic Laravel-runtime primitive for adapter-specific trusted authority that is outside the frozen core requirement vocabulary.

Conceptual value object:

```text
TrustedContextExtension
- key: namespaced non-empty identifier
- value: non-null trusted runtime value; `[]`, false, 0 and "" remain valid
- provenance: ContextProvenance
- scopeKey: optional non-empty stable trusted identity
```

The extension-key grammar follows the project's existing namespaced extension convention:

```text
namespace/name
```

T-503 uses exactly:

```text
filament/active_filters
```

`InvocationContext` stores trusted extensions separately from core `TrustedContextEntry` values and separately from non-authoritative metadata.

Conceptual API:

```text
hasTrustedExtension(key)
getTrustedExtension(key)
requireTrustedExtension(key)
allTrustedExtensions()
withTrustedExtension(entry)
```

Required invariants:

- duplicate trusted-extension keys fail loudly;
- extension ordering is deterministic by exact key bytes, independent of constructor/caller ordering;
- null is not a present extension value; absence is represented by no entry;
- falsy values including an empty array remain legitimate present values;
- generic metadata is never promoted into the trusted-extension collection;
- rebuilding context through `withTrustedEntry()` or `withTrustedExtension()` preserves both trusted collections exactly;
- core `ContextRequirement::cases()` ordering and behavior remain unchanged.

The new channel is a reference-runtime API, not a `spec/0.1` wire field.

## Components

```text
exact trusted active Filament Page
        │
        ├── existing T-501 record resolver
        ├── existing T-502 selection resolver
        │
        └── explicit FilamentContextExposure::activeFilters() ?
                    │
                    ▼
        FilamentActiveFilterContextResolver
                    │
                    ├── require HasTable
                    ├── public getTable()->getFilters()
                    ├── public getTableFilterState(name)
                    ├── canonical representability check
                    └── stable filter scope key
                    │
                    ▼
              ResolvedTrustedValue
                    │
                    ▼
 TrustedContextExtension("filament/active_filters")
                    │
                    ▼
        FilamentInvocationContextFactory
                    │
                    ▼
              InvocationContext
                    │
                    ▼
          FilamentActionGateway
                    │
                    ▼
     existing ActionCall → ActionBus
                    │
                    ▼
     existing Livewire RuntimeBinding execution
```

`FilamentTrustedContextComposer` continues to own only frozen core context requirements (`authenticated_actor`, `tenant`, `current_record`, `current_selection`). T-503 does not overload that list with non-core vocabulary.

## Active-filter snapshot representation

The resolver builds one associative map keyed by exact configured filter name.

For every filter returned by Filament's normal `getFilters()` collection:

```text
snapshot[filterName] = page.getTableFilterState(filterName)
```

The snapshot includes the exact applied state even when that state contains values that look “inactive”, for example:

```text
null
false
0
""
[]
{"isActive": false}
```

SurfaceRelay does not drop these by truthiness and does not call filter-specific `isActive()` heuristics. Doing so would create a second interpretation layer that can diverge from Filament custom filters.

Filter names are sorted lexicographically by exact bytes before scope encoding. Nested associative state is canonicalized with the existing `RuntimeScopeCanonicalizer`; list order is preserved because list order can be semantically meaningful to a custom filter.

The extension's application-facing `value` is the canonicalizable applied-state map itself. No Eloquent Builder, SQL, indicator text, form component object, or filter object is exposed as the trusted value.

## Representability and fail-closed behavior

Filter state may originate from user interaction, but “trusted” here means that the server can truthfully attest **this is the state Filament has applied**, not that every nested value is inherently safe application data.

The snapshot must be representable by the existing runtime canonicalization rules:

- null, bool, int, finite float and string are representable;
- lists are recursively representable and preserve order;
- associative arrays require string keys and are recursively sorted for canonical encoding;
- objects/resources/non-finite floats or other unsupported runtime values fail closed.

A custom filter that stores an unrepresentable state must not silently fall back to string casting, `serialize()`, object IDs, labels, or omission. T-503 reports a static-safe adapter failure and produces no partial trusted extension.

## Stable scope key

The resolver derives a stable non-secret scope key from the canonical applied-filter snapshot using a dedicated domain separator, conceptually:

```text
SHA-256(
  "surfacerelay.filament.active-filters.v1\n"
  + canonical(snapshot)
)
```

The raw filter state is not embedded in the scope key and the scope key is not authority by itself. It is only a deterministic trusted identity for confirmation/idempotency scoping.

Required properties:

- identical applied snapshots produce identical scope keys;
- map insertion order does not affect the key;
- nested associative-key order does not affect the key;
- list ordering remains significant;
- int `1` and string `"1"` remain distinct;
- false, zero, empty string, null and empty arrays remain distinct canonical values where structurally distinguishable;
- changed applied filter state changes the scope key;
- changing only pending deferred form state does not change the key until Filament applies it.

## Provenance

```text
extension key = "filament/active_filters"
provider      = "filament.active_filters"
reference     = null
```

Provenance must not contain:

- filter names or values;
- page/resource/table class names;
- route/query values;
- filter labels/indicators;
- SQL or bindings;
- canonical bytes;
- scope hashes.

## Caller-spoofing boundary

None of these can satisfy, create, replace, or change `filament/active_filters` authority:

- input fields named `filters`, `activeFilters`, `tableFilters`, `tableDeferredFilters`, `status`, or similar;
- `InvocationContext::metadata`;
- route/query/request values;
- persisted browser values;
- `bindingId`;
- confirmation receipt;
- idempotency key;
- WebMCP tool arguments;
- caller-provided hash/count/state descriptors.

Only trusted server code can request exposure, and only the exact active Filament table page can provide the applied snapshot.

## Interaction with authorization

Active-filter context does not authorize an action by itself.

Existing authorization still evaluates the exact trusted actor/tenant and application rules. Application code may inspect the trusted extension when its business semantics explicitly depend on current filters, but the filter values do not bypass validation, tenant scope, policies, Gates, or current-record/current-selection semantics.

No filter state is translated directly into raw SQL by SurfaceRelay.

## Interaction with confirmation — D-044

Every trusted runtime extension is security-relevant by definition and therefore participates in confirmation scope automatically.

`ConfirmationScopeHasher` extends its document with a deterministic trusted-extension map after the existing core context map.

Conceptually:

```text
context = existing core trusted requirements
extensions = {
  "filament/active_filters": trustedIdentity(extension)
}
```

Required proofs:

- with no trusted extensions, existing confirmation fingerprints remain byte-for-byte unchanged;
- exposed empty active-filter context differs from no exposure;
- identical applied filters produce the same confirmation scope;
- changed applied filters produce a different scope;
- pending deferred changes alone do not change scope;
- a receipt issued for filter state A cannot authorize applied filter state B;
- wrong-filter mismatch does not consume an otherwise-valid receipt for state A;
- caller metadata that mimics filter state does not affect confirmation scope.

## Interaction with idempotency — D-045

Every trusted runtime extension also participates in the idempotency **intent fingerprint** automatically.

It does not alter the D-045 lookup-key authority partition, which remains tenant/actor/browser-session/global as currently defined. It changes only the exact intent identity under a given caller key.

Required proofs:

- with no trusted extensions, existing intent fingerprints remain byte-for-byte unchanged;
- identical applied filter state produces the same intent fingerprint;
- changed applied state produces a different intent fingerprint;
- pending deferred state does not change intent until applied;
- the same idempotency key reused across different exposed applied-filter states conflicts instead of replaying old output;
- caller metadata cannot recreate the trusted extension fingerprint.

## Interaction with structured audit — D-047

Audit must record that an authority dimension was present without persisting filter contents.

The existing `trusted_context_manifest` JSON remains the storage field. Core entries preserve their current shape:

```json
{"requirement":"current_selection","provider":"filament.current_selection"}
```

Trusted extension entries use a disjoint shape:

```json
{"extension":"filament/active_filters","provider":"filament.active_filters"}
```

A manifest item must contain exactly one of `requirement` or `extension` plus `provider`.

Audit must never persist:

- filter state or values;
- filter names individually beyond the fixed extension key;
- pending/deferred state;
- canonical snapshot bytes;
- scope key/fingerprint;
- SQL/query bindings;
- filter indicator labels.

Core manifest serialization remains backward-compatible for existing entries.

## Snapshot freshness

`filament/active_filters` is resolved exactly once per SurfaceRelay invocation while `FilamentInvocationContextFactory` builds the trusted invocation context.

That snapshot is fixed for the invocation. A later UI change does not mutate the already-built context object.

A later invocation resolves again from the exact current Page instance.

Consequences:

- a confirmation challenge binds the applied filters from the invocation that created it;
- a confirmed retry after filters change resolves the new filter snapshot and rejects the old receipt by scope mismatch;
- idempotency intent follows the newly resolved applied snapshot;
- pending deferred edits do not alter authority before Apply;
- after Apply, the next invocation sees the new applied state;
- no long-lived filter snapshot is synchronized in the background.

## Interaction with T-502 selection

`current_selection` and `filament/active_filters` remain independent dimensions.

```text
current_selection         = which exact records Filament resolves as selected
filament/active_filters   = which applied filter states Filament supplies to its filter query pipeline
```

If filters change but Filament still resolves the exact same selected set:

- T-502 selection identity remains unchanged;
- T-503 filter-extension identity changes after the new state is applied.

If applying filters also changes effective selection semantics, both dimensions may change independently according to their own resolvers.

T-503 must not embed filter state into the T-502 selection scope key.

## Failure model

T-503 adapter/runtime failures are static-safe, non-chained, and produce no partial trusted authority.

Representative categories:

- `active_filter_context_unavailable` — explicit exposure requested on a non-table page;
- `active_filter_resolution_failed` — a supported public Filament read failed;
- `unrepresentable_active_filter_state` — applied state cannot be canonicalized;
- `duplicate_trusted_extension` — generic runtime extension key collision;
- `invalid_trusted_extension` — malformed key/null value/empty scope key.

Failures must not expose:

- raw filter values;
- page/resource/filter class names where unnecessary;
- underlying Filament/Livewire exception text;
- SQL/bindings;
- scope hashes;
- canonical serialized state.

The adapter catches framework exceptions at its boundary and throws static-safe SurfaceRelay exceptions without chaining sensitive underlying errors.

## Public API compatibility

T-503 may add optional server-side adapter parameters/types but must not break existing call sites.

Required compatibility:

- `FilamentActionGateway::dispatch(...)` without an exposure argument behaves exactly as T-502;
- `FilamentInvocationContextFactory::forPage(...)` without exposure behaves exactly as T-502;
- existing `InvocationContext` constructor call sites remain valid through an optional trusted-extension parameter;
- existing core `ContextRequirement` APIs and ordering remain unchanged;
- existing audit core-manifest JSON entries keep the exact `{requirement, provider}` shape;
- `spec/0.1/**` remains unchanged;
- `packages/browser-runtime/src/**` remains unchanged;
- `packages/laravel/src/Livewire/**` remains unchanged.

## Proposed implementation units

### Generic runtime

- `packages/laravel/src/Runtime/Context/TrustedContextExtension.php`
- static-safe duplicate/absence/validation exceptions as required
- `packages/laravel/src/Runtime/InvocationContext.php`
- `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php` only as needed to share trusted-identity encoding across requirement and extension entries
- `packages/laravel/src/Confirmation/ConfirmationScopeHasher.php`
- `packages/laravel/src/Idempotency/IdempotencyIntentHasher.php`

### Filament adapter

- `packages/laravel/src/Filament/Context/FilamentContextExposure.php`
- `packages/laravel/src/Filament/Context/FilamentActiveFilterContextResolver.php`
- `packages/laravel/src/Filament/Context/InvalidFilamentActiveFilterContext.php`
- `packages/laravel/src/Filament/Context/FilamentInvocationContextFactory.php`
- `packages/laravel/src/Filament/Invocation/FilamentActionGateway.php`

### Audit

- `packages/laravel/src/Audit/AuditTrustedContextEntry.php`
- `packages/laravel/src/Audit/AuditEventFactory.php`
- `packages/laravel/src/Audit/DatabaseAuditEventStore.php`

The implementation plan may split exception/value-object files more narrowly if existing repository conventions require it, but it must not merge framework-specific filter logic into generic runtime classes.

## Testing strategy

### Generic trusted-extension unit tests

Prove:

- namespaced key validation;
- null rejection and falsy-value preservation;
- duplicate-key rejection;
- deterministic extension ordering;
- `withTrustedEntry()` preserves extensions;
- `withTrustedExtension()` preserves core entries;
- metadata with the same key never becomes trusted authority;
- core-only `InvocationContext` behavior remains unchanged.

### Filter resolver unit tests

Prove:

- non-table explicit exposure fails closed;
- zero configured filters resolves a present empty snapshot;
- exact configured filter names/states are captured;
- map order canonicalizes deterministically;
- falsy/null nested applied states are preserved;
- unrepresentable custom state fails closed;
- underlying framework exceptions are not chained/leaked;
- caller data is never consulted.

### Real Filament integration tests

Using the real Filament page/table lifecycle, prove:

- live filter changes are observed through applied state;
- with deferred filters, editing form state without Apply does not change the trusted snapshot;
- after `applyTableFilters()`, the next invocation sees the new state;
- the resolver follows `getFilters()` normal visibility semantics;
- the exact state SurfaceRelay snapshots is the same state read by Filament's query-facing `getTableFilterState()` API.

### Gateway/factory integration tests

Prove:

- no exposure preserves T-502 trusted context exactly;
- explicit active-filter exposure adds exactly one trusted extension;
- input/metadata spoofing cannot add or change it;
- non-table explicit exposure fails before ActionBus execution;
- existing record/selection context remains independent.

### Trust-control integration tests

Prove:

- no-extension confirmation and idempotency regression hashes remain byte-for-byte compatible;
- filter state A/B scope mismatch behavior;
- deferred pending state does not invalidate a receipt until applied;
- changed applied state invalidates confirmation scope without spending the state-A receipt;
- same idempotency key + changed applied state conflicts rather than replaying;
- audit stores only `{extension, provider}` for T-503 and leaks no raw state/scope key.

### Dependency/boundary regression

Prove:

- Filament remains dev-only for the Laravel package;
- no Filament imports enter Action Definition, generic contracts, browser runtime, or Livewire driver code;
- frozen spec files are unchanged;
- no new RuntimeBinding driver or business execution endpoint is introduced.

## Required verification matrix

T-503 must retain the existing project matrix:

```text
PHP 8.3 / Illuminate 12 / Testbench 10
PHP 8.3 / Illuminate 13 / Testbench 11
PHP 8.4 / Illuminate 12 / Testbench 10
PHP 8.4 / Illuminate 13 / Testbench 11
Filament 5.x + Livewire 4.x
MySQL 8.4 service coverage where the repository workflow already requires it
Browser TypeScript typecheck + existing Vitest suite
python scripts/validate.py
```

Exact dependency versions are resolved by the repository workflow; T-503 does not upgrade them as part of feature scope.

## Out of scope

T-503 does not add trusted context for:

- global table search;
- column search;
- sorting;
- active resource/list tabs;
- grouping;
- pagination/current page/cursor;
- filter indicator text;
- rendered filtered record IDs;
- arbitrary query SQL;
- saved filter presets as a new authority concept.

It also does not:

- change the Action Definition schema;
- add `ContextRequirement::ActiveFilters`;
- add a Filament RuntimeBinding driver;
- introduce a second execution path;
- build the human confirmation UI bridge;
- implement T-505 demo operations;
- make filter values authorization by themselves;
- persist raw filter state in audit;
- expose filter state to WebMCP tool metadata automatically.

## Acceptance summary

T-503 is complete only when all of the following are proven:

1. `spec/0.1` remains unchanged and `ContextRequirement` remains frozen.
2. A generic namespaced trusted-runtime-extension channel is physically separate from caller metadata and core requirements.
3. `filament/active_filters` can only be requested by explicit trusted server-side Filament exposure wiring.
4. Authority comes from the exact active `HasTable` Page and public `getFilters()` + `getTableFilterState()` APIs.
5. Deferred/pending form state is ignored until Filament applies it.
6. Empty exposed state is present and distinguishable from no exposure.
7. Snapshot canonicalization preserves types, nested list order and falsy states while normalizing associative-key order.
8. Unrepresentable/framework-failure states fail closed without leaking underlying details.
9. Present trusted extensions bind confirmation scope and idempotency intent automatically.
10. No-extension confirmation/idempotency behavior remains byte-for-byte compatible.
11. Audit persists only extension key/provider and never filter values or scope material.
12. `current_selection` remains independent and its T-502 scope behavior is unchanged.
13. Existing Livewire execution remains the sole action execution path.
14. Full PHP/browser/contract verification is green before T-503 is marked DONE.

## Implementation gate

No production implementation should begin until this design is reviewed as the T-503 contract boundary. After approval, create a dedicated TDD implementation plan under `docs/superpowers/plans/` and execute it task-by-task with RED → GREEN evidence.