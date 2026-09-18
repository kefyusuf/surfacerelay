# T-703 — Laravel MCP Projection Design

**Status:** APPROVED DESIGN / IMPLEMENTATION NOT STARTED  
**Task:** T-703  
**Milestone:** M7 — Conformance / Ecosystem Bridges  
**Branch:** `feat/t-703-laravel-mcp-projection`  
**Approved:** 2026-09-18  

## 1. Purpose

T-703 adds an optional Laravel-side MCP projection that exposes selected SurfaceRelay Action Definitions as MCP tools without turning MCP into a second execution model.

The projection must preserve the existing SurfaceRelay separation:

```text
Action Definition  = WHAT can be done
Trusted Runtime    = WHO/WHERE/WHETHER may execute it
ActionBus           = HOW server-side policy/execution runs
MCP Projection      = HOW a selected action is represented to MCP clients
```

T-703 is not an MCP protocol implementation project. It integrates SurfaceRelay with a maintained MCP implementation and keeps protocol/transport mechanics outside SurfaceRelay-owned core semantics.

## 2. Existing constraints

T-703 inherits these accepted decisions and must not reinterpret them:

- D-004: core semantics are protocol-neutral; MCP/WebMCP annotations are projections.
- D-006 / ADR-0006: SurfaceRelay does not implement MCP JSON-RPC or transports from scratch.
- D-007: caller input cannot manufacture trusted actor, tenant, selection, confirmation, or binding authority.
- D-010: exposure is explicit; no expose-all convention.
- D-011: discovery permission does not imply invocation authorization.
- D-013: action identity is exact `id + version`.
- D-015 / D-044: confirmation authority comes only from validated runtime-issued receipts.
- D-027 / D-028 / D-029: trusted context and authorization remain server-runtime responsibilities.
- D-031 / D-032: ActionResult status and output trust/sensitivity semantics remain core semantics.
- D-045: idempotency remains a server-side bounded deduplication contract.
- D-059 through D-061: the current conformance runner has a deliberately closed browser binding-driver scope.
- D-062: adapter/projection documentation may explain but may not silently create new core semantics.

D-026 remains independently PROPOSED and is not promoted by T-703.

## 3. Maintained MCP implementation

T-703 uses the official maintained `laravel/mcp` package rather than implementing MCP protocol mechanics inside SurfaceRelay.

The integration boundary is:

```text
packages/laravel-mcp
├── depends on surfacerelay/laravel
└── depends on laravel/mcp

packages/laravel
└── MUST NOT depend on laravel/mcp
```

This package split is intentional.

The existing `surfacerelay/laravel` package currently supports Illuminate 12 and 13 directly. Adding Laravel MCP to that package would couple the protocol-neutral runtime to MCP release constraints and could narrow its dependency floor. The optional bridge keeps MCP consumers opt-in and preserves the existing Laravel runtime boundary.

T-703 does not own:

- JSON-RPC parsing;
- protocol-version negotiation;
- stdio/HTTP transports;
- OAuth implementation;
- MCP session handling;
- MCP client implementation;
- generic MCP conformance.

Those remain responsibilities of the maintained MCP implementation.

## 4. Scope: MCP Tools only

T-703 v1 projects SurfaceRelay actions only to **MCP Tools**.

Explicit non-goals:

- MCP Resources;
- MCP Prompts;
- MCP Apps / UI resources;
- MCP client support;
- an MCP gateway product;
- remote conformance infrastructure;
- new core Action Definition fields;
- a new authorization model;
- browser RuntimeBinding execution through MCP;
- T-704 OpenAPI importing.

Supporting those later requires separate scope/design gates.

## 5. Explicit MCP exposure

The base `ActionRegistry` is storage, not exposure authority.

T-703 MUST NOT project `ActionRegistry::all()` automatically.

The bridge owns a separate exact-identity allow-list, conceptually:

```text
McpActionExposureRegistry
    ├── orders.list@1
    └── orders.cancel@2
```

Each exposure references exactly one registered Action Definition by `id + version`.

An exposure:

- allows an action to participate in MCP discovery;
- does not authorize invocation;
- does not create trusted context;
- does not create a RuntimeBinding;
- does not change Action Definition semantics.

Unknown action identities, duplicate exposures, duplicate projected tool names, and unsupported scopes fail closed.

Conditional MCP registration may use host MCP registration mechanisms, but it remains only a discovery decision. Invocation authorization always reruns through SurfaceRelay's existing server pipeline.

## 6. Eligible Action scopes

