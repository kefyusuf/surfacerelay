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

The frozen `spec/0.1` `ContextRequirement` vocabulary is preserved. `active_filters` has not earned protocol-neutral core vocabulary across materially different bindings, so it is represented as a namespaced trusted runtime extension in the Laravel reference runtime rather than being added to `ContextRequirement`.

The snapshot represents the filter state Filament itself supplies to its table-query filter pipeline at invocation time. SurfaceRelay does not attempt to infer whether a custom filter is semantically active from truthiness, indicators, labels, or filter-specific conventions.

```text
Action Definition       = unchanged; no Filament field and no new context requirement
Exact Filament Page     = owns authoritative current table state
Exposure policy         = trusted server code explicitly requests active-filter context
Filter resolver         = reads Filament public applied-filter APIs only
Trusted extension       = canonical applied-state snapshot + provenance/scope key
ActionBus               = existing authorization/confirmation/idempotency/execution path
Livewire RuntimeBinding = remains the only execution binding for this vertical
```

## Locked decision — D-050

Adapter-specific trusted UI authority that has not earned frozen core `ContextRequirement` vocabulary is represented by a physically separate, namespaced **trusted runtime context extension** channel, never by `InvocationContext::metadata` and never by silently expanding `spec/0.1`.

For T-503:

- extension key: `filament/active_filters`;
- exposure: only explicit trusted server-side Filament adapter wiring;
- authority source: exact active Filament table page + public applied filter-state API;
- deferred/pending form state: never authority until Filament applies it;
- security binding: every present trusted runtime extension participates in confirmation scope and idempotency intent by default;
- audit persistence: extension key/provider only, never filter values, canonical bytes, or scope keys;
- protocol: no new Action Definition field, RuntimeBinding driver, browser argument, or `ContextRequirement`.

## Why this is not a new `ContextRequirement`

The frozen vocabulary is:

```text
authenticated_actor
tenant
current_record
current_selection
browser_session
human_confirmation
```

Adding `active_filters` only to PHP would diverge from the schema. Changing the schema would prematurely promote a Filament-shaped concept before the HTMX portability proof and would require full contract migration evidence.

T-503 therefore uses a generic runtime extension seam with strict trust semantics. Later cross-binding evidence may justify protocol promotion; T-503 does not pre-decide that question.

## Why generic metadata is forbidden

`InvocationContext::metadata` is non-authoritative by design. Keys such as `active_filters`, `filters`, `status`, or `dateRange` may be caller-controlled and cannot influence authorization, confirmation scope, idempotency identity, or application authority merely because their names resemble trusted UI state.

Trusted runtime extensions remain physically separate from:

- action input;
- generic metadata;
- binding ID;
- confirmation receipt;
- idempotency key;
- route/query/request parameters;
- browser/WebMCP arguments.

## Filament evidence — applied state versus form state

Filament 5 exposes separate public reads:

```php
getTableFilterState(string $name): ?array
getTableFilterFormState(string $name): ?array
```

Filament's own table-query filter application path calls `getTableFilterState()` for configured filters.

With deferred filters, `getTableFilterFormState()` reads pending form state from `tableDeferredFilters`, while `getTableFilterState()` continues to read applied `tableFilters`. `applyTableFilters()` copies deferred state into applied state.

Therefore:

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

With live filters, applied state updates immediately and `getTableFilterState()` remains the query-facing authority read.

## Authority source

The page-level authority is the exact active `Filament\Resources\Pages\Page` supplied by trusted adapter code to the existing T-501/T-502 gateway path.

Active-filter context requires `Filament\Tables\Contracts\HasTable`.

Allowed public reads:

- `Page instanceof HasTable`;
- `$page->getTable()`;
- `$page->getTable()->getFilters()` using Filament's normal visibility semantics;
- `$page->getTableFilterState($filterName)`.

Forbidden reconstruction sources:

- direct `$page->tableFilters`;
- direct `$page->tableDeferredFilters`;
- `getTableFilterFormState()`;
- filter indicators/labels;
- raw Livewire state arrays;
- request/query/route values;
- session persistence keys;
- SQL strings/bindings;
- frontend DOM/Alpine state;
- caller-provided filter names/values.

Filament's normal query application path itself iterates `getFilters()` and reads `getTableFilterState()`. SurfaceRelay mirrors that semantic seam rather than reimplementing activation rules.

## Visibility semantics

T-503 uses `Table::getFilters()` with the default argument, not `getFilters(withHidden: true)`, because Filament's normal query filter path also uses the default filter set.

