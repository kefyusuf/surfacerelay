# T-502 — Filament Current-Selection Trusted Context Design

## Status

Approved design for M5 / T-502.

- Base: `main@ae77cbf26cbefcca478d070765386628343bec52`
- Target vertical: Filament 5.x on the existing Laravel 12/13 + Livewire 4 runtime
- Predecessor: T-501 `current_record` is DONE / REVIEWED / MERGED / main-revalidated
- Protocol impact: none; `spec/0.1/**` remains frozen
- Execution-driver impact: none; Filament continues to use the existing Livewire execution path
- Out of scope: active-filter context (T-503), confirmation UI bridge (T-504), order-operations demo (T-505)

## Objective

T-502 implements D-017 for the Filament reference vertical by providing `current_selection` as trusted runtime context.

The authority is the exact selection that Filament resolves from the exact active table page at invocation time. Caller-supplied record IDs, generic invocation metadata, route/query/request values and raw Livewire selection properties never satisfy `current_selection` authority.

The resulting selection is a bounded invocation-time snapshot represented as an unordered set of exact persisted Eloquent record identities. The **membership and identity set** are frozen for that SurfaceRelay invocation. The Eloquent model objects themselves are not made immutable; application execution receives the exact selected model instances that Filament resolved and may interact with them normally.

```text
Action Definition   = declares `current_selection` requirement
Filament Page       = owns authoritative current table state
Selection Resolver  = snapshots exact selected records
Trusted Context     = carries bounded exact model set + stable scope key
ActionBus           = applies existing context/authorization/confirmation/idempotency controls
Livewire Binding    = remains the execution path
```

## Locked decision — D-049

Filament `current_selection` is a bounded invocation-time snapshot of the exact selection resolved through Filament's public table contract. It is an unordered set of exact persisted Eloquent record identities, never caller record IDs or raw Livewire selection properties. Empty selection is trusted-context absence. Ambiguous duplicate, unsupported non-Eloquent, invalid-identity and over-limit selections fail closed. Active filters remain a separate trusted-context concern and are not embedded into selection authority.

## Why the public Filament selection contract is authoritative

Filament 5 selection semantics are richer than a raw `selectedTableRecords` array.

The public `Filament\Tables\Contracts\HasTable` contract exposes:

```php
getSelectedTableRecords(
    bool $shouldFetchSelectedRecords = true,
    ?int $chunkSize = null,
): EloquentCollection|Collection|LazyCollection
```

Filament's implementation uses this API to account for:

- ordinary selected-record keys;
- select-all state represented through deselection exceptions;
- selectable-record constraints;
- table query semantics;
- relationship-backed tables;
- current table configuration such as selection limits;
- lazy/chunked selected-record resolution.

SurfaceRelay therefore must not reconstruct selection authority by reading or combining public Livewire properties such as:

- `$selectedTableRecords`;
- `$deselectedTableRecords`;
- `$isTrackingDeselectedTableRecords`.

Those properties are framework state representation, not the SurfaceRelay authority contract. Reimplementing their interpretation would duplicate Filament behavior and could diverge across framework versions.

T-502 relies only on the supported public table contract from the exact active page.

## Authority source

The trusted source is the exact active Filament `Page` instance already supplied to the T-501 production gateway.

A page can contribute `current_selection` only when it implements `Filament\Tables\Contracts\HasTable`.

The resolver does not discover a page through:

- request globals;
- route parameters;
- container scans;
- Livewire component names;
- DOM inspection;
- resource-class lookup;
- table identifiers supplied by the caller.

The exact Page instance remains the authority boundary established by T-501.

## Components

```text
exact trusted active Filament Page
        │
        ├── record resolver (T-501, when applicable)
        │
        └── instanceof HasTable ?
                    │
                    ▼
        FilamentCurrentSelectionResolver
                    │
                    │ public getSelectedTableRecords(...)
                    ▼
        bounded selected Eloquent models
                    │
                    ├── shared exact record identities
                    ├── duplicate/unsupported checks
                    ├── canonical set ordering
                    └── selection scope key
                    │
                    ▼
              ResolvedTrustedValue
                    │
                    ▼
        FilamentTrustedContextComposer
                    │
                    ├── authenticated_actor
                    ├── tenant
                    ├── current_record (if present)
                    └── current_selection (if non-empty)
                    │
                    ▼
        FilamentInvocationContextFactory
                    │
                    ▼
        FilamentActionGateway
                    │
                    ▼
        existing ActionCall → ActionBus → Livewire execution
```

### `FilamentCurrentSelectionResolver`

Target namespace:

`SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver`

Responsibilities:

