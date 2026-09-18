# T-703 — Laravel MCP Projection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional Laravel MCP bridge that projects explicitly exposed SurfaceRelay `portable` / `headless` Action Definitions as MCP tools while routing every invocation through the existing trusted `ActionBus` pipeline.

**Architecture:** Create `packages/laravel-mcp`, depending inward on `surfacerelay/laravel` and maintained `laravel/mcp:^1.0`. The bridge owns exposure, tool projection, MCP metadata parsing, and server wiring; the existing Laravel runtime remains MCP-independent and continues to own trusted context, authorization, confirmation, idempotency, execution, output policy, audit, and ActionResult normalization.

**Tech Stack:** PHP 8.3+, Laravel / Illuminate 12.41.1+ and 13.x, `laravel/mcp:^1.0`, PHPUnit 11, Orchestra Testbench 10.8+/11.x, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-18-laravel-mcp-projection-design.md`

## Global Constraints

- `packages/laravel` MUST NOT depend on or import `laravel/mcp`.
- All MCP-specific production code lives under `packages/laravel-mcp`.
- Do not implement MCP JSON-RPC, transports, OAuth, sessions, protocol negotiation, or clients.
- T-703 v1 exposes MCP Tools only.
- Do not use `ActionRegistry::all()` as exposure authority.
- Only exact explicitly exposed `portable` and `headless` Action Definitions are eligible.
- `page_scoped` and `browser_local` fail closed.
- Tool identity is exactly `<action-id>.v<version>`, with no aliases or fallback versions.
- Projected names must match `^[A-Za-z0-9_.-]{1,128}$`.
- MCP arguments are untrusted Action input.
- Actor/tenant authority comes only from existing trusted runtime resolvers.
- `io.surfacerelay/confirmationReceipt` and `io.surfacerelay/idempotencyKey` are non-authoritative metadata candidates.
- Every business invocation reaches the existing `ActionBus` and `ActionResultNormalizer`.
- Valid SurfaceRelay results remain structured MCP tool results; do not convert policy/business results into JSON-RPC errors.
- Do not expand T-701 conformance.
- D-063 / D-064 remain PROPOSED through implementation and external review.
- T-704 is outside this plan.

## Planned file structure

```text
packages/laravel-mcp/
├── composer.json
├── phpunit.xml
├── README.md
├── src/
│   ├── Exposure/
│   │   ├── InvalidMcpActionExposure.php
│   │   ├── McpActionExposure.php
│   │   └── McpActionExposureRegistry.php
│   ├── Invocation/
│   │   ├── InvalidMcpInvocationMetadata.php
│   │   ├── McpActionGateway.php
│   │   └── McpInvocationMetadata.php
│   ├── Projection/
│   │   ├── InvalidMcpToolProjection.php
│   │   ├── McpToolNameProjector.php
│   │   └── McpToolProjector.php
│   ├── Server/
│   │   ├── SurfaceRelayActionTool.php
│   │   └── SurfaceRelayMcpServer.php
│   └── SurfaceRelayMcpServiceProvider.php
└── tests/
    ├── Architecture/DependencyBoundaryTest.php
    ├── Integration/
    │   ├── McpConfirmationIdempotencyIntegrationTest.php
    │   ├── McpPipelineTrustIntegrationTest.php
    │   └── SurfaceRelayMcpServerIntegrationTest.php
    ├── Support/McpTestRuntime.php
    ├── Unit/
    │   ├── McpActionExposureRegistryTest.php
    │   ├── McpActionGatewayTest.php
    │   ├── McpInvocationMetadataTest.php
    │   ├── McpToolNameProjectorTest.php
    │   └── SurfaceRelayActionToolTest.php
    └── TestCase.php
