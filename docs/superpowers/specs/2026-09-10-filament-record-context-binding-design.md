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

This preserves SurfaceRelay's four-way separation:

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

## Why Filament does not get a new RuntimeBinding driver

A new `filament` binding driver would duplicate the runtime target already represented by the mounted Livewire component and would create a second execution path for the same business action. That would weaken D-022/D-025 and D-034 by making it possible for the Filament adapter and Livewire driver to disagree about exact target identity or lifecycle.

T-501 therefore does not:

- create a `filament` driver identifier;
- issue Filament-specific RuntimeBindings;
- invoke Filament actions directly;
- create an agent-only Filament endpoint;
- bypass the existing Livewire browser driver;
- resolve a replacement page/component/record when the original target is stale.

The existing Livewire binding remains the exact execution reference. Filament contributes trusted record context before ActionBus dispatch.

## Supported Filament surface

The trusted source is an exact active Filament resource page instance supplied by trusted adapter code.

The resolver accepts a concrete page object at construction time. It does not discover a page through route names, request globals, container scans, Livewire component-name searches, DOM state, or other heuristic lookup.

The resolver recognizes record capability only through Filament's public resource-page API surface:

1. the object must be a Filament resource page;
2. it must expose a callable public `getRecord()` method;
3. `getRecord()` must return an `Illuminate\\Database\\Eloquent\\Model` instance.

Filament's documented Edit/View resource pages expose their active record through `getRecord()`. Record-aware custom resource pages that use Filament's supported record interaction API are eligible under the same rule.

A normal resource page that is not record-aware is not an error; it simply produces no `current_record` entry.

## Components