- hold the exact trusted active Filament Page;
- detect supported table capability through `HasTable`;
- use only public `getSelectedTableRecords()` for selection resolution;
- materialize at most the configured maximum plus one sentinel record;
- validate that every selected item is a persisted Eloquent Model;
- derive shared typed record identities;
- reject ambiguous duplicate identities;
- canonicalize the selection as a set;
- return exact selected model instances in deterministic canonical order;
- derive a non-secret stable `confirmationScopeKey`;
- return absence for a legitimate effective empty selection;
- fail closed with static-safe adapter errors for inconsistent/unsupported states.

The resolver must not:

- accept caller record IDs;
- read raw selection properties to reconstruct selection meaning;
- execute a replacement query from caller IDs;
- include active filters/search/sort/tab state in selection identity;
- silently truncate a selection that exceeds the configured ceiling;
- silently deduplicate ambiguous selected records;
- serialize model attributes into trusted metadata or provenance.

## Shared exact-record identity primitive

T-501 already derives exact persisted record identity for `current_record`. T-502 needs the same scalar-type and canonicalization rules for each selected record.

A focused shared primitive should therefore be extracted inside the Filament adapter boundary, for example:

`SurfaceRelay\Laravel\Filament\Context\FilamentRecordIdentity`

Its semantic output is the canonical identity tuple:

```text
{
  modelClass: exact PHP model class,
  keyName:    exact non-empty Eloquent primary-key name,
  keyValue:   exact persisted int|string primary-key value
}
```

Rules remain identical to T-501:

- model must be persisted (`exists === true`);
- key name must be a non-empty string;
- key value must be `int|string`;
- empty-string key is invalid;
- integer `0` is valid;
- null, float, bool, array, object or other key types are invalid;
- model attributes, timestamps, connection names, route keys and tenant data are excluded.

The extraction is allowed only if the existing T-501 `current_record` scope-key result remains byte-for-byte stable. Regression tests must pin that property.

## Selection value semantics

`ResolvedTrustedValue::value` for `current_selection` is a bounded `list<Model>` containing the exact Eloquent model instances returned by Filament.

The list order is canonicalized by exact record identity; it does not preserve click order, table display order or database query order.

This is intentional because `current_selection` is defined as an unordered set. Selecting records A then B is the same authority as selecting B then A.

Application code may rely on:

- the exact selected model objects;
- each model appearing exactly once;
- deterministic list order for the same identity set during one invocation.

Application code must not interpret list position as user intent.

The snapshot freezes **which exact identities are members of the selection** for the invocation. It does not freeze Eloquent attributes or prohibit the executor from mutating the exact model instances. Confirmation/idempotency scope is derived before execution and is not recomputed from mutable business attributes afterward.

## Stable selection identity

Each selected model first produces the shared canonical record identity representation.

Define:

```text
RECORD_IDENTITY_ENCODING = RuntimeScopeCanonicalizer::encode({
  modelClass,
  keyName,
  keyValue,
})
```

Canonical set construction:

1. encode each exact record identity;
2. reject duplicate encoded identities;
3. sort encoded identities lexicographically by exact encoded bytes;
4. build the ordered canonical identity list from that sorted set.

Selection scope key:

```text
DOMAIN = "surfacerelay.filament.current_selection.v1\n"

confirmationScopeKey = hex(
  SHA-256(
    DOMAIN + RuntimeScopeCanonicalizer::encode(sortedIdentitySet)
  )
)
```

Properties:

- same typed record set in different selection order → same scope key;
- one added or removed record → different scope key;
- integer key `7` and string key `"7"` remain distinct;
- different exact model classes remain distinct even with identical primary keys;
- raw primary keys do not enter provenance, audit metadata or generic invocation metadata.

The selection hash is an identity aid, never authorization proof.

## Tenant remains an independent trusted dimension

As with T-501, tenant identity must not be folded into each selected-record identity or the selection hash.

```text
selection identity = exact selected record set
Tenant identity    = separate `tenant` trusted entry
Combined scope     = existing D-044 / D-045 machinery binds both when present
```

If the same exact model identities exist under two tenant authorities, existing confirmation and idempotency scope must differ because the trusted tenant entry differs.

T-502 must not infer tenant authority from:

- model attributes;
- connection names;
- resource/page class;
- route prefixes;
- Filament panel state.

## Empty selection semantics

An exact supported table page produces trusted-context absence **only when the effective result of Filament's public `getSelectedTableRecords()` contract is empty**.

Raw Livewire selection properties are not an emptiness test. In particular, select-all mode may legitimately have an empty `$selectedTableRecords` array while the effective selected-record set is non-empty and represented through Filament's deselection tracking semantics.

