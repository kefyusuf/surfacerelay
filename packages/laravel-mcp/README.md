# SurfaceRelay Laravel MCP Bridge

`surfacerelay/laravel-mcp` is an **optional projection bridge** from SurfaceRelay Action Definitions into the maintained `laravel/mcp` package.

It is deliberately not an MCP protocol implementation. `laravel/mcp` owns MCP protocol, transport, request/response, and server mechanics; the host Laravel application owns route placement, authentication, OAuth, and network policy. SurfaceRelay owns only the bounded mapping from explicitly exposed Actions into MCP Tools and routes tool invocation back through the existing trusted Action runtime.

## Boundary

The bridge has a one-way dependency on `surfacerelay/laravel`.

```text
SurfaceRelay ActionRegistry
        │
        │ exact id + version only
        ▼
McpActionExposureRegistry
        │
        ▼
McpToolProjector
        │
        ▼
SurfaceRelayActionTool
        │
        │ MCP arguments + bounded metadata candidates
        ▼
McpActionGateway
        │
        ├─ TrustedContextComposer
        ├─ ActionBus
        └─ ActionResultNormalizer
        │
        ▼
structured ActionResult
```

The base `packages/laravel` package does not depend on `laravel/mcp`, and this bridge does not add MCP-specific fields to Action Definition or Runtime Binding contracts.

## Explicit exposure

MCP exposure is an explicit allow-list keyed by the exact SurfaceRelay Action identity.

```php
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposureRegistry;

public function boot(McpActionExposureRegistry $mcp): void
{
    $mcp->expose('orders.list', 1);
    $mcp->expose('orders.cancel', 2);
}
```

Important constraints:

- `ActionRegistry::all()` is not MCP exposure authority.
- There is no wildcard, "latest version", reflection-based, or registry-wide auto-exposure.
- Only Actions with `portable` or `headless` scope are eligible.
- `page_scoped` and `browser_local` Actions fail closed at exposure time.
- Duplicate exact exposures fail rather than silently overwrite each other.
- Discovery eligibility does **not** grant invocation authorization.

## MCP server registration

The bridge service provider intentionally registers no route, transport, authentication policy, OAuth policy, or implicit tool exposure.

The host application chooses where and how the maintained Laravel MCP server is published, for example:

```php
use Laravel\Mcp\Facades\Mcp;
use SurfaceRelay\LaravelMcp\Server\SurfaceRelayMcpServer;

Mcp::web('/mcp/surfacerelay', SurfaceRelayMcpServer::class);
```

Protect that route according to the application's own authentication, OAuth, tenant, deployment, and network requirements. The MCP tools/list surface is discovery only; every tools/call still passes through the existing SurfaceRelay invocation pipeline.

## Tool projection

Each exposed Action becomes one deterministic MCP Tool.

Current v1 projection rules are intentionally narrow:

- tool name: `<action-id>.v<version>`;
- projected names must satisfy the MCP-compatible `[A-Za-z0-9_.-]` grammar and the 128-character bound;
- Action `title` and `description` are copied directly;
- canonical Action `inputSchema` is copied directly rather than rebuilt through a second schema model;
- `effect=read` maps to `readOnlyHint=true`;
- no other MCP annotations are inferred in v1;
- no MCP output schema is invented;
- no Runtime Binding is synthesized.

This keeps the protocol projection subordinate to the existing SurfaceRelay contracts instead of creating a shadow specification.

## Invocation and trust

MCP request arguments are **untrusted Action input**. They cannot become trusted actor, tenant, current-record, current-selection, confirmation, or idempotency authority merely because the caller supplied similarly named fields.

The invocation path is:

```text
MCP tools/call
  -> SurfaceRelayActionTool
  -> McpInvocationMetadata
  -> McpActionGateway
  -> TrustedContextComposer
  -> ActionBus
  -> ActionResultNormalizer
  -> MCP structured response
```

`McpActionGateway` creates a server-side correlation ID, sets `surface=mcp`, obtains trusted actor/tenant state only through the existing `TrustedContextComposer`, dispatches the existing `ActionBus`, and normalizes the result through `ActionResultNormalizer`.

Authorization, confirmation, idempotency, output policy, redaction, audit, and execution remain responsibilities of the existing trusted runtime stages.

## Confirmation and idempotency metadata

The bridge recognizes only two namespaced MCP metadata keys:

```text
io.surfacerelay/confirmationReceipt
io.surfacerelay/idempotencyKey
```

They are **non-authoritative candidates**, not proof.

- `confirmationReceipt` must be a string and is bounded to 4096 characters. Existing confirmation verification decides whether it is valid, approved, in-scope, unexpired, and consumable.
- `idempotencyKey` must be a non-empty string of at most 240 characters. Existing idempotency policy, hashing, intent comparison, storage, and replay logic remain authoritative.
- unknown MCP metadata is ignored rather than copied into trusted context.
- business input such as `confirmed=true` does not bypass confirmation.
- a business argument named `idempotencyKey` does not satisfy an idempotency requirement.
- an approved confirmation receipt remains exact-scope and single-use according to the existing server contract.
- an exact idempotent retry may replay, while the same key with changed intent fails closed.

## Structured results

The bridge does not create a parallel MCP-specific business result model. A tool call returns the existing normalized SurfaceRelay `ActionResult` as a structured MCP response.

That preserves existing success, rejection, failure, and `confirmation_required` semantics and keeps output-policy/audit behavior on the same execution path used by non-MCP callers.

## What the bridge does not own

T-703 v1 does not implement or claim ownership of:

- MCP JSON-RPC, protocol negotiation, transports, sessions, or OAuth;
- automatic authentication or route protection;
- MCP Resources, Prompts, Apps, or client support;
- browser/page Runtime Bindings;
- automatic exposure of registered Actions;
- new trusted-context fields or caller-authoritative trust;
- a second authorization, confirmation, idempotency, output, or audit pipeline;
- new T-701 conformance profiles, targets, or scenarios;
- OpenAPI import (reserved for T-704).

## Verification

Package-local checks:

```bash
composer validate --strict
composer test
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Repository CI additionally verifies the bridge across:

- PHP 8.3 and 8.4;
- Laravel 12 and 13;
- the existing Laravel package regression matrix;
- PHP lint;
- repository contract validation;
- browser runtime typecheck/tests;
- Python conformance tests;
- browser conformance harness build;
- the canonical runtime matrix.

See the T-703 design and implementation plan for the exact reviewed boundary:

- `docs/superpowers/specs/2026-09-18-laravel-mcp-projection-design.md`
- `docs/superpowers/plans/2026-09-18-laravel-mcp-projection.md`