```

Existing files modified only when their task requires it:
`.github/workflows/validate.yml`, `README.md`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.

Forbidden implementation paths for T-703:
`packages/laravel/src/**`, `spec/0.1/**`, `conformance/targets/**`, `scripts/conformance_model.py`, `scripts/run_conformance.py`.

---

### Task 1: Scaffold the optional bridge package and architecture guard

**Files:**
- Create: `packages/laravel-mcp/composer.json`
- Create: `packages/laravel-mcp/phpunit.xml`
- Create: `packages/laravel-mcp/src/SurfaceRelayMcpServiceProvider.php`
- Create: `packages/laravel-mcp/tests/TestCase.php`
- Create: `packages/laravel-mcp/tests/Architecture/DependencyBoundaryTest.php`
- Modify: `.github/workflows/validate.yml`

**Interfaces:** Produces package `surfacerelay/laravel-mcp`; base package remains MCP-independent.

- [ ] **Step 1: Create package bootstrap**

Use `php:^8.3`, `surfacerelay/laravel:dev-main`, `laravel/mcp:^1.0`, PHPUnit 11, Testbench `^10.8|^11.0`, PSR-4 `SurfaceRelay\LaravelMcp\`.

Use a Composer path repository for `../laravel` with `surfacerelay/laravel=dev-main`. Auto-discover `SurfaceRelayMcpServiceProvider`.

Create an initially empty provider shell; it must not auto-register routes or actions.

- [ ] **Step 2: Install dependencies**

```bash
cd packages/laravel-mcp
composer update --no-interaction --no-progress --prefer-dist
```

Expected: successful resolution of the local SurfaceRelay package and `laravel/mcp:^1.0`.

- [ ] **Step 3: Write architecture test**

`DependencyBoundaryTest` loads `packages/laravel/composer.json`, asserts `laravel/mcp` is absent from `require` / `require-dev`, recursively scans `packages/laravel/src/**/*.php`, and asserts no source contains `Laravel\\Mcp\\`.

- [ ] **Step 4: Run architecture test**

```bash
vendor/bin/phpunit tests/Architecture/DependencyBoundaryTest.php
```

Expected: PASS. If it fails, remove dependency leakage rather than weakening the guard.

- [ ] **Step 5: Add CI job**

Add `laravel-mcp-tests` with:

```yaml
include:
  - { php-version: '8.3', laravel-version: '^12.41.1', testbench-version: '^10.8' }
  - { php-version: '8.3', laravel-version: '^13.0',    testbench-version: '^11.0' }
  - { php-version: '8.4', laravel-version: '^12.41.1', testbench-version: '^10.8' }
  - { php-version: '8.4', laravel-version: '^13.0',    testbench-version: '^11.0' }
```

For each matrix entry:

```bash
composer require --dev --no-interaction --no-progress --no-update   "laravel/framework:${{ matrix.laravel-version }}"   "orchestra/testbench:${{ matrix.testbench-version }}"
composer update --no-interaction --no-progress --prefer-dist
composer validate --strict
composer test
```

Extend PHP lint to cover both Laravel packages.

- [ ] **Step 6: Verify and commit**

```bash
composer validate --strict
composer test
cd ../..
python scripts/validate.py
git add packages/laravel-mcp .github/workflows/validate.yml
git commit -m "build(mcp): scaffold optional Laravel MCP bridge"
```

---

### Task 2: Implement explicit exact-identity exposure

**Files:**
- Create: `src/Exposure/InvalidMcpActionExposure.php`
- Create: `src/Exposure/McpActionExposure.php`
- Create: `src/Exposure/McpActionExposureRegistry.php`
- Create: `tests/Unit/McpActionExposureRegistryTest.php`

**Interfaces:**
```php
new McpActionExposure(ActionDefinition $definition);
new McpActionExposureRegistry(ActionRegistry $actions);
McpActionExposureRegistry::expose(string $id, int $version): void;
McpActionExposureRegistry::all(): array; // list<McpActionExposure>
```

- [ ] **Step 1: Write RED tests**

Required tests:
- exact portable identity exposed;
- exact headless identity exposed;
- unknown identity rejected with no version fallback;
- page-scoped rejected;
- browser-local rejected;
- duplicate exact exposure rejected;
- deterministic id/version ordering;
- a spy `ActionRegistry::all()` that throws is never called.

- [ ] **Step 2: Run RED**

```bash
vendor/bin/phpunit tests/Unit/McpActionExposureRegistryTest.php
```

- [ ] **Step 3: Implement minimal registry**

```php
$definition = $this->actions->get($id, $version);