No `current_selection` entry is added when the effective public selection result is empty.

Therefore:

```text
action requires current_selection
AND effective selected record count = 0
→ existing required_context_missing halt
```

This prevents actions such as "refund selected rows" from executing under a fabricated empty selection authority while still respecting Filament's select-all semantics.

An empty selection is not represented as an empty trusted list with a valid scope key.

## Bounded materialization

Selection resolution can imply database work, especially for Filament's select-all representation. T-501's zero-query requirement does not apply to T-502 because resolving the exact selection may legitimately require Filament's own table query.

T-502 introduces an explicit trusted configuration ceiling:

```text
DEFAULT_MAX_SELECTION_RECORDS = 500
```

The limit is adapter/runtime configuration, not caller input and not authorization.

Requirements:

- configured maximum must be a positive integer;
- resolver requests Filament's public selected-record stream with chunking/laziness where supported;
- it observes at most `max + 1` records for the decision;
- count `<= max` may materialize the bounded snapshot;
- observing record `max + 1` fails closed with `selection_limit_exceeded` semantics;
- the adapter never silently truncates to the first `max` records;
- the limit applies before confirmation/idempotency identity construction and before application execution;
- the bounded count is measured over the **effective selected records returned by Filament after Filament's own selectability/query semantics**, not over raw selection-key properties.

The initial default of 500 is a reference-runtime safety ceiling, not a protocol constant. It can be overridden only through trusted adapter construction/configuration.

## Supported table/record scope

T-502 initially supports exact selected persisted Eloquent model records whose identity can be represented unambiguously.

### Supported

- normal Eloquent-backed Filament tables;
- ordinary explicit selections;
- Filament select-all/deselection semantics resolved by the public API;
- selectable-record filtering resolved by Filament;
- lazy/chunked selected Eloquent models;
- different Eloquent model classes when a supported table legitimately returns them and identities remain unambiguous.

### Fail closed

- selected non-Eloquent values/arrays;
- unsaved Eloquent models;
- invalid primary-key identity;
- duplicate exact record identity in the resolved selection;
- relationship/table modes in which the same Eloquent identity can represent multiple distinct selectable rows, including duplicate-enabled `BelongsToMany` semantics;
- selection larger than the trusted configured ceiling;
- exceptions from Filament selection resolution;
- exceptions from model identity accessors.

Duplicate-enabled relation rows are not silently collapsed to one model identity. Supporting pivot-row identity would require a distinct explicit authority model and is out of T-502 scope.

For a table with no current effective selection, absence may be returned without manufacturing an error solely because an unsupported duplicate-row mode exists; unsupported identity semantics matter only when SurfaceRelay would otherwise issue non-empty selection authority.

## Public API and framework-private-state boundary

Allowed Filament interaction:

- exact Page supplied by trusted code;
- `instanceof HasTable` capability check;
- public `getSelectedTableRecords(...)`;
- public table relationship/configuration APIs only where required to reject ambiguous duplicate-row semantics.

Forbidden:

- `Livewire\invade()`;
- reflection to read protected/private Filament properties;
- private framework methods;
- direct mutation of Filament selection state;
- direct reconstruction from public Livewire state arrays;
- frontend DOM or Alpine state as server authority.

## Provenance

```text
provider  = "filament.current_selection"
reference = null
```

Provenance must not contain:

- record IDs;
- record count if it would broaden the persisted audit contract;
- table/resource/page names;
- filter state;
- selection scope key;
- route/query values.

T-404 audit may persist only the existing trusted-context manifest fact:

```text
{ requirement: "current_selection", provider: "filament.current_selection" }
```

It must not persist selected models, keys, selection hash or filter state.

## `FilamentTrustedContextComposer` evolution

The T-501 composer currently appends `current_record`. T-502 extends the same Filament adapter boundary rather than adding a second independent invocation composer.

The composer should accept/own both Filament resolvers and emit entries according to canonical `ContextRequirement::cases()` ordering.

When available:

```text
authenticated_actor
→ tenant
→ current_record
→ current_selection
```

No changes are required to the generic core `TrustedContextComposer` plugin model.

A Filament table page may legitimately have `current_selection` without `current_record`. A record page may have `current_record` without a table selection. The dimensions remain independent.

## `FilamentInvocationContextFactory` and gateway

The production path established by T-501 remains authoritative:

```text
exact Page
→ FilamentInvocationContextFactory
→ InvocationContext
→ normal ActionCall
→ FilamentActionGateway
→ existing ActionBus
→ existing Livewire RuntimeBinding execution
```