T-503 does not invent separate hidden-filter authority.

## Explicit exposure

Active-filter context is not attached to every Filament invocation.

Trusted application code opts in through a typed adapter-side value, conceptually:

```text
FilamentContextExposure::activeFilters()
```

This is server-side adapter configuration for the gateway/factory call. It is not action input, invocation metadata, or a wire/browser argument.

Default:

```text
no explicit exposure
→ no `filament/active_filters` trusted extension
→ existing T-501/T-502 behavior remains unchanged
```

Explicit exposure:

```text
non-table Page → fail closed
HasTable Page  → resolve applied snapshot → attach trusted extension
```

An exposed table with no configured filters produces a **present empty snapshot**. This distinguishes:

```text
extension absent          = application did not expose filter authority
extension present + []    = application exposed it and the effective filter set is empty
```

## Generic trusted runtime extension channel

T-503 introduces one generic Laravel-runtime primitive for trusted adapter authority outside the frozen core requirement vocabulary.

Conceptual value object:

```text
TrustedContextExtension
- key: namespaced non-empty identifier
- value: non-null trusted runtime value; [] / false / 0 / "" remain valid values
- provenance: ContextProvenance
- scopeKey: optional non-empty stable trusted identity
```

T-503 uses exactly:

```text
filament/active_filters
```

`InvocationContext` stores extensions separately from core `TrustedContextEntry` values and separately from non-authoritative metadata.

Conceptual API:

```text
hasTrustedExtension(key)
getTrustedExtension(key)
requireTrustedExtension(key)
allTrustedExtensions()
withTrustedExtension(entry)
```

Invariants:

- keys follow the project's namespaced `namespace/name` convention;
- duplicate keys fail loudly;
- ordering is deterministic by exact key bytes;
- null means absence and cannot be a present extension value;
- legitimate falsy values remain present;
- metadata is never promoted into trusted extensions;
- `withTrustedEntry()` and `withTrustedExtension()` preserve both trusted collections;
- core `ContextRequirement::cases()` behavior/order remains unchanged.

This channel is a reference-runtime API, not a `spec/0.1` wire field.

## Components and data flow

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

`FilamentTrustedContextComposer` continues to own only frozen core requirements (`authenticated_actor`, `tenant`, `current_record`, `current_selection`).

## Snapshot representation

The resolver builds one associative map keyed by exact configured filter name:

```text
snapshot[filterName] = page.getTableFilterState(filterName)
```

Per Filament's public API, each top-level filter state is `?array`. The resolver preserves that exact array-or-null state. Nested filter state may legitimately contain values such as:

```text
null
false
0
""
[]
{"isActive": false}
```

SurfaceRelay does not drop nested values by truthiness and does not invent filter-specific `isActive()` heuristics.

Filter names are sorted lexicographically before scope encoding. Nested associative maps are canonicalized by the existing `RuntimeScopeCanonicalizer`; list order is preserved because it may be semantically meaningful to custom filters.

The application-facing extension value is the canonicalizable applied-state map only. It never contains a Builder, SQL, filter object, form component, or indicator text.

## Representability and fail-closed behavior

“Trusted” means the runtime can attest **this is what Filament has applied**. It does not mean user-originated nested filter values are inherently safe or authorization-capable.

The snapshot follows existing runtime canonicalization:

- null, bool, int, finite float and string are representable nested values;
- lists preserve order;
- associative arrays require string keys and canonicalize by key order;
- objects/resources/non-finite floats and unsupported values fail closed.

No string-casting, PHP `serialize()`, object-ID fallback, indicator-label fallback, or partial omission is allowed.

## Stable scope key

The resolver derives a stable non-secret scope key:

```text
SHA-256(
  "surfacerelay.filament.active-filters.v1\n"
  + canonical(snapshot)
)
```

Properties:

- identical applied snapshots → identical keys;
- associative insertion order does not matter;
- nested associative-key order does not matter;
- list order remains significant;
- int `1` and string `"1"` remain distinct;
- nested false/zero/empty-string/null/empty-array values are preserved distinctly where structurally distinguishable;
- changed applied state changes the key;
- pending deferred edits do not change it until Apply.

The key is deterministic identity material only; it grants no authority by itself.

## Provenance

```text
extension key = "filament/active_filters"
provider      = "filament.active_filters"
reference     = null
```

Provenance never includes filter values/names, page/resource/table names, route/query values, indicator text, SQL, canonical bytes, or scope hashes.

