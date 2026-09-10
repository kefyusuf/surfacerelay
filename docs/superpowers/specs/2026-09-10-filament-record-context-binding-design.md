# T-501 — Filament Record Context Binding Design

## Status

Approved design for M5 / T-501.

- Base: `main@66afcc22704bfe3b317f2894b7737cf18d248e34`
- Target vertical: Filament 5.x on the existing Laravel 12/13 + Livewire 4 runtime
- Protocol impact: none; `spec/0.1/**` remains frozen
- Execution-driver impact: none; Filament does not introduce a second RuntimeBinding driver

## Objective

T-501 provides the first production-oriented Filament trusted-context adapter. Its only new authority is `current_record`: the exact Eloquent model already owned by the active Filament record page.

Filament is not treated as a separate execution framework. Filament resource pages are Livewire-backed surfaces, so action execution continues through the existing exact `livewire` RuntimeBinding path. The Filament layer contributes runtime-authoritative page context only.

```text
Action Definition  = WHAT can be done
Runtime Binding    = HOW the existing Livewire target executes it
Trusted Runtime    = WHICH active Filament record is authoritative now
Surface Projection = HOW the surface exposes the action
```

## Locked decisions

### D-019 promotion

D-019 is promoted from `PROPOSED` to `ACCEPTED`: Filament is the first production-oriented reference vertical.

### D-048

Filament is a trusted-context/UI adapter layered on the existing Livewire execution binding. `current_record` comes only from the exact active record-aware Filament page through supported public Filament APIs; it is never reconstructed from caller input, route/query parameters, generic invocation metadata, or a second model lookup.

## No Filament RuntimeBinding driver

A new `filament` binding driver would duplicate the runtime target already represented by the mounted Livewire component and create a second execution path for the same business action. That would weaken D-022/D-025 and D-034 by allowing the Filament adapter and Livewire driver to disagree about exact target identity or lifecycle.

T-501 therefore does not:

- create a `filament` driver identifier;
- issue Filament-specific RuntimeBindings;
- invoke Filament actions directly;
- create an agent-only Filament endpoint;
- bypass the existing Livewire browser driver;
- resolve a replacement page/component/record when an earlier target is stale.

The existing Livewire binding remains the execution reference. Filament contributes trusted record context before ActionBus dispatch.

## Supported Filament source

The trusted source is an exact active Filament resource page instance supplied by trusted adapter/application code.

The resolver accepts that concrete page object at construction time. It does not discover a page through route names, request globals, container scans, Livewire component-name searches, DOM state, or heuristic lookup.

Record capability is recognized only through Filament's public resource-page API surface:

1. the object is a Filament resource page;
2. it exposes a callable public `getRecord()` method;
3. `getRecord()` returns an `Illuminate\\Database\\Eloquent\\Model` instance.

Filament 5's official resource documentation uses public `getRecord()` on record pages. SurfaceRelay may rely on the page-owned record state that Filament established during its own lifecycle, but SurfaceRelay must not independently call `resolveRecord()` or repeat route-model binding from caller/request values.

A normal Filament resource page that is not record-aware is legitimate absence and produces no `current_record` entry.

## Components

```text
exact active Filament resource page
        │
        ▼
FilamentRecordContextResolver
        │
        ├── public getRecord()
        ├── exact Eloquent Model instance
        ├── ContextProvenance("filament.current_record")
        └── stable confirmationScopeKey
                    │
                    ▼
ResolvedTrustedValue
                    │
                    ▼
FilamentTrustedContextComposer
        │
        ├── existing actor entries
        ├── existing tenant entries
        └── current_record
                    │
                    ▼
InvocationContext
                    │
                    ▼
existing ActionBus + Livewire binding/execution
```

### `FilamentRecordContextResolver`

Target namespace:

`SurfaceRelay\\Laravel\\Filament\\Context\\FilamentRecordContextResolver`

Responsibilities:

- hold the exact trusted active page instance;
- determine whether the page has supported record capability;
- call public `getRecord()` only;
- return `null` for a legitimately non-record-aware page;
- return `ResolvedTrustedValue` for a valid active Eloquent record;
- derive stable record identity for confirmation/idempotency scope;
- provide non-secret provenance;
- fail closed on inconsistent record-aware state.

The constructor accepts `object`, not caller record identifiers. The implementation should avoid making Filament a mandatory dependency for loading unrelated core classes; Filament-specific type checks stay inside the optional adapter boundary.

The resolver must not:

