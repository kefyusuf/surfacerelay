# Architecture

## 1. Architectural objective

Keep stable application semantics separate from volatile delivery surfaces and framework-specific execution details.

The four layers are deliberately independent:

```text
Action Definition  = WHAT can be done
Runtime Binding    = HOW this runtime can execute it now
Trusted Runtime    = WHO/WHERE/WHETHER may execute it
Surface Projection = HOW an agent/human surface sees it
```

## 2. Action Definition

An Action Definition is immutable semantic metadata associated with executable application code.

It includes:

- stable ID and version;
- title/description;
- input/output schemas;
- scope;
- effect;
- risk;
- idempotency policy;
- output trust;
- trusted context requirements.

It excludes:

- component IDs;
- URLs/HTTP verbs;
- DOM selectors;
- WebMCP/MCP annotations;
- browser origins;
- Filament resources;
- Livewire class names;
- framework event names.

## 3. Runtime Binding

A Runtime Binding connects one Action Definition to a currently executable runtime target.

Examples:

- mounted Livewire component + method;
- HTMX form/element + request behavior;
- current LiveView process/event;
- HTTP endpoint;
- a custom app-owned driver.

Bindings have lifecycle. A component-scoped binding may disappear when the component unmounts or navigation occurs. A stale binding must fail closed rather than discovering an arbitrary replacement.

Locked lifecycle values (see Glossary and D-023/D-024/D-025 for full semantics):

- `page` — tied to one runtime-defined page/surface instance (not necessarily a browser document).
- `component` — tied to one specific component instance; replacements never inherit the binding.
- `session` — tied to a trusted runtime session authority; session identity never comes from caller input.
- `persistent` — not tied to page/component/session lifetime, but never permanent: still subject to revocation, `expiresAt`, and all runtime policy.

Binding validity is cumulative, and whichever condition invalidates first wins:

```text
valid =
    binding exists
    AND not revoked
    AND lifecycle authority is still valid
    AND expiresAt has not passed
    AND referenced action id/version is available
    AND driver is supported
```

## 4. Trusted Invocation Runtime

The runtime receives an invocation envelope and builds a trusted context.

```text
caller input  ------------------------------┐
                                            │ untrusted
client metadata ----------------------------┘

session/auth middleware ──→ actor ──────────┐
tenant resolver ──────────→ tenant          │ trusted
binding producer ─────────→ record/selection│ runtime
confirmation store ───────→ receipt         │ authority
idempotency store ────────→ replay state ───┘
```

### Pipeline

```text
resolve action
→ resolve/verify binding
→ resolve trusted context
→ check context requirements
→ validate input
→ authorize invocation
→ enforce rate limit
→ require/verify confirmation
→ enforce idempotency
→ execute application action
→ validate/redact/annotate output
→ audit
→ normalized result
```

Discovery happens before this pipeline and uses a separate permission decision. A discovered action is not pre-authorized for every input/resource.

## 5. Surface Projection

Projection maps core semantics to a delivery surface.

Example mapping for WebMCP:

| Core | WebMCP projection |
|---|---|
| `effect=read` | `readOnlyHint=true` |
| untrusted output | `untrustedContentHint=true` |
| consequential risk | `consequentialHint=true` where supported |

The browser adapter owns compatibility with changing WebMCP API details. Backend/core packages do not feature-detect browser APIs.

## 6. Laravel reference runtime

```text
packages/laravel
├── Definition
├── Registry
├── Schema
├── Runtime
│   ├── ActionBus
│   ├── Pipeline
│   └── InvocationContext
├── Security
│   ├── Authorization
│   ├── Tenancy
│   ├── Confirmation
│   ├── Idempotency
│   └── OutputPolicy
├── Audit
└── Adapters
    ├── Livewire
    └── Filament
```

The Laravel runtime is real executable code; the cross-language contract is the schema/semantics, not shared PHP code.

## 7. Browser runtime

```text
packages/browser-runtime
├── core
│   ├── DriverRegistry
│   └── binding lifecycle
├── drivers
│   ├── livewire
│   └── htmx
└── projections
    └── webmcp
```

The runtime does not become an agent planner. It registers declared actions, dispatches invocations to bindings, normalizes results, and manages lifecycle/cancellation.

## 8. Extension model

### Contract extensions

Protocol-neutral extensions use namespaced keys rather than adding every experiment to the base schema.

Example:

```json
{
  "extensions": {
    "io.surfacerelay.example/cache-hint": {
      "ttl": 30
    }
  }
}
```

### Binding drivers

Drivers are identifiers, not a closed enum. Implementations must explicitly register supported drivers. Unknown drivers are rejected.

## 9. Versioning

Action ID and action version form a logical contract identity. Bindings reference an exact version.

A breaking input/output/semantic change increments action version. Description-only clarifications may remain same version if behavior and agent selection meaning remain compatible.

## 10. Promotion rule

Do not extract a standalone “standard” repository until:

1. Laravel + Livewire implements the contract;
2. a materially different HTMX binding implements it;
3. shared conformance scenarios expose no Livewire-specific assumptions.