```text
exact active Filament resource page
        │
        ▼
FilamentRecordContextResolver
        │
        ├── getRecord() via public Filament API
        ├── exact Eloquent Model instance
        ├── ContextProvenance("filament.current_record")
        └── stable non-secret confirmationScopeKey
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

Namespace target:

`SurfaceRelay\\Laravel\\Filament\\Context\\FilamentRecordContextResolver`

Responsibilities:

- hold the exact trusted active Filament page instance;
- determine whether that page has supported record capability;
- call public `getRecord()` only;
- return `null` when the page is legitimately non-record-aware;
- return `ResolvedTrustedValue` for a valid active Eloquent record;
- generate stable record identity for confirmation/idempotency scope;
- provide non-secret provenance;
- fail closed on an inconsistent record-aware page.

It must not:

- accept record ID/model class from action input;
- accept route/query/request parameters;
- call `resolveRecord()` using caller-controlled values;
- query the database for a replacement record;
- silently convert a malformed record-aware page to context absence;
- serialize the model or copy its attributes into trusted metadata.

### `FilamentTrustedContextComposer`

Namespace target:

`SurfaceRelay\\Laravel\\Filament\\Context\\FilamentTrustedContextComposer`

The existing `TrustedContextComposer` remains source-compatible and continues to own actor + tenant composition.

The Filament composer wraps/delegates to that existing composer and appends the Filament record entry when present. It does not refactor the core composer into a generic plugin container in T-501.

Expected canonical `InvocationContext` order remains governed by `ContextRequirement::cases()`, so adding the entry through trusted runtime code produces:

```text
authenticated_actor
→ tenant
→ current_record
```

when all three are available.

This narrow wrapper leaves T-502 free to add `current_selection` without destabilizing actor/tenant behavior now.

## Trusted record value

`ResolvedTrustedValue::value` is the exact Eloquent model object returned by the active Filament page.

SurfaceRelay does not turn the record into:

- an array of attributes;
- a resource payload;
- `{model, id}` caller data;
- serialized JSON;
- a cloned/reloaded model.

This is deliberate. Application authorization/execution may need the actual domain model instance, and re-resolving by ID would introduce a second authority path and a stale/retarget race.

## Stable record identity

The generic runtime intentionally cannot serialize arbitrary objects for confirmation/idempotency scope. Therefore the Filament resolver supplies a trusted `confirmationScopeKey`.

The key is a domain-separated SHA-256 digest over canonical record identity, not over business attributes:

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

- model class is the exact PHP class, not morph-map alias or resource class;
- key name must be a non-empty string;
- key value must be a persisted scalar identity supported by Eloquent for this adapter (`int|string`);
- an empty string key is invalid;
- integer `0` is representable and must not be rejected by truthiness checks;
- unsaved models (`exists=false`) are invalid;
- `getKey() === null` is invalid;
- no record attributes, tenant IDs, user IDs, route values, timestamps, or model serialization participate in the key;
- the raw key value is not copied to provenance, audit metadata, or generic invocation metadata.

The hash is an identity aid only. It does not itself authorize execution.

Because T-401 confirmation scope and T-402 idempotency intent already consume trusted context identities through the shared runtime scope canonicalizer, T-501 does not change their algorithms. It supplies an adapter-owned stable identity that makes the exact model object safely representable to those existing trust controls.

## Provenance

The entry uses:

```text
provider  = "filament.current_record"
reference = null
```

The primary key, resource name, URL, route key, page class and model attributes are not placed into provenance.

This keeps T-404 audit behavior payload-minimized: audit may record that `current_record` came from `filament.current_record`, but it must not persist the model value, raw key, hash/scope key, route or provenance reference.

## Absence vs invariant failure

The adapter distinguishes two cases.

### Legitimate absence

A Filament resource page that is not record-aware produces `null` from the resolver. No `current_record` trusted entry is created.

If an ActionDefinition requires `current_record`, the existing ActionBus context-requirement gate rejects before validation/execution.

### Inconsistent record-aware page

If a page advertises/contains callable `getRecord()` but the returned state is unusable, the adapter fails closed with an internal static-safe Filament context exception.

Examples:

- `getRecord()` returns `null`;
- result is not an Eloquent Model;
- model is unsaved;
- model key name is empty/invalid;
- model key is null or an unsupported type;
- `getRecord()` throws an underlying exception.

The adapter must not silently reinterpret those cases as ordinary absence, because that would hide a runtime invariant violation. Error messages must not contain the record key, model attributes, SQL diagnostics, route parameters or the underlying exception message. Exception chaining is not needed for the public/runtime-facing failure type.

## Caller-spoofing boundary

The following inputs never influence `current_record` resolution:

- action input keys named `record`, `record_id`, `recordId`, `id`, `model`, etc.;
- `InvocationContext::metadata`;
- query string values;
- route parameters supplied to SurfaceRelay invocation;
- `bindingId`;
- confirmation receipt;
- idempotency key;
- WebMCP tool arguments.

The only record authority is the exact active Filament page instance supplied by trusted adapter code and the model object returned by that page's public record API.

## Interaction with existing trust controls

### Context requirement gate

An action declaring `current_record` executes only when the Filament adapter has produced a trusted entry. Caller input cannot satisfy the requirement.

### Authorization

T-501 does not change ActionAuthorizer behavior. Authorization can read the exact `current_record` trusted entry and evaluate application policy as required by later vertical code.

### Confirmation

For consequential actions, confirmation scope binds the exact stable Filament record identity through the existing `confirmationScopeKey` mechanism. Changing the active record changes the scope and invalidates a receipt created for another record.

### Idempotency

The existing idempotency intent fingerprint includes `current_record`. Two otherwise identical invocations on different Filament records produce different intent fingerprints. Same record identity remains stable across equivalent model instances/pages.

### Output policy

No change.

### Structured audit

T-404 records only `{requirement, provider}` for trusted context. The record object, model key, identity hash, route and resource/page details remain excluded.

## Dependency policy

Filament is optional for consumers of the Laravel core package.

T-501 will add Filament 5 only as a development/test dependency:

```json
"require-dev": {
  "filament/filament": "^5.0"
}
```

The production package must remain installable without Filament.

Any production Filament adapter classes will reference Filament types only when those classes are actually autoloaded/used. The base `SurfaceRelayServiceProvider` must not eagerly instantiate or register Filament services when Filament is absent.

No hard production `require` on `filament/filament` is introduced by T-501.

## Filament API compatibility target

T-501 targets Filament 5.x public APIs only.

The design is verified against current official Filament 5 documentation, which shows record resource pages using public `getRecord()` and Filament 5's Livewire 4 integration. SurfaceRelay will not inspect Filament private properties, internal Livewire snapshots, protected `$record` state, private request payloads or undocumented component internals.

If Filament 5.x does not provide the expected public API in the test matrix, T-501 fails closed rather than adding reflection/private-state fallbacks.

## Production file plan

Expected new files:

```text
packages/laravel/src/Filament/Context/
├── FilamentRecordContextResolver.php
├── FilamentTrustedContextComposer.php
└── InvalidFilamentRecordContext.php
```

Potential shared helper only if tests prove it is useful:

```text
packages/laravel/src/Filament/Context/FilamentRecordIdentity.php
```

Do not create the helper pre-emptively if the resolver remains cohesive.

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

unless implementation evidence exposes a true incompatibility that requires a new design gate.

## Testing strategy

### Unit tests — record resolution

Prove:

1. a supported record-aware Filament page returns `ResolvedTrustedValue` containing the exact same Eloquent model object;
2. provider is exactly `filament.current_record` and provenance reference is null;
3. a non-record-aware Filament resource page returns null;
4. callable `getRecord()` returning null fails closed;
5. non-Model return fails closed;
6. unsaved Model fails closed;
7. null/unsupported/empty identity components fail closed;
8. integer `0` key is handled explicitly rather than lost through truthiness;
9. `getRecord()` exception is replaced by a static safe adapter failure with no sensitive message propagation.

### Unit tests — identity

Prove:

1. same model class + key name + typed key value => same scope key;
2. different key => different scope key;
3. different model class with same key => different scope key;
4. integer `123` and string `"123"` remain distinct if canonical encoding distinguishes them;
5. model attribute changes do not affect the identity key;
6. no raw model key appears in provenance or exception messages.

### Composer tests

Prove:

1. existing actor + tenant composition remains unchanged;
2. current record is appended when available;
3. no record entry is added for a non-record page;
4. final InvocationContext ordering remains canonical;
5. caller metadata cannot override or duplicate `current_record`.

### Trust-control integration

Use real existing hashers/pipeline state to prove:

1. confirmation scope changes when current record changes;
2. a receipt scoped to record A cannot grant authority for record B;
3. idempotency intent differs for record A vs B;
4. exact same persisted record identity remains stable across distinct Eloquent object instances;
5. T-404 structured audit sees `current_record` provider only and never model key/attributes/scope key.

### Real Filament/Testbench integration

Create a minimal test Eloquent model/resource/page fixture and exercise a real Filament 5 record page through Orchestra Testbench enough to prove the adapter receives a page whose public `getRecord()` returns the active model.

This test is the compatibility proof that T-501 is not merely passing against a hand-written object with a `getRecord()` method.

No browser test is required for T-501 because execution remains on the already-tested Livewire browser driver and this task is server-side trusted-context resolution.

### Full regression matrix

Retain current repository verification:

- PHP 8.3 / 8.4;
- Illuminate 12 / 13;
- Orchestra Testbench 10 / 11;
- MySQL 8.4 service checks;
- existing Livewire 4 tests;
- browser TypeScript + Vitest isolation;
- contract validator;
- frozen `spec/0.1/**` no-diff assertion.

Filament 5 dependency resolution must succeed in the existing PHP/Illuminate matrix. If Filament's supported matrix proves narrower than SurfaceRelay's current matrix, stop and redesign CI/dependency policy rather than silently dropping an existing Laravel combination.

## Security invariants

1. Caller/request data never chooses `current_record`.
2. No model re-query or route-model-binding lookup is performed by SurfaceRelay.
3. The exact active Filament page is the only adapter source of record authority.
4. The exact Eloquent model object is retained for authorization/execution context.
5. Generic runtime code does not serialize the model object.
6. Stable record identity contains only model class + primary-key identity and is domain-separated + hashed before becoming a scope key.
7. The hash is not authorization and is not persisted by T-404.
8. Record identity changes invalidate confirmation/idempotency equivalence as expected.
9. Inconsistent record-aware state fails closed and does not silently become absence.
10. Filament private/internal state is never read.
11. Filament does not introduce a second execution driver/path.
12. `spec/0.1/**` remains unchanged.

## Out of scope

T-501 explicitly does not implement:

- `current_selection` — T-502;
- active filter context — T-503;
- human confirmation UI/bridge — T-504;
- multi-tenant order operations demo — T-505;
- Filament table/bulk-action execution adapters;
- a Filament browser driver;
- a new RuntimeBinding schema/driver;
- automatic page discovery from route/request globals;
- record history/audit payload storage;
- model serialization;
- arbitrary record lookup by ID;
- Filament 4 compatibility;
- generic framework-neutral `CurrentRecordResolver` refactor;
- changes to the browser runtime or public wire spec.

## Acceptance criteria

T-501 is complete only when all of the following are proven:

1. Filament 5 is the first accepted production-oriented reference vertical (D-019).
2. D-048 records that Filament contributes trusted context over existing Livewire execution, not a second driver.
3. A real active Filament record page yields exact `current_record` trusted context through public `getRecord()`.
4. Non-record pages produce absence, while inconsistent record-aware pages fail closed.
5. Caller/request/metadata values cannot manufacture or retarget current-record authority.
6. The exact Eloquent model instance is preserved as trusted value.
7. Stable record identity is deterministic, domain-separated, hashed and does not include business attributes.
8. Existing confirmation scope distinguishes records correctly.
9. Existing idempotency intent distinguishes records correctly.
10. Structured audit persists provider presence only, never record identity/value/scope key.
11. Existing `TrustedContextComposer` remains source-compatible.
12. No new Filament RuntimeBinding driver or duplicate execution path is introduced.
13. Filament remains an optional consumer dependency; the Laravel core remains usable without it.
14. PHP/Illuminate/Testbench/Filament compatibility is validated across the supported CI matrix.
15. Browser and existing Laravel regressions stay green.
16. `spec/0.1/**` remains unchanged.

## Implementation sequencing constraint

After this design is approved in written form, implementation planning should proceed TDD-first in this order:

1. dependency/real-Filament compatibility proof;
2. resolver + safe failure semantics;
3. stable identity projection;
4. Filament trusted-context composer;
5. confirmation/idempotency/audit integration proofs;
6. full regression and external review preparation.

No T-502 work should begin until T-501 is merged/revalidated.