if (!in_array($definition->scope, [ActionScope::Portable, ActionScope::Headless], true)) {
    throw InvalidMcpActionExposure::unsupportedScope($definition);
}

$key = $definition->id.'@'.$definition->version;

if (isset($this->exposures[$key])) {
    throw InvalidMcpActionExposure::duplicate($definition);
}

$this->exposures[$key] = new McpActionExposure($definition);
```

`all()` returns a copied deterministic list. No scanning, wildcard, latest-version behavior, or auto-exposure.

- [ ] **Step 4: Run GREEN and commit**

```bash
vendor/bin/phpunit tests/Unit/McpActionExposureRegistryTest.php
composer test
git add src/Exposure tests/Unit/McpActionExposureRegistryTest.php
git commit -m "feat(mcp): add explicit action exposure registry"
```

---

### Task 3: Project exact MCP tool identity and discovery shape

**Files:**
- Create: `src/Projection/InvalidMcpToolProjection.php`
- Create: `src/Projection/McpToolNameProjector.php`
- Create: `src/Projection/McpToolProjector.php`
- Create: `src/Server/SurfaceRelayActionTool.php`
- Create: `tests/Unit/McpToolNameProjectorTest.php`
- Create: `tests/Unit/SurfaceRelayActionToolTest.php`

**Interfaces:**
```php
McpToolNameProjector::project(ActionDefinition $definition): string;
McpToolProjector::projectAll(array $exposures): array; // list<SurfaceRelayActionTool>
```

- [ ] **Step 1: Write RED name tests**

Assert:
- `orders.cancel@2 -> orders.cancel.v2`;
- different versions differ;
- same definition is deterministic;
- projected name >128 fails;
- invalid projected characters fail.

Use valid long ID:
```php
str_repeat('a', 63).'.'.str_repeat('b', 63)
```

- [ ] **Step 2: Implement exact name projector**

```php
private const NAME_PATTERN = '/^[A-Za-z0-9_.-]{1,128}$/D';

$name = $definition->id.'.v'.$definition->version;

if (preg_match(self::NAME_PATTERN, $name) !== 1) {
    throw InvalidMcpToolProjection::invalidName($name);
}
```

No truncation or hashing.

- [ ] **Step 3: Write RED tool-shape tests**

For a read action, `toArray()` must include exact:
- projected name;
- title;
- description;
- canonical `inputSchema` unchanged;
- `annotations.readOnlyHint=true`.

For writes, do not emit `destructiveHint`, `idempotentHint`, or `openWorldHint`.

Do not emit MCP `outputSchema` in v1 because returned structured content is an ActionResult envelope, not raw action output.

- [ ] **Step 4: Implement tool discovery projection**

`SurfaceRelayActionTool` extends `Laravel\Mcp\Server\Tool`, stores ActionDefinition, projected name, gateway, and overrides `name()`, `title()`, `description()`, `annotations()`, `toArray()`.

Override `toArray()` so canonical JSON Schema is copied directly; do not rebuild it through Laravel's schema builder.

`McpToolProjector::projectAll()` validates element types, detects duplicate projected names, and preserves deterministic exposure order.

- [ ] **Step 5: Run GREEN and commit**

```bash
vendor/bin/phpunit tests/Unit/McpToolNameProjectorTest.php
vendor/bin/phpunit tests/Unit/SurfaceRelayActionToolTest.php
composer test
git add src/Projection src/Server/SurfaceRelayActionTool.php tests/Unit
git commit -m "feat(mcp): project exposed actions as MCP tools"
```

---

### Task 4: Parse bounded request metadata and route calls through ActionBus

**Files:**
- Create: `src/Invocation/InvalidMcpInvocationMetadata.php`
- Create: `src/Invocation/McpInvocationMetadata.php`
- Create: `src/Invocation/McpActionGateway.php`
- Create: `tests/Support/McpTestRuntime.php`
- Create: `tests/Unit/McpInvocationMetadataTest.php`
- Create: `tests/Unit/McpActionGatewayTest.php`
- Modify: `src/Server/SurfaceRelayActionTool.php`

**Interfaces:**
```php
McpInvocationMetadata::from(?array $meta): self;
McpActionGateway::__construct(
    ActionBus $bus,
    ActionResultNormalizer $normalizer,
    TrustedContextComposer $trustedContext,
);
McpActionGateway::invoke(
    ActionDefinition $definition,
    array $input,
    McpInvocationMetadata $metadata,
): ActionResult;
```

- [ ] **Step 1: Write metadata RED tests**

Exact keys:
```text
io.surfacerelay/confirmationReceipt
io.surfacerelay/idempotencyKey
```

Prove:
- absent keys -> null;
- unknown metadata ignored;
- strings preserved;
- non-string candidates rejected;
- confirmation >4096 chars rejected;
- idempotency empty or >240 chars rejected.

- [ ] **Step 2: Implement bounded parser**

Extract only the two namespaced keys. Do not copy arbitrary MCP `_meta` into trusted context or `InvocationContext::metadata`.

- [ ] **Step 3: Write gateway RED tests**

Prove:
- `surface='mcp'`;
- correlation ID is generated server-side and non-empty;
- exact action id/version used;
- bindingId is null;
- idempotency candidate lands only in `InvocationContext::idempotencyKey`;
- confirmation candidate lands only in `ActionCall::confirmationReceipt`.

Security cases:
- input `tenant_id` cannot replace resolver tenant;
- input `actor` cannot replace resolver actor;
- input `current_record` cannot satisfy missing CurrentRecord;
- input `current_selection` cannot satisfy missing CurrentSelection;
- input `confirmed=true` cannot create HumanConfirmation.

- [ ] **Step 4: Implement gateway**

```php
$context = new InvocationContext(
    surface: 'mcp',
    correlationId: bin2hex(random_bytes(16)),
    trustedContext: $this->trustedContext->resolve(),
    idempotencyKey: $metadata->idempotencyKey,
);

