# Getting started: expose one read-only action to agents

> **Experimental project. Not an official W3C, WebMCP, MCP, Laravel, or browser-vendor project.**
> This guide describes implemented behavior only; every code block below is
> proven verbatim on a fresh registry consumer by a guarded CI job
> ([`examples/minimal-quickstart`](../../examples/minimal-quickstart/)).

This quickstart gives a Laravel + Filament application the smallest complete
SurfaceRelay path: **one read-only action** that an agent can discover and
invoke through WebMCP while the application keeps every security decision
server-side. The consumer code is intentionally small — one action class, one
explicitly exposed page method, one self-guarding browser module — and it is
the exact code the CI job installs and runs.

You will NOT find write paths, confirmation receipts, idempotency keys, output
redaction or multi-tenancy plumbing here. Those are real requirements for real
actions and are documented as later steps (see *After the quickstart*).

## Prerequisites

- Laravel 13 with Filament 5 and a login-protected admin panel.
- `composer require surfacerelay/laravel:0.1.0-alpha.3`
- `npm install --save-exact @surfacerelay/browser-runtime@0.1.0-alpha.3`
- Install the exact alpha versions; alpha releases make no compatibility promises.

## Step 1 — define one read-only action

An `ActionDefinition` is protocol-neutral: no URLs, no component IDs, no WebMCP
fields. It states *what* the application can do, its risk, and what trusted
context it requires.

```php
// app/Actions/InspectOrder.php (excerpt — full file in the example)
public static function definition(): ActionDefinition
{
    return new ActionDefinition(
        id: 'quickstart.orders.inspect',
        version: 1,
        title: 'Inspect order',
        description: 'Read the status of one order belonging to the tenant.',
        inputSchema: [
            'type' => 'object',
            'properties' => ['order_id' => ['type' => 'integer', 'minimum' => 1]],
            'required' => ['order_id'],
            'additionalProperties' => false,
        ],
        scope: ActionScope::PageScoped,
        effect: ActionEffect::Read,
        risk: ActionRisk::Low,
        idempotency: IdempotencyPolicy::None,
        outputSensitivity: OutputSensitivity::Normal,
        outputContentTrust: OutputContentTrust::TrustedApplicationData,
        contextRequirements: [ContextRequirement::AuthenticatedActor, ContextRequirement::Tenant],
    );
}
```

The same class dispatches the action through the trusted pipeline — input
validation, Gate authorization, idempotency, confirmation, execution, output
policy, audit — via `FilamentActionGateway`. The caller's input is untrusted:
the tenant comes from a server-side resolver and the Gate re-checks the order
inside that tenant on every dispatch.

```php
// Trusted context: a single-tenant demo uses a server-side constant.
// Guests fail closed; multi-tenant apps derive this from their own model.
final class FixedTenantResolver implements TenantResolver
{
    public const TENANT = 'quickstart-tenant';

    public function resolve(): ?ResolvedTrustedValue
    {
        if (Auth::guard('web')->user() === null) {
            return null;
        }

        return new ResolvedTrustedValue(
            value: self::TENANT,
            provenance: new ContextProvenance('quickstart.fixed-tenant'),
            confirmationScopeKey: self::TENANT,
        );
    }
}
```

Full file: [`app/Actions/InspectOrder.php`](../../examples/minimal-quickstart/app/Actions/InspectOrder.php)
and [`app/Support/FixedTenantResolver.php`](../../examples/minimal-quickstart/app/Support/FixedTenantResolver.php).

## Step 2 — expose it on one Filament page

Exposure is explicit: the `#[ExposeAction]` attribute on one page method is
metadata only — it issues no authority and registers no tool by itself. The
page also issues the *browser descriptor* (the canonical definition plus the
server-issued component binding) as `#[Locked]` state.