- accept record ID/model class from action input;
- accept route/query/request parameters;
- call `resolveRecord()` using caller-controlled values;
- query the database for a replacement record;
- silently convert malformed record-aware state to ordinary absence;
- serialize the model or copy its attributes into metadata.

### `FilamentTrustedContextComposer`

Target namespace:

`SurfaceRelay\\Laravel\\Filament\\Context\\FilamentTrustedContextComposer`

The existing `TrustedContextComposer` remains source-compatible and continues to own actor + tenant composition.

The Filament composer delegates to that existing composer and adds the record entry when available. T-501 does not refactor core trusted-context composition into a generic plugin registry.

Canonical `InvocationContext` ordering remains governed by `ContextRequirement::cases()`:

```text
authenticated_actor
→ tenant
→ current_record
```

when all are present.

This narrow wrapper leaves T-502 free to add `current_selection` without destabilizing actor/tenant behavior.

## Trusted record value

`ResolvedTrustedValue::value` is the exact Eloquent model object returned by the active page.

SurfaceRelay does not convert it to:

- model attributes;
- a resource payload;
- `{model, id}` caller data;
- serialized JSON;
- a cloned/reloaded model.

Application authorization/execution may need the real model instance, and re-resolving by ID would introduce a second authority path and stale/retarget races.

## Stable record identity

The generic runtime intentionally cannot serialize arbitrary model objects for confirmation/idempotency scope. The Filament resolver therefore supplies a trusted `confirmationScopeKey`.

```text
DOMAIN = "surfacerelay.filament.current_record.v1\n"

identity = {
  "modelClass": exact PHP model class,
  "keyName":    Eloquent primary-key name,
  "keyValue":   exact persisted primary-key value
}

confirmationScopeKey = hex(
  SHA-256(DOMAIN + RuntimeScopeCanonicalizer::encode(identity))
)
```

Rules:

- model class is the exact PHP class, not morph-map alias or Filament resource class;
- key name is a non-empty string;
- key value is `int|string`;
- empty-string key value is invalid;
- integer `0` is valid and must not be lost through truthiness;
- unsaved models (`exists=false`) are invalid;
- `getKey() === null` is invalid;
- no business attributes, timestamps, route values, user identity, or tenant identity participate in the record key;
- raw primary-key value is not copied to provenance, audit metadata, or generic invocation metadata.

The scope key is an identity aid only. It grants no authorization.

### Tenant separation is a separate trusted dimension

T-501 must **not** smuggle tenant identity into the current-record hash. In database-per-tenant or other partitioned systems, the same model class and primary key may legitimately exist under different tenants. The existing confirmation and idempotency scope machinery already combines present trusted dimensions, including `tenant` and `current_record`.

Therefore:

```text
record identity = model class + primary-key identity
Tenant identity = separate ContextRequirement::Tenant entry
Combined trust scope = existing runtime combines both when present
```

If tenancy matters to an action, the runtime/application must provide and require trusted tenant context. Filament record resolution does not manufacture tenant authority from model attributes, connection names, route prefixes, resource classes, or global Filament state.

Integration tests should prove that identical record identity under different trusted tenants yields different overall confirmation/idempotency scope when tenant context is present.

### Logical-record identity non-claim

The generic identity represents the application's logical Eloquent record (`class + primary key`), not a cryptographic database-row incarnation. Applications that intentionally reuse a primary key for a semantically different entity within an active idempotency window must supply stronger application-level identity semantics in a future explicit adapter extension; T-501 does not guess from mutable business attributes such as `created_at`.

## Provenance

```text
provider  = "filament.current_record"
reference = null
```

Primary key, resource name, URL, route key, page class and model attributes are not placed into provenance.

T-404 may record that `current_record` came from `filament.current_record`, but never persists the model value, raw key, identity hash/scope key, route or provenance reference.

## Absence vs invariant failure

### Legitimate absence

A Filament resource page without record capability returns `null` from the resolver. No current-record trusted entry is created.

If an ActionDefinition requires `current_record`, the existing ActionBus context-requirement gate rejects before validation/execution.

### Inconsistent record-aware state

If a page exposes callable `getRecord()` but record state is unusable, the adapter fails closed with an internal static-safe Filament context exception.

Examples:

- `getRecord()` returns `null`;
- result is not an Eloquent Model;
- model is unsaved;
- key name is invalid;
- key is null/empty/unsupported;
- `getRecord()` throws.