$outcome = $this->bus->dispatch(new ActionCall(
    actionId: $definition->id,
    actionVersion: $definition->version,
    input: $input,
    context: $context,
    bindingId: null,
    confirmationReceipt: $metadata->confirmationReceipt,
));

return $this->normalizer->normalize($outcome);
```

- [ ] **Step 5: Complete tool handle**

```php
$metadata = McpInvocationMetadata::from($request->meta());
$result = $this->gateway->invoke($this->definition, $request->all(), $metadata);

return Response::structured($result->toArray());
```

Do not use `Response::error()` for a valid SurfaceRelay ActionResult.

- [ ] **Step 6: Run GREEN and commit**

```bash
vendor/bin/phpunit tests/Unit/McpInvocationMetadataTest.php
vendor/bin/phpunit tests/Unit/McpActionGatewayTest.php
vendor/bin/phpunit tests/Unit/SurfaceRelayActionToolTest.php
composer test
git add src/Invocation src/Server/SurfaceRelayActionTool.php tests
git commit -m "feat(mcp): route tool calls through ActionBus"
```

---

### Task 5: Wire the dynamic Laravel MCP server

**Files:**
- Create: `src/Server/SurfaceRelayMcpServer.php`
- Modify: `src/SurfaceRelayMcpServiceProvider.php`
- Create: `tests/Integration/SurfaceRelayMcpServerIntegrationTest.php`

**Interfaces:** Host applications choose MCP route/transport/auth; bridge provides server and exposure singleton.

- [ ] **Step 1: Write RED integration tests**

Prove:
- tools/list includes only explicit exposure;
- registered but unexposed action absent;
- ordering deterministic;
- tools/call reaches gateway/executor;
- rejected and confirmation-required outcomes remain structured ActionResult.

Use Laravel MCP `FakeTransporter`, call `start()`, and inspect:
```php
$tools = $server->createContext()->tools()->map->toArray()->all();
```

- [ ] **Step 2: Bind exposure registry**

```php
$this->app->singleton(
    McpActionExposureRegistry::class,
    fn ($app) => new McpActionExposureRegistry(
        $app->make(ActionRegistry::class),
    ),
);
```

Do not auto-expose actions.

- [ ] **Step 3: Implement server**

```php
public function __construct(
    Transport $transport,
    private readonly McpActionExposureRegistry $exposures,
    private readonly McpToolProjector $projector,
) {
    parent::__construct($transport);
}