```php
// app/Filament/Resources/QuickstartOrders/Pages/ListQuickstartOrders.php (excerpt)
#[Locked]
public ?array $surfacerelayDescriptor = null;

#[ExposeAction('quickstart.orders.inspect', 1)]
public function inspectOrder(int $order_id): void
{
    $outcome = app(InspectOrder::class)->dispatch($this, ['order_id' => $order_id]);
    $this->inspectionStatus = $outcome->completed ? 'ok' : 'denied';
    $this->inspected = $outcome->completed ? $outcome->state->output : null;
}

public function render(): View
{
    $this->issueBrowserDescriptor();
    return parent::render();
}
```

`issueBrowserDescriptor()` pairs the canonical definition with the exact
`LivewireBindingProducer` binding for the mounted component — public discovery
metadata, never an authorization capability. Full file:
[`ListQuickstartOrders.php`](../../examples/minimal-quickstart/app/Filament/Resources/QuickstartOrders/Pages/ListQuickstartOrders.php).

## Step 3 — wire the browser

One service-provider render hook serves the published runtime bytes and the
registration module on panel pages; the module self-guards and registers
nothing on unsupported surfaces or pages without a descriptor.

```js
// public/js/quickstart-register.mjs (core of the module)
const descriptor = descriptorFromMountedComponents();   // reads the Locked descriptor
if (!descriptor) return;
const modelContext = resolveDocumentModelContext(document);
if (modelContext === null) return;                      // unsupported surface: fail closed

const drivers = new DriverRegistry();
drivers.register('livewire', new LivewireBrowserDriver(new GlobalLivewireBrowserRuntime()));

const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers, { resultMode: 'envelope' });
lease = await lifecycle.register([{ definition: descriptor.definition, binding: descriptor.binding }]);
```

The module disposes the registration lease on Livewire navigation and
re-registers when a descriptor-carrying page returns. Full file:
[`quickstart-register.mjs`](../../examples/minimal-quickstart/public/js/quickstart-register.mjs);
the provider hook lives in
[`QuickstartProvider.php`](../../examples/minimal-quickstart/app/Providers/QuickstartProvider.php).

## Step 4 — verify

- **Server side, in CI:** the guarded workflow
  ([`t913-quickstart-consumer.yml`](../../.github/workflows/t913-quickstart-consumer.yml))
  installs a fresh Laravel 13 + Filament 5 consumer from the registry, applies
  this document's code, and runs the PHPUnit acceptance (dispatch with audit,
  foreign-order denial, forged-field isolation, descriptor issuance, guest
  denial) plus a Node suite that runs the browser module against the published
  package (registration contract, envelope return, forged-input rejection).
- **In your browser:** open the page in a Chrome 150+ build with the WebMCP
  feature enabled and the tool appears through `document.modelContext`. The
  repository's own real-Chrome proof
  ([A2-08](../../scripts/acceptance/a2-08-native-chrome/README.md)) demonstrates
  the same discovery/invocation path end to end.

## The security model you just used

- Caller input is untrusted; the actor, tenant and authorization are resolved
  server-side on every dispatch.
- Exposure (`#[ExposeAction]`) and the browser descriptor are **discovery
  metadata** — neither grants invocation authority.
- Discovery is not authorization: the Gate re-checks the exact order in the
  exact tenant on every call.
- Unknown or unsupported surfaces fail closed: no `document.modelContext`, no
  tool.
- Every dispatch is audited server-side.

## After the quickstart

Real consequential actions need more than this read-only path, in this order:
human confirmation with opaque runtime-issued receipts (D-077), server-side
idempotency and replay protection, output sensitivity and redaction, and
tenant resolution from your own membership model. See
[the Laravel consumer guide](laravel.md), [the browser runtime guide](browser-runtime.md),
[the threat model](../THREAT-MODEL.md) and the A2-04…A2-08 acceptance flows in
[`scripts/acceptance/`](../../scripts/acceptance/). Do not expose a write or
destructive action with only this quickstart.