These are not silently converted to ordinary absence. Error messages must not contain record keys, model attributes, SQL diagnostics, route parameters, or underlying exception messages. Public/runtime-facing exception chaining is unnecessary.

## Caller-spoofing boundary

The following never influence current-record resolution:

- action input keys such as `record`, `record_id`, `recordId`, `id`, `model`;
- `InvocationContext::metadata`;
- SurfaceRelay query/route parameters;
- `bindingId`;
- confirmation receipt;
- idempotency key;
- WebMCP tool arguments.

Only the exact active Filament page instance supplied by trusted adapter code and its public page-owned record API provide authority.

## Interaction with existing trust controls

### Context requirement gate

An action declaring `current_record` executes only when the adapter produced a trusted entry. Caller input cannot satisfy it.

### Authorization

T-501 does not change ActionAuthorizer behavior. Application authorization may inspect the exact current-record trusted value.

### Confirmation

Existing T-401 confirmation scope consumes the resolver-supplied `confirmationScopeKey`. Record A and record B therefore produce different scopes. Binding/surface authority remains independently represented by the existing confirmation model.

### Idempotency

Existing T-402 intent fingerprint includes current-record identity. Different records differ; equivalent instances of the same logical record remain stable. Tenant remains an independent trusted dimension as described above.

### Output policy

No change.

### Structured audit

T-404 persists only `{requirement, provider}` for trusted context. The record object, raw key, scope key, tenant value, route and page/resource details remain excluded.

## Dependency policy

Filament remains optional for consumers of `surfacerelay/laravel`.

T-501 adds Filament only as a development/test dependency:

```json
"require-dev": {
  "filament/filament": "^5.0"
}
```

The production package stays installable without Filament.

The base `SurfaceRelayServiceProvider` does not eagerly instantiate/register Filament services when Filament is absent. Optional adapter code is only relevant when consumers use the Filament namespace.

A Composer `suggest` entry may be added if useful, but no production `require` on `filament/filament` is introduced.

## Filament API compatibility target

T-501 targets Filament 5.x public APIs only.

Official Filament 5 documentation currently requires PHP 8.2+ and Laravel 11.28+, installs `filament/filament:^5.0`, and documents record-page use of public `getRecord()`. Filament v5 is the Livewire v4 generation.

References:

- https://filamentphp.com/docs/5.x/introduction/installation
- https://filamentphp.com/docs/5.x/resources/editing-records
- https://filamentphp.com/docs/5.x/resources/viewing-records
- https://filamentphp.com/docs/5.x/resources/custom-pages

SurfaceRelay does not inspect protected `$record` state, Filament private properties, internal Livewire snapshots, private request payloads, or undocumented component internals.

If supported public APIs are unavailable in a claimed matrix combination, stop and redesign the compatibility policy instead of adding reflection/private-state fallbacks.

## Expected production files

```text
packages/laravel/src/Filament/Context/
├── FilamentRecordContextResolver.php
├── FilamentTrustedContextComposer.php
└── InvalidFilamentRecordContext.php
```

A small identity helper may be extracted only if TDD shows the resolver would otherwise mix unrelated responsibilities. Do not add abstractions pre-emptively.

Expected modified file:

```text
packages/laravel/composer.json
```

No changes are expected under:

```text
spec/0.1/**
packages/browser-runtime/**
packages/laravel/src/Livewire/**
```

unless implementation evidence reveals a real incompatibility requiring a new design gate.

## Testing strategy

### Record-resolution unit tests

Prove:

1. supported record-aware page returns `ResolvedTrustedValue` with the exact same Eloquent object;
2. provider is exactly `filament.current_record`; reference is null;
3. non-record-aware resource page returns null;
4. callable `getRecord()` returning null fails closed;
5. non-Model return fails closed;
6. unsaved model fails closed;
7. null/unsupported/empty identity components fail closed;
8. integer `0` key is handled explicitly;
9. underlying `getRecord()` exception is replaced by a static safe failure without message leakage;
10. action input/metadata/request values are not resolver inputs.

### Identity unit tests

Prove:

1. same class + key name + typed key => same scope key;
2. different key => different scope key;
3. different model class with same key => different scope key;
4. integer `123` and string `"123"` remain distinct under canonical encoding;
5. model attribute changes do not affect record identity;
6. raw key never appears in provenance or errors.

### Composer tests

Prove:

1. existing actor + tenant composition remains unchanged;
2. current record is added when available;
3. non-record page adds no record entry;
4. InvocationContext ordering remains canonical;
5. non-authoritative metadata cannot override or manufacture current record.