## Caller-spoofing boundary

None of these can create or change `filament/active_filters` authority:

- input fields named `filters`, `activeFilters`, `tableFilters`, `tableDeferredFilters`, `status`, etc.;
- `InvocationContext::metadata`;
- route/query/request values;
- browser-persisted values;
- `bindingId`;
- confirmation receipt;
- idempotency key;
- WebMCP arguments;
- caller-provided state/hash/count descriptors.

Only trusted server code requests exposure and only the exact active Filament table page supplies the snapshot.

## Authorization interaction

Active-filter context does not authorize an action by itself. Existing actor/tenant/policy/Gate rules remain authoritative. SurfaceRelay never translates filter state directly into raw SQL.

Application code may inspect the extension when its business semantics explicitly depend on current filters, but this does not bypass normal validation/authorization or T-501/T-502 context.

## Confirmation interaction — D-044

Every trusted runtime extension is security-relevant by definition and binds confirmation scope automatically.

When at least one trusted extension exists, `ConfirmationScopeHasher` adds a deterministic `extensions` map keyed by extension key. **When no trusted extensions exist, the `extensions` field is omitted entirely** so all existing core-only fingerprints remain byte-for-byte unchanged.

Required proofs:

- no-extension fingerprints remain exact regressions;
- exposed empty filter context differs from no exposure;
- identical applied filters produce the same scope;
- changed applied filters produce a different scope;
- pending deferred changes do not change scope before Apply;
- a state-A receipt cannot authorize state B;
- wrong-filter mismatch does not spend the valid state-A receipt;
- metadata spoofing does not affect scope.

## Idempotency interaction — D-045

Every trusted runtime extension also binds the idempotency **intent fingerprint** automatically.

The D-045 lookup-key authority partition remains unchanged: tenant/actor/browser-session/global. Trusted extensions affect only intent identity under that lookup partition.

As with confirmation, **the `extensions` document member is omitted when no trusted extensions exist** so existing core-only fingerprints remain byte-for-byte unchanged.

Required proofs:

- no-extension intent fingerprints remain exact regressions;
- identical applied filters produce the same intent;
- changed applied filters change intent;
- pending deferred state changes nothing before Apply;
- same caller idempotency key + different applied filter state conflicts rather than replaying old output;
- metadata spoofing cannot recreate trusted extension intent.

## Structured audit interaction — D-047

Audit records authority presence without persisting filter contents.

The existing `trusted_context_manifest` JSON storage field remains. Core entries preserve their existing shape:

```json
{"requirement":"current_selection","provider":"filament.current_selection"}
```

Trusted extension entries use a disjoint shape:

```json
{"extension":"filament/active_filters","provider":"filament.active_filters"}
```

A manifest item contains exactly one of `requirement` or `extension`, plus `provider`.

Audit never persists filter state, individual filter names beyond the fixed extension key, pending/deferred state, canonical bytes, scope keys/fingerprints, SQL/bindings, or indicator labels.

## Snapshot freshness

`filament/active_filters` resolves exactly once while `FilamentInvocationContextFactory` builds one invocation context. That snapshot is fixed for the invocation; later UI edits do not mutate it.

A later invocation resolves again from the current exact Page.

Consequences:

- confirmation binds the applied filters present when the challenge was created;
- a confirmed retry after applied filters change sees a scope mismatch;
- idempotency follows the newly resolved applied snapshot;
- deferred edits do not alter authority before Apply;
- the next invocation after Apply sees the new state;
- no long-lived synchronized filter object is maintained.

## T-502 selection independence

```text
current_selection       = which exact records Filament resolves as selected
filament/active_filters = which applied filter states Filament supplies to its filter query pipeline
```

If filters change but the selected set remains identical, T-502 selection identity stays the same while T-503 extension identity changes after Apply. If filter changes also alter effective selection semantics, both dimensions may change independently.

Filter state is never embedded into the T-502 selection scope key.

## Failure model

Representative static-safe, non-chained failure categories:

- `active_filter_context_unavailable` — exposure requested on a non-table page;
- `active_filter_resolution_failed` — public Filament read failed;
- `unrepresentable_active_filter_state` — state cannot be canonicalized;
- `duplicate_trusted_extension` — trusted extension key collision;
- `invalid_trusted_extension` — malformed key/null value/empty scope key.

No failure exposes raw values, unnecessary framework class names, underlying exception text, SQL/bindings, canonical bytes, or scope hashes. No partial trusted extension is produced.

## Public API compatibility