T-703 v1 permits only:

```text
portable  -> eligible
headless  -> eligible
```

The following are not eligible:

```text
page_scoped   -> rejected
browser_local -> rejected
```

Rationale:

An MCP server invocation has no authoritative Livewire component instance, HTMX rendered source, browser page lifecycle, or browser-local binding authority. Projecting these actions would either lie about their runtime requirements or force T-703 to invent new binding semantics.

T-703 therefore does not reuse or synthesize browser RuntimeBindings.

## 7. Tool identity

One MCP tool name is deterministically derived from exact Action identity:

```text
<action-id>.v<version>
```

Example:

```text
orders.find.v1
orders.cancel.v2
```

Tool identity MUST NOT be derived from:

- PHP class names;
- Laravel routes;
- URLs;
- RuntimeBinding IDs;
- component IDs;
- aliases;
- "latest" action versions;
- fallback version resolution.

Invalid or colliding projected names fail loudly.

## 8. Metadata projection

T-703 copies only representable Action Definition information into the MCP tool surface.

Baseline mapping:

| SurfaceRelay | MCP tool |
|---|---|
| exact Action identity | deterministic tool name |
| title | title |
| description | description |
| input schema | input schema |
| `effect=read` | `readOnlyHint=true` where supported |

T-703 v1 does not infer additional MCP semantic hints unless an accepted decision defines an exact mapping.

In particular, these are not automatically inferred:

- destructive hint from SurfaceRelay effect/risk;
- idempotent hint from SurfaceRelay idempotency policy;
- open-world hint from external-side-effect semantics.

Similarity is not sufficient to create a cross-protocol semantic mapping.

## 9. Invocation boundary

MCP tool invocation MUST converge on the existing SurfaceRelay execution path:

```text
MCP tools/call
      │
      ▼
Laravel MCP Request
      │
      ├── arguments -> untrusted Action input
      │
      └── namespaced metadata -> non-authoritative invocation candidates
      ▼
McpActionGateway
      │
      ├── exact action id/version
      ├── TrustedContextComposer
      └── InvocationContext(surface = "mcp")
      ▼
ActionBus
      ▼
existing validation / authorization / idempotency /
confirmation / execution / output-policy / audit pipeline
      ▼
ActionResultNormalizer
      ▼
MCP structured response
```

T-703 MUST NOT define an MCP-only business executor or bypass ActionBus stages.

## 10. Trust boundary

MCP tool arguments are ordinary untrusted Action input.

Caller-supplied keys such as these never become authority merely because their names resemble trusted state:

```text
actor
user
tenant
tenant_id
roles
permissions
current_record
current_selection
confirmed
binding
bindingId
```

Trusted actor and tenant values come only from the existing injected trusted resolvers and `TrustedContextComposer`.

Any future trusted-context provider specific to MCP requires a separate explicit decision if it introduces new authority.

## 11. Confirmation and idempotency metadata

Confirmation receipts and idempotency keys are invocation controls, not business input and not trusted context.

T-703 v1 reserves namespaced MCP request metadata candidates:

```text
io.surfacerelay/confirmationReceipt
io.surfacerelay/idempotencyKey
```

Their presence grants no authority by itself.

- `confirmationReceipt` is an untrusted receipt candidate and must pass the existing server-side confirmation verification/consumption contract.
- `idempotencyKey` is invocation metadata and must pass the existing T-402 idempotency contract.
- `confirmed=true`, challenge IDs, caller booleans, or equivalent ordinary arguments never materialize confirmation authority.

Malformed or over-limit metadata fails closed before business execution.

## 12. Result projection

T-703 preserves SurfaceRelay ActionResult semantics rather than translating business/policy outcomes into transport errors.

These remain valid structured application results:

- `succeeded`;
- `rejected`;
- `failed`;
- `confirmation_required`.

The MCP adapter returns the normalized ActionResult as structured content where supported.

MCP/JSON-RPC level errors are reserved for adapter/protocol failures such as:

- malformed MCP invocation shape;
- unknown MCP tool;
- projection invariant failure;
- internal bridge failure before a valid SurfaceRelay ActionResult exists.

An authorization denial, confirmation requirement, input rejection, or output-policy failure is not silently reclassified as a JSON-RPC transport error.

## 13. Proposed package architecture

Conceptual package shape:

```text
packages/laravel-mcp/
├── composer.json
├── src/
│   ├── Exposure/
│   │   ├── McpActionExposure.php
│   │   └── McpActionExposureRegistry.php
│   ├── Projection/
│   │   ├── McpToolNameProjector.php
│   │   └── McpToolProjector.php
│   ├── Invocation/
│   │   ├── McpActionGateway.php
│   │   └── McpInvocationMetadata.php
│   ├── Server/
│   │   └── SurfaceRelayMcpServer.php
│   └── SurfaceRelayMcpServiceProvider.php
└── tests/
```

Exact implementation classes may be refined in the implementation plan, but the responsibility boundaries are part of this design.

The bridge may depend inward on public SurfaceRelay Laravel contracts. The base Laravel runtime must never import bridge/MCP classes.

## 14. Security invariants

T-703 must prove at least:

1. no implicit registry-wide exposure;
2. discovery is not authorization;
3. caller tenant/actor fields do not become trusted authority;
4. page/browser-local actions cannot be projected;
5. exact action version is preserved;
6. consequential actions still require the existing confirmation flow;
7. idempotency requirements still run through the existing server stage;
8. output policy still executes;
9. structured audit still executes;
10. MCP metadata cannot manufacture trusted context;
11. unsupported/ambiguous projection states fail closed.

Every authority-sensitive implementation task needs a negative test.

## 15. Verification strategy

Implementation-phase verification should include four layers.

### 15.1 Architecture tests

Prove:

- `packages/laravel` has no dependency/import on `laravel/mcp`;
- bridge dependency direction is one-way;
- no MCP-specific fields enter Action Definition or RuntimeBinding contracts.

### 15.2 Projection tests

Prove:

- exact deterministic tool identity;
- explicit exposure only;
- duplicate/invalid exposure fails;
- only portable/headless scopes are eligible;
- input schema/title/description mapping;
- only accepted MCP annotation mappings are emitted.

### 15.3 Trust/invocation tests

Negative proofs include:

- caller `tenant_id` cannot become trusted tenant;
- caller actor/user fields cannot become trusted actor;
- caller record/selection fields cannot create trusted current context;
- caller `confirmed=true` cannot bypass confirmation;
- malformed receipt/idempotency metadata fails closed;
- discovery eligibility does not bypass invocation authorization.

Positive pipeline proofs include:

- MCP call -> existing ActionBus -> existing executor;
- authorization denial is preserved;
- `confirmation_required` is preserved;
- valid confirmation retry uses the existing receipt contract;
- required idempotency behavior is preserved;
- output policy and audit still run.

### 15.4 Maintained MCP integration tests

Use Laravel MCP's supported test surface to verify:

- projected tool appears in tools/list;
- unexposed action does not appear;
- eligible tool can be called;
- unsupported scope is not registered;
- structured ActionResult is returned.

SurfaceRelay does not expand T-701's closed browser conformance runner into a generic MCP protocol runner in T-703.

## 16. Decisions introduced by this design

### D-063 — Maintained Laravel MCP bridge boundary

T-703 uses the maintained official `laravel/mcp` implementation. SurfaceRelay does not implement MCP JSON-RPC, protocol negotiation, transports, OAuth, session mechanics, or generic protocol compatibility. The integration lives in an optional `packages/laravel-mcp` bridge so the existing `packages/laravel` runtime remains MCP-independent.

### D-064 — MCP tool projection and invocation boundary

Only explicitly MCP-exposed portable/headless Action Definitions may be projected. Tool identity is exact Action `id + version`; MCP arguments remain untrusted Action input; trusted actor/tenant authority comes only from existing server-runtime resolvers; confirmation receipt and idempotency key are namespaced non-authoritative metadata candidates; all invocation converges on the existing ActionBus and normalized ActionResult path.

Both decisions remain PROPOSED until implementation, verification, and review evidence justify promotion.

## 17. Explicit non-goals

T-703 does not:

- modify canonical Action Definition or RuntimeBinding schemas;
- add an MCP transport implementation;
- add a generic plugin framework;
- expose every registered Action automatically;
- create browser binding authority on the server;
- create a new trusted-context fallback from request data;
- create a new confirmation or idempotency mechanism;
- broaden T-701 conformance scope;
- implement MCP Resources, Prompts, Apps, or client functionality;
- implement T-704 OpenAPI importing.

## 18. Implementation gate

Design approval does not start implementation.

Before code changes:

1. this design must be committed;
2. D-063 and D-064 remain PROPOSED;
3. T-703 tracking must say implementation not started;
4. a separate implementation plan must be written and reviewed;
5. implementation then begins only after an explicit subsequent gate.