T-502 extends only trusted-context composition inside this path.

It does not:

- add another gateway;
- add a Filament RuntimeBinding driver;
- dispatch a Filament bulk action as the business endpoint;
- bypass ActionBus;
- introduce route/request discovery.

The gateway/factory may accept trusted selection configuration such as `maxSelectionRecords` through constructor wiring. It must not accept caller-controlled per-invocation selection IDs or selection limits.

## Caller-spoofing boundary

None of these can satisfy or change `current_selection` authority:

- action input fields such as `ids`, `recordIds`, `selection`, `selected`, `selectedTableRecords`;
- `InvocationContext::metadata`;
- query or route parameters;
- `bindingId`;
- confirmation receipt;
- idempotency key;
- WebMCP tool arguments;
- caller-provided count/hash values.

Only the exact Page and Filament's public current-selection resolution API provide selection authority.

## Interaction with confirmation — D-044

`current_selection` already participates in the relevant trusted-context dimensions used by confirmation scope.

T-502 supplies a stable trusted scope key, so confirmation binds the exact selected set.

Required proofs:

- selection `{A,B}` and `{B,A}` produce the same confirmation scope;
- selection `{A,B}` and `{A,C}` produce different confirmation scopes;
- receipt issued for `{A,B}` cannot authorize `{A,C}`;
- wrong-selection mismatch does not spend the otherwise-valid `{A,B}` receipt;
- caller IDs cannot recreate selection authority.

## Interaction with idempotency — D-045

The idempotency intent fingerprint already includes present `current_selection` trusted context.

Required proofs:

- same typed selected set in different click/order resolution produces the same intent fingerprint;
- changed selected set changes intent fingerprint;
- tenant remains a separate dimension and changes overall intent where present;
- raw selection IDs and selection scope key are not persisted as idempotency lookup material beyond the existing trusted canonical fingerprint mechanics.

## Interaction with audit — D-047

Audit records only trusted-context manifest facts.

Required proof:

- completed/halted invocations may show `current_selection / filament.current_selection` in the manifest;
- no selected record model, primary key, model class, selection hash, selection count, table state or active-filter state appears in semantic audit persistence.

## Selection snapshot freshness

`current_selection` is resolved once per SurfaceRelay invocation while building trusted context before ActionBus dispatch.

The snapshot's **membership and exact identity set** remain fixed for that invocation even if the human UI changes selection immediately afterward. The Eloquent model instances in the snapshot remain normal mutable application objects; T-502 does not copy/freeze their attributes.

A later invocation resolves a fresh selection snapshot.

SurfaceRelay does not attempt to keep a long-lived server object synchronized with subsequent Filament selection changes.

Consequences:

- confirmation issued for one selection remains bound to that selection scope;
- if the user changes selection before confirmed retry, current trusted scope differs and the old receipt cannot authorize the new selection;
- idempotency intent likewise tracks the current invocation snapshot;
- no stale selection is silently rebound to a new table state;
- changes to mutable model attributes after snapshot construction do not rewrite the already-derived selection identity for that invocation.

## T-503 separation — active filters are not selection authority

T-502 deliberately does not serialize or expose:

- active filters;
- search query;
- sort state;
- selected tab;
- grouping;
- pagination cursor/page.

Filament may internally use some of these states when resolving what select-all currently means. SurfaceRelay accepts the exact selected records returned by Filament, but it does not copy the filter descriptor into `current_selection`.

Conceptually:

```text
current_selection = WHICH exact records are selected now
active_filters    = WHICH table-view constraints are active now
```

If active filters change but Filament still resolves the **same exact effective selected record set**, the T-502 selection scope key intentionally remains the same. T-503, not T-502, is responsible for representing the changed filter authority/state. If the filter change alters the effective selected record set, the T-502 scope key changes because the record set changed.

T-503 owns the filter-state concern.

## Failure model

T-502 introduces Filament adapter failures that are static-safe and non-chained.

Representative internal failure categories:

- `invalid_selection_configuration`;
- `selection_resolution_failed`;
- `selection_limit_exceeded`;
- `unsupported_selection_value`;
- `invalid_selection_record_identity`;
- `duplicate_selection_identity`;
- `ambiguous_duplicate_row_selection`.

Exact public/runtime mapping may use the existing static-safe runtime failure path; T-502 does not expand `spec/0.1` ActionError vocabulary.

Errors must not expose:

- raw selected keys;
- SQL text/bindings;
- model attributes;
- table/resource names when unnecessary;
- underlying Filament/Eloquent exception messages;
- selection scope hashes.

No partial selection authority is produced on failure.