- `FilamentActionGateway::dispatch(...)` without exposure behaves exactly as T-502.
- `FilamentInvocationContextFactory::forPage(...)` without exposure behaves exactly as T-502.
- Existing `InvocationContext` constructor calls remain valid via an optional trusted-extension parameter.
- Core `ContextRequirement` APIs/order remain unchanged.
- Existing audit core-manifest entries keep `{requirement, provider}` exactly.
- `spec/0.1/**` remains unchanged.
- `packages/browser-runtime/src/**` remains unchanged.
- `packages/laravel/src/Livewire/**` remains unchanged.

## Proposed implementation units

### Generic runtime

- `packages/laravel/src/Runtime/Context/TrustedContextExtension.php`
- focused static-safe duplicate/absence/validation exceptions
- `packages/laravel/src/Runtime/InvocationContext.php`
- `packages/laravel/src/Runtime/Scope/RuntimeScopeCanonicalizer.php` only if needed to share trusted-identity encoding
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

Framework-specific filter logic must remain outside generic runtime classes.

## Testing strategy

### Generic trusted-extension tests

Prove key validation, null rejection/falsy preservation, duplicate rejection, deterministic ordering, context rebuild preservation, metadata non-promotion, and unchanged core-only behavior.

### Filter resolver tests

Prove non-table fail-closed behavior, present empty snapshot, exact configured state capture, deterministic canonicalization, nested falsy/null preservation, unrepresentable-state rejection, non-leaking framework failures, and zero caller-data consultation.

### Real Filament tests

Prove live applied-state changes, deferred edits remaining non-authoritative before Apply, next-invocation state changing after Apply, default `getFilters()` visibility semantics, and parity with Filament's query-facing `getTableFilterState()` read.

### Gateway/factory tests

Prove no-exposure T-502 compatibility, explicit exposure adding exactly one extension, input/metadata spoofing failure, non-table early failure, and record/selection independence.

### Trust-control tests

Prove no-extension confirmation/idempotency hash compatibility, A/B scope differences, deferred-state stability, non-spending wrong-filter confirmation mismatch, idempotency conflict on changed applied state, and audit secrecy with only `{extension, provider}` persisted.

### Boundary tests

Prove Filament remains dev-only, no Filament imports enter Action Definition/browser/Livewire driver code, frozen spec files remain unchanged, and no new RuntimeBinding driver/business endpoint appears.

## Required verification matrix

```text
PHP 8.3 / Illuminate 12 / Testbench 10
PHP 8.3 / Illuminate 13 / Testbench 11
PHP 8.4 / Illuminate 12 / Testbench 10
PHP 8.4 / Illuminate 13 / Testbench 11
Filament 5.x + Livewire 4.x
MySQL 8.4 service coverage where existing workflow requires it
Browser TypeScript typecheck + existing Vitest suite
python scripts/validate.py
```

T-503 does not upgrade dependencies as feature scope.

## Out of scope

No trusted context is added for global/column search, sorting, tabs, grouping, pagination, indicators, filtered record IDs, arbitrary SQL, or saved-filter presets.

T-503 also does not change Action Definition schema, add `ContextRequirement::ActiveFilters`, add a Filament RuntimeBinding driver, add a second execution path, implement T-504/T-505, make filter values authorization by themselves, persist raw filter state, or expose filters automatically as WebMCP metadata.

## Acceptance summary

T-503 is complete only when:

1. `spec/0.1` and `ContextRequirement` remain unchanged.
2. Namespaced trusted runtime extensions are physically separate from metadata/core requirements.
3. `filament/active_filters` requires explicit trusted server-side exposure.
4. Authority comes only from exact `HasTable` Page + public `getFilters()` / `getTableFilterState()`.
5. Deferred form state is ignored until Apply.
6. Present empty state differs from no exposure.
7. Canonicalization preserves type/list semantics and normalizes associative ordering.
8. Framework/unrepresentable failures fail closed without leakage.
9. Present trusted extensions bind confirmation/idempotency automatically.
10. No-extension fingerprints remain byte-for-byte compatible.
11. Audit stores extension key/provider only.
12. T-502 selection identity remains independent and unchanged.
13. Existing Livewire execution remains the sole execution path.
14. Full PHP/browser/contract verification is green before DONE.

## Implementation gate

No production implementation begins until this design is reviewed as the T-503 contract boundary. After approval, create a dedicated TDD implementation plan under `docs/superpowers/plans/` and execute RED → GREEN task-by-task.