### Trust-control integration

Using existing real hashers/stages, prove:

1. confirmation scope changes when record changes;
2. confirmation for record A cannot authorize record B;
3. idempotency intent differs for record A vs B;
4. same logical record identity stays stable across distinct Eloquent object instances;
5. same logical record under tenant A vs tenant B yields distinct overall scope when trusted tenant context is present;
6. T-404 sees provider presence only, never model key/attributes/scope key/tenant value.

### Real Filament/Testbench integration

Create a minimal test Eloquent model/resource/record-page fixture and exercise a real Filament 5 page through Orchestra Testbench enough to prove public `getRecord()` exposes the active model to the adapter.

This prevents T-501 from passing only against a hand-written duck type.

No browser behavior is added by T-501, so no new browser feature test is required. Existing browser typecheck/Vitest remains a regression gate.

### Full regression matrix

Retain:

- PHP 8.3 / 8.4;
- Illuminate 12 / 13;
- Orchestra Testbench 10 / 11;
- MySQL 8.4 service checks;
- existing Livewire 4 tests;
- browser TypeScript + Vitest;
- contract validator;
- frozen `spec/0.1/**` no-diff.

Filament 5 dependency resolution must succeed across the current supported PHP/Illuminate matrix. If Filament proves incompatible with one combination, stop and redesign CI/dependency policy rather than silently deleting an existing Laravel support cell.

## Security invariants

1. Caller/request data never chooses current record.
2. SurfaceRelay performs no second model query/route-model binding.
3. Exact active Filament page is the adapter source of record authority.
4. Exact Eloquent model object is retained as trusted value.
5. Generic runtime code does not serialize the model object.
6. Stable record identity uses model class + primary-key identity only and is domain-separated + hashed before becoming a scope key.
7. Tenant remains a separate trusted context dimension and is never inferred from record data.
8. Record scope key is not authorization and is not persisted by T-404.
9. Record/tenant identity changes affect confirmation/idempotency equivalence through existing trust-control composition.
10. Inconsistent record-aware state fails closed rather than becoming absence.
11. Filament private/internal state is never read.
12. Filament does not introduce a second execution driver/path.
13. `spec/0.1/**` remains unchanged.

## Out of scope

T-501 does not implement:

- `current_selection` — T-502;
- active filter context — T-503;
- human confirmation UI/bridge — T-504;
- multi-tenant order operations demo — T-505;
- Filament table/bulk-action execution adapters;
- a Filament browser driver;
- a new RuntimeBinding schema/driver;
- automatic page discovery from request/route globals;
- business-history/audit payload storage;
- model serialization;
- arbitrary record lookup by ID;
- Filament 4 compatibility;
- generic framework-neutral `CurrentRecordResolver` refactor;
- stronger application-specific row-incarnation identity;
- browser-runtime or public wire-spec changes.

## Acceptance criteria

T-501 is complete only when:

1. D-019 is `ACCEPTED`.
2. D-048 records Filament trusted context over existing Livewire execution.
3. A real active Filament record page yields exact current-record trusted context through public `getRecord()`.
4. Non-record pages produce absence; inconsistent record-aware pages fail closed.
5. Caller/request/metadata values cannot manufacture or retarget record authority.
6. Exact Eloquent model instance is preserved as trusted value.
7. Stable record identity is deterministic, domain-separated, hashed, and excludes business attributes/tenant inference.
8. Confirmation distinguishes records and tenants correctly through existing trusted-context composition.
9. Idempotency distinguishes records and tenants correctly through existing trusted-context composition.
10. Structured audit persists provider presence only, never record/tenant identity values or scope keys.
11. Existing `TrustedContextComposer` remains source-compatible.
12. No new Filament RuntimeBinding driver or duplicate execution path exists.
13. Filament remains optional to package consumers.
14. Current PHP/Illuminate/Testbench matrix remains supported with Filament 5.
15. Existing Laravel/browser regressions remain green.
16. `spec/0.1/**` remains unchanged.

## Implementation sequencing constraint

After written-spec approval, implementation planning proceeds TDD-first:

1. dependency + real-Filament compatibility proof;
2. resolver + safe failure semantics;
3. stable identity projection;
4. Filament trusted-context composer;
5. confirmation/idempotency/audit integration proofs;
6. full regression and external-review preparation.

No T-502 work begins until T-501 is merged and `main` is revalidated.