## Testing strategy

### Shared record identity regression

Prove extraction of the common identity primitive does not change T-501 behavior:

- existing current-record scope hash remains byte-for-byte stable;
- int/string key distinction remains preserved;
- zero key remains valid;
- invalid identity failures remain static-safe.

### Unit selection resolver

Cover:

- non-table Page → absence;
- table with effective zero selection → absence;
- explicit selected Eloquent records → exact model snapshot;
- canonical selection ordering independent of source order;
- same set produces same selection scope key;
- changed set changes scope key;
- duplicate identity fails closed;
- non-Eloquent selected item fails closed;
- unsaved/invalid-key selected model fails closed;
- limit boundary: exactly max succeeds, max+1 fails;
- configured max must be positive;
- resolver/identity exception text is sanitized.

### Real Filament 5 integration

Use real Filament `HasTable` behavior and Testbench to prove:

- explicit table selections resolve through `getSelectedTableRecords()`;
- select-all plus deselected exceptions produce the exact effective selection Filament reports;
- a raw empty `$selectedTableRecords` value does **not** imply trusted absence when Filament's effective select-all result is non-empty;
- selectable-record constraints are respected by the public API before SurfaceRelay applies its own selection ceiling;
- SurfaceRelay does not read raw selection properties as authority;
- the bounded resolver does not consume more than the allowed decision window when a LazyCollection path is available.

### Trust-control integration

Prove:

- ActionDefinition requiring `current_selection` rejects an effective empty selection before execution;
- exact selected model list reaches application executor through `FilamentActionGateway → ActionBus`;
- caller input/metadata spoofing does not substitute selection;
- selection-order independence for confirmation/idempotency;
- changed selection invalidates a prior confirmation scope without spending the original receipt;
- T-404 audit records only requirement/provider manifest facts.

### Regression matrix

Retain the existing matrix:

- PHP 8.3 + Illuminate 12 + Testbench 10;
- PHP 8.4 + Illuminate 12 + Testbench 10;
- PHP 8.3 + Illuminate 13 + Testbench 11;
- PHP 8.4 + Illuminate 13 + Testbench 11;
- Filament `^5.0` + Livewire `^4.4`;
- MySQL 8.4 service coverage;
- browser typecheck + 103/103 Vitest tests;
- contract validator green.

No matrix row is removed to make T-502 pass.

## Security invariants

1. Caller IDs never become `current_selection` authority.
2. Exact active Page remains the trusted UI source.
3. Filament's public selected-record contract, not raw Livewire properties, defines effective selection.
4. Empty selection means the effective public selected-record result is empty; raw selection-key arrays are not an emptiness authority.
5. Empty effective selection is absence, not an empty authority token.
6. Selection is bounded before application execution.
7. Over-limit selection fails rather than truncating.
8. Selection is an unordered exact record-identity set.
9. Duplicate exact identity fails rather than silently deduplicating.
10. Ambiguous duplicate-row relationship semantics fail closed.
11. Tenant remains independent of selection identity.
12. Filter state is not smuggled into `current_selection`.
13. Confirmation/idempotency bind the exact bounded membership/identity snapshot.
14. Audit persists no selected-record material.
15. No new Filament execution driver/path is introduced.
16. Framework/model exceptions are static-safe and non-chained.
17. T-501 current-record scope identity remains byte-for-byte compatible.
18. Snapshot immutability applies to selection membership/identity, not to mutable Eloquent business attributes.

## Non-goals

T-502 does not:

- implement T-503 active filters;
- implement T-504 confirmation UI;
- implement T-505 order demo;
- support arbitrary non-Eloquent table records;
- support duplicate pivot-row selection identity;
- make selection unbounded;
- make table display order part of authority;
- define a new protocol field/schema;
- add a Filament RuntimeBinding driver;
- infer tenant from selected models;
- use caller-provided selection IDs as a shortcut;
- freeze/copy Eloquent model attributes or prevent normal application mutations.

## Acceptance summary

T-502 is complete when the Laravel/Filament reference runtime can truthfully guarantee:

> For one exact trusted Filament table page and one SurfaceRelay invocation, `current_selection` is either absent because Filament's public table contract resolves no effective selected records, or is a bounded fail-closed snapshot of the exact persisted Eloquent records that contract currently considers selected. The snapshot freezes an unordered exact membership/identity set for the invocation while preserving the exact normal Eloquent model instances for application use; it is caller-unforgeable, binds existing confirmation/idempotency controls, leaks no selection material into audit, and reaches application execution through the existing Filament gateway / ActionBus / Livewire path without introducing a second driver or execution endpoint.