protected function boot(): void
{
    $this->tools = $this->projector->projectAll($this->exposures->all());
}
```

Set conservative server name/version/instructions. Do not auto-register a route.

- [ ] **Step 4: Run GREEN and commit**

```bash
vendor/bin/phpunit tests/Integration/SurfaceRelayMcpServerIntegrationTest.php
composer test
git add src/Server src/SurfaceRelayMcpServiceProvider.php tests/Integration/SurfaceRelayMcpServerIntegrationTest.php
git commit -m "feat(mcp): wire SurfaceRelay MCP server"
```

---

### Task 6: Prove trust, authorization, output-policy, and audit convergence

**Files:**
- Create: `tests/Integration/McpPipelineTrustIntegrationTest.php`
- Modify: `tests/Support/McpTestRuntime.php`

**Interfaces:** Uses real ActionBus, ActionResultNormalizer, TrustedContextComposer, OutputPolicyStage, StructuredActionPipelineAuditor.

- [ ] **Step 1: Extend deterministic test runtime**

Provide helpers that register one exact definition and install exactly one handler for every canonical ActionPipelineStage. Use real production stage classes for behavior under test; test pass-through handlers only for unrelated stages.

- [ ] **Step 2: Write trust/authorization tests**

Required:
```text
caller tenant argument cannot cross trusted tenant boundary
caller actor argument cannot replace authenticated actor
caller current_record cannot materialize trusted record
caller current_selection cannot materialize trusted selection
discovery does not bypass invocation authorization
```

Assert executor call count remains zero on authorization rejection.

- [ ] **Step 3: Write output-policy tests**

For sensitive output:
- executor returns `SECRET_RAW_MCP_OUTPUT`;
- real OutputPolicyStage without redactor returns normalized `failed/output_policy_failed`;
- secret absent from structured result;
- deterministic redactor releases only safe transformed output.

- [ ] **Step 4: Write audit tests**

Use real StructuredActionPipelineAuditor with in-memory store. Assert exactly one event for success, auth rejection, output-policy failure; `surface === 'mcp'`; serialized event contains no raw input secret, receipt, idempotency key, actor value, or tenant value.

- [ ] **Step 5: Run RED/GREEN and commit**

```bash
vendor/bin/phpunit tests/Integration/McpPipelineTrustIntegrationTest.php
composer test
git add tests/Integration/McpPipelineTrustIntegrationTest.php tests/Support src
git commit -m "test(mcp): prove trusted pipeline convergence"
```

If GREEN requires changing `packages/laravel/src/**`, stop and reopen design/decision instead.

---

### Task 7: Prove existing confirmation and idempotency authority

**Files:**
- Create: `tests/Integration/McpConfirmationIdempotencyIntegrationTest.php`
- Modify: `tests/Support/McpTestRuntime.php`

**Interfaces:** Uses real ConfirmationStage / ConfirmationService and IdempotencyStage / IdempotencyService.

- [ ] **Step 1: Add deterministic confirmation support**

Create test-only in-memory `ConfirmationStore`, fixed `ConfirmationClock`, and fixed 43-character URL-safe `ConfirmationTokenGenerator`.

- [ ] **Step 2: Write confirmation tests**

Required:
- consequential call without receipt -> `confirmation_required`;
- `confirmed=true` argument does not bypass;
- unapproved receipt candidate grants no authority;
- approved exact-scope receipt allows one retry;
- consumed receipt cannot be reused.

Positive flow: issue challenge, approve via real `ConfirmationService::approveChallenge()`, place token in namespaced metadata, retry identical intent, assert one execution.

- [ ] **Step 3: Add deterministic idempotency support**

Create test-only `IdempotencyStore` and fixed `IdempotencyClock`; use existing validators/hashers/replay codec and real IdempotencyService/Stage.

- [ ] **Step 4: Write idempotency tests**

Required:
- required key missing from MCP metadata rejected;
- business argument `idempotencyKey` does not satisfy policy;
- namespaced metadata key permits fresh execution;
- exact retry replays with no second executor call;
- same key + different input -> conflict.

- [ ] **Step 5: Run GREEN and commit**

```bash
vendor/bin/phpunit tests/Integration/McpConfirmationIdempotencyIntegrationTest.php
composer test
git add tests/Integration/McpConfirmationIdempotencyIntegrationTest.php tests/Support src
git commit -m "test(mcp): preserve confirmation and idempotency authority"
```

---

### Task 8: Documentation, full verification, and review handoff

**Files:**
- Create: `packages/laravel-mcp/README.md`
- Modify: `README.md`
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`

- [ ] **Step 1: Document explicit usage**

README example:
```php
public function boot(McpActionExposureRegistry $mcp): void
{
    $mcp->expose('orders.list', 1);
    $mcp->expose('orders.cancel', 2);
}
```

Host-controlled registration:
```php
Mcp::web('/mcp/surfacerelay', SurfaceRelayMcpServer::class);
```

State host owns auth/OAuth/route policy; discovery is not authorization; metadata keys are non-authoritative candidates.

- [ ] **Step 2: Update root README conservatively**

Point to optional `packages/laravel-mcp` bridge. Do not call SurfaceRelay an official MCP implementation.

- [ ] **Step 3: Run bridge package verification**

```bash
cd packages/laravel-mcp
composer validate --strict
composer test
find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

- [ ] **Step 4: Run base Laravel regression**

```bash
cd ../laravel
composer test
composer validate --strict
cd ../..
```

- [ ] **Step 5: Run repository/browser/conformance regression**

```bash
python scripts/validate.py
cd packages/browser-runtime
npm run typecheck
npm test
npm run conformance:build
cd ../..
python -m unittest discover -s scripts/tests -p 'test_*conformance*.py' -v
python scripts/run_conformance.py
```

Historical baseline before T-703:
```text
Browser Vitest:     328/328
Python conformance: 47/47
Canonical matrix:   7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

Record fresh counts from execution; do not fake historical numbers if suites grow.

- [ ] **Step 6: Audit forbidden diffs**

```bash
git diff main...HEAD --   packages/laravel/src   spec/0.1   conformance/targets   scripts/conformance_model.py   scripts/run_conformance.py
```

Expected: empty.

Then:
```bash
git diff --name-only main...HEAD
```
Review every changed path.

- [ ] **Step 7: Update tracking/review handoff**

Set T-703 to `IMPLEMENTED / REVIEW PENDING`, record exact head and CI, keep D-063/D-064 PROPOSED, keep D-026 PROPOSED, and state T-704 not started.

Review questions must cover dependency leakage, explicit exposure, eligible scopes, exact identity/schema projection, trust escalation, metadata authority, ActionBus convergence, result/error boundary, annotations, unchanged T-701 conformance, and proposed decisions.

- [ ] **Step 8: Exact-head CI and stop**

Require the full GitHub Actions matrix green. Do not promote D-063/D-064, merge, close T-703, or begin T-704.

- [ ] **Step 9: Commit review handoff**

```bash
git add packages/laravel-mcp README.md TASKS.md STATUS.md REVIEW_REQUEST.md .github/workflows/validate.yml
git commit -m "docs(review): request T-703 Laravel MCP review"
```

---

## Plan self-review

Before implementation begins, verify:

- D-063 package boundary is implemented without MCP dependency in `packages/laravel`.
- Exposure is explicit and never registry-wide.
- Eligible scopes are exactly portable/headless.
- Exact tool names fail closed and are not truncated.
- Canonical inputSchema is copied directly.
- Only readOnlyHint is mapped in v1.
- Arguments remain untrusted input.
- Trusted actor/tenant values come only from existing resolvers.
- Confirmation/idempotency metadata remains non-authoritative until core verification.
- No RuntimeBinding is synthesized.
- Every invocation reaches ActionBus + ActionResultNormalizer.
- Output policy and audit use real production stages in evidence tests.
- T-701 conformance files remain unchanged.
- D-063 / D-064 remain PROPOSED.
- T-704 remains outside implementation.

## Implementation start gate

Committing this plan does **not** start implementation.

Implementation starts only after an explicit subsequent approval while tracking reads:

```text
T-703: IN_PROGRESS / DESIGN APPROVED / PLAN APPROVED / IMPLEMENTATION NOT STARTED
D-063: PROPOSED
D-064: PROPOSED
T-704: NOT STARTED
```
