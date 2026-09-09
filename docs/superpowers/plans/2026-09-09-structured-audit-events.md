# T-404 Structured Audit Events Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Persist one append-only, payload-minimized structured audit record for every `ActionBus` dispatch that reaches the existing completed/explicit-halt finalization boundary.

**Architecture:** Keep the existing `ActionPipelineAuditor::record(ActionCall, ActionPipelineOutcome)` port unchanged. A new structured auditor projects only final semantic facts through `AuditEventFactory`, appends the immutable event through `AuditEventStore`, and a database adapter performs the insert plus persistence-only equality-index hashing; raw input/output, trusted identity values, bearer capabilities, generic metadata and idempotency material never enter the persisted event contract.

**Tech Stack:** PHP 8.3/8.4, Laravel/Illuminate 12/13, Illuminate Database, PHPUnit 11, SQLite for fast unit store tests, MySQL 8.4 for production-matrix persistence verification, existing GitHub Actions `validate` workflow.

**Spec:** `docs/superpowers/specs/2026-09-09-structured-audit-events-design.md`

## Global Constraints

- Work only on `T-404 — Structured audit events`; do not add event sourcing, SIEM/OpenTelemetry export, query/reporting UI/API, retention cleanup, signing/hash chains, custom encryption/KMS, or automatic audit retry/outbox.
- Keep `ActionPipelineAuditor::record(ActionCall $call, ActionPipelineOutcome $outcome): void` source-compatible.
- Do not modify frozen `spec/0.1/**`, ActionDefinition/Invocation/ActionResult wire shapes, public result taxonomy, WebMCP annotations, or browser contracts.
- T-404 guarantees one audit append attempt only for dispatches that reach the existing completed/explicit-halt finalization boundary; arbitrary PHP `Throwable` paths before `ActionBus::finalize()` remain outside scope.
- Never persist raw/validated action input, any output (including T-403 released output), generic metadata, raw/hashed idempotency material, replay payloads, binding IDs, confirmation receipts/challenges, trusted context values, provenance references, confirmation/scope keys, halt details, exception diagnostics, schemas or extensions.
- Persist trusted context only as canonical ordered `{requirement, provider}` pairs and confirmation only as `humanConfirmationPresent: bool`.
- `eventId` is exactly 32 lowercase hex characters generated from `random_bytes(16)`.
- `recordedAt` is a UTC `DateTimeImmutable`; persistence uses explicit `Y-m-d H:i:s.u` into `DATETIME(6)`.
- Database failures and JSON encoding failures use static, non-chained SurfaceRelay audit exceptions; never expose SQL, table, row or driver diagnostics.
- `correlation_hash` and `halt_code_hash` are database/index artifacts only; they are not `AuditEvent` fields and never confer authority.
- Use TDD for every production change: failing focused test → verify RED → minimal implementation → verify GREEN → commit.
- Preserve PHP 8.3/8.4 × Illuminate 12/13 compatibility and MySQL 8.4 CI coverage.

---

## File Structure

### New runtime audit namespace

- `packages/laravel/src/Audit/AuditOutcomeKind.php` — closed internal `completed|halted` event outcome enum.
- `packages/laravel/src/Audit/AuditTrustedContextEntry.php` — immutable `{ContextRequirement, provider}` persistence-safe provenance pair.
- `packages/laravel/src/Audit/AuditEvent.php` — immutable semantic audit record; validates cross-field completed/halted consistency and UTC timestamp.
- `packages/laravel/src/Audit/AuditClock.php` — injectable UTC wall-clock contract.
- `packages/laravel/src/Audit/SystemAuditClock.php` — production UTC clock.
- `packages/laravel/src/Audit/AuditConfigurationViolation.php` — static internal configuration/invariant errors such as non-UTC clocks.
- `packages/laravel/src/Audit/AuditEventFactory.php` — explicit semantic allowlist projection from final `ActionPipelineOutcome`; deliberately receives no `ActionCall` so raw caller-only candidates are outside the factory API.
- `packages/laravel/src/Audit/AuditEventStore.php` — append-only write contract.
- `packages/laravel/src/Audit/StructuredActionPipelineAuditor.php` — adapter implementing the existing pipeline auditor port.
- `packages/laravel/src/Audit/AuditStoreUnavailable.php` — static non-chained persistence/encoding failure.
- `packages/laravel/src/Audit/DatabaseAuditEventStore.php` — one-insert Illuminate database adapter and persistence-only equality hashes.

### Persistence artifact

- `packages/laravel/database/migrations/0000_00_00_000001_create_surfacerelay_audit_events.php` — append-only audit table and exact indexes; ordered after T-402 migration.

### Tests

- `packages/laravel/tests/Unit/AuditEventFactoryTest.php` — semantic projection, secrecy, UTC and event-ID invariants.
- `packages/laravel/tests/Unit/StructuredActionPipelineAuditorTest.php` — exactly-one append and propagation semantics.
- `packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php` — row projection, hashes, static failures, append-only collision, migration/schema and MySQL timestamp behavior.
- `packages/laravel/tests/Integration/StructuredAuditPipelineIntegrationTest.php` — real ActionBus finalization, confirmation and T-403 output-policy interactions.
- `packages/laravel/tests/Integration/StructuredAuditIdempotencyReplayIntegrationTest.php` — T-402 completed replay creates a second audit event without a second application execution.
- `packages/laravel/tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php` — adversarial marker proof against the durable row/JSON representation.

### Existing files modified only where necessary

- `packages/laravel/src/Runtime/Pipeline/ActionPipelineAuditor.php` — update stale T-404-future doc comment only; method signature unchanged.
- `STATUS.md` — record T-404 implementation/review checkpoint only after code is verified.
- `TASKS.md` — mark T-404 done only after acceptance criteria pass.
- `REVIEW_REQUEST.md` — prepare exact-head external review evidence after full CI is green.

---

### Task 1: Semantic Audit Event and Allowlist Factory

**Files:**
- Create: `packages/laravel/src/Audit/AuditOutcomeKind.php`
- Create: `packages/laravel/src/Audit/AuditTrustedContextEntry.php`
- Create: `packages/laravel/src/Audit/AuditEvent.php`
- Create: `packages/laravel/src/Audit/AuditClock.php`
- Create: `packages/laravel/src/Audit/SystemAuditClock.php`
- Create: `packages/laravel/src/Audit/AuditConfigurationViolation.php`
- Create: `packages/laravel/src/Audit/AuditEventFactory.php`
- Test: `packages/laravel/tests/Unit/AuditEventFactoryTest.php`

**Interfaces:**
- Consumes: `ActionPipelineOutcome`, its final `ActionPipelineState`, exact `ActionDefinition`, `InvocationContext::allTrusted()`, `ContextRequirement`, `ActionPipelineStage`.
- Produces: `AuditEventFactory::__construct(AuditClock $clock)` and `AuditEventFactory::create(ActionPipelineOutcome $outcome): AuditEvent`.
- Produces: `AuditEvent` with typed enums for declared action semantics, `?ActionPipelineStage $haltedAt`, `AuditOutcomeKind $outcomeKind`, `list<AuditTrustedContextEntry> $trustedContextManifest`.

- [ ] **Step 1: Write the RED factory tests for completed projection and forbidden-field minimization**

Create `AuditEventFactoryTest.php` with a fixed UTC clock and a completed state containing adversarial values:

```php
final class FixedAuditClock implements AuditClock
{
    public function __construct(private readonly DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

public function test_completed_event_projects_only_allowlisted_final_facts(): void
{
    $definition = $this->definition(
        inputSchema: ['type' => 'object', 'secret_schema_marker' => true],
        outputSchema: ['secret_output_schema_marker' => true],
        extensions: ['example/secret' => 'SECRET_EXTENSION'],
    );
    $context = new InvocationContext(
        surface: 'webmcp',
        correlationId: 'corr-404',
        trustedContext: [
            new TrustedContextEntry(
                ContextRequirement::Tenant,
                'SECRET_TENANT_VALUE',
                new ContextProvenance('tenant.provider', 'SECRET_PROVENANCE_REFERENCE'),
                confirmationScopeKey: 'SECRET_SCOPE_KEY',
            ),
        ],
        idempotencyKey: 'SECRET_IDEMPOTENCY_KEY',
        metadata: ['secret' => 'SECRET_METADATA'],
    );
    $state = new ActionPipelineState(
        definition: $definition,
        input: ['secret' => 'SECRET_INPUT'],
        context: $context,
        hasOutput: true,
        output: ['secret' => 'SECRET_OUTPUT'],
        bindingId: 'SECRET_BINDING',
        confirmationReceipt: 'SECRET_RECEIPT',
    );

    $event = (new AuditEventFactory(new FixedAuditClock(
        new DateTimeImmutable('2026-09-09 16:20:30.123456', new DateTimeZone('UTC')),
    )))->create(ActionPipelineOutcome::completed($state));

    self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $event->eventId);
    self::assertSame('2026-09-09 16:20:30.123456', $event->recordedAt->format('Y-m-d H:i:s.u'));
    self::assertSame('corr-404', $event->correlationId);
    self::assertSame('webmcp', $event->surface);
    self::assertSame($definition->id, $event->actionId);
    self::assertSame($definition->version, $event->actionVersion);
    self::assertSame(AuditOutcomeKind::Completed, $event->outcomeKind);
    self::assertNull($event->haltedAt);
    self::assertNull($event->haltCode);
    self::assertFalse($event->humanConfirmationPresent);
    self::assertCount(1, $event->trustedContextManifest);
    self::assertSame(ContextRequirement::Tenant, $event->trustedContextManifest[0]->requirement);
    self::assertSame('tenant.provider', $event->trustedContextManifest[0]->provider);
}
```

Also add a helper that reflects public `AuditEvent` properties or converts only those properties to scalars and asserts none of these markers occur: `SECRET_INPUT`, `SECRET_OUTPUT`, `SECRET_METADATA`, `SECRET_IDEMPOTENCY_KEY`, `SECRET_BINDING`, `SECRET_RECEIPT`, `SECRET_TENANT_VALUE`, `SECRET_PROVENANCE_REFERENCE`, `SECRET_SCOPE_KEY`, `SECRET_EXTENSION`.

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/AuditEventFactoryTest.php
```

Expected: FAIL because `SurfaceRelay\Laravel\Audit\AuditEventFactory`, `AuditEvent`, clock types and outcome enum do not exist.

- [ ] **Step 3: Implement the minimal semantic types**

Create the enum and narrow value objects:

```php
enum AuditOutcomeKind: string
{
    case Completed = 'completed';
    case Halted = 'halted';
}
```

```php
final readonly class AuditTrustedContextEntry
{
    public function __construct(
        public ContextRequirement $requirement,
        public string $provider,
    ) {
        if ($this->provider === '') {
            throw new InvalidArgumentException('Audit trusted-context provider must be non-empty.');
        }
    }
}
```

`AuditEvent` constructor must be explicit, typed and cross-field safe:

```php
final readonly class AuditEvent
{
    /** @param list<AuditTrustedContextEntry> $trustedContextManifest */
    public function __construct(
        public string $eventId,
        public DateTimeImmutable $recordedAt,
        public string $correlationId,
        public string $surface,
        public string $actionId,
        public int $actionVersion,
        public ActionScope $actionScope,
        public ActionEffect $actionEffect,
        public ActionRisk $actionRisk,
        public IdempotencyPolicy $idempotencyPolicy,
        public OutputSensitivity $outputSensitivity,
        public OutputContentTrust $outputContentTrust,
        public AuditOutcomeKind $outcomeKind,
        public ?ActionPipelineStage $haltedAt,
        public ?string $haltCode,
        public bool $humanConfirmationPresent,
        public array $trustedContextManifest,
    ) {
        if (preg_match('/^[0-9a-f]{32}$/', $this->eventId) !== 1) {
            throw new InvalidArgumentException('Audit eventId must be 32 lowercase hexadecimal characters.');
        }
        if ($this->recordedAt->getTimezone()->getName() !== 'UTC') {
            throw AuditConfigurationViolation::clockMustReturnUtc();
        }
        if ($this->correlationId === '' || $this->surface === '') {
            throw new InvalidArgumentException('Audit invocation labels must be non-empty.');
        }
        if ($this->outcomeKind === AuditOutcomeKind::Completed
            && ($this->haltedAt !== null || $this->haltCode !== null)) {
            throw new InvalidArgumentException('Completed audit event cannot carry halt data.');
        }
        if ($this->outcomeKind === AuditOutcomeKind::Halted
            && ($this->haltCode === null || $this->haltCode === '')) {
            throw new InvalidArgumentException('Halted audit event requires a non-empty halt code.');
        }
    }
}
```

Clock contracts:

```php
interface AuditClock
{
    public function now(): DateTimeImmutable;
}

final readonly class SystemAuditClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
```

Static configuration error:

```php
final class AuditConfigurationViolation extends RuntimeException
{
    public static function clockMustReturnUtc(): self
    {
        return new self('Audit clock must return a UTC DateTimeImmutable.');
    }
}
```

- [ ] **Step 4: Implement the factory as a strict final-state allowlist**

The factory deliberately accepts only the outcome, not `ActionCall`:

```php
final readonly class AuditEventFactory
{
    public function __construct(private AuditClock $clock) {}

    public function create(ActionPipelineOutcome $outcome): AuditEvent
    {
        $state = $outcome->state;
        $context = $state->context;
        $definition = $state->definition;
        $recordedAt = $this->clock->now();

        if ($recordedAt->getTimezone()->getName() !== 'UTC') {
            throw AuditConfigurationViolation::clockMustReturnUtc();
        }

        $manifest = array_map(
            static fn (TrustedContextEntry $entry): AuditTrustedContextEntry =>
                new AuditTrustedContextEntry($entry->requirement, $entry->provenance->provider),
            $context->allTrusted(),
        );

        return new AuditEvent(
            eventId: bin2hex(random_bytes(16)),
            recordedAt: $recordedAt,
            correlationId: $context->correlationId,
            surface: $context->surface,
            actionId: $definition->id,
            actionVersion: $definition->version,
            actionScope: $definition->scope,
            actionEffect: $definition->effect,
            actionRisk: $definition->risk,
            idempotencyPolicy: $definition->idempotency,
            outputSensitivity: $definition->outputSensitivity,
            outputContentTrust: $definition->outputContentTrust,
            outcomeKind: $outcome->completed ? AuditOutcomeKind::Completed : AuditOutcomeKind::Halted,
            haltedAt: $outcome->haltedAt,
            haltCode: $outcome->halt?->code,
            humanConfirmationPresent: $context->has(ContextRequirement::HumanConfirmation),
            trustedContextManifest: $manifest,
        );
    }
}
```

Do not read `$state->input`, `$state->output`, `$state->bindingId`, `$state->confirmationReceipt`, idempotency plan internals, trusted values, provenance references, scope keys, schemas/extensions or halt details.

- [ ] **Step 5: Add halted/pre-stage/confirmation/UTC negative tests**

Add tests proving:

```php
public function test_halted_event_records_stage_and_code_but_not_details(): void
{
    $outcome = ActionPipelineOutcome::halted(
        $this->stateWithTrustedConfirmation(),
        ActionPipelineStage::OutputPolicy,
        new ActionPipelineHalt('output_policy_failed', ['secret' => 'SECRET_HALT_DETAILS']),
    );

    $event = $this->factory()->create($outcome);

    self::assertSame(AuditOutcomeKind::Halted, $event->outcomeKind);
    self::assertSame(ActionPipelineStage::OutputPolicy, $event->haltedAt);
    self::assertSame('output_policy_failed', $event->haltCode);
    self::assertTrue($event->humanConfirmationPresent);
}
```

and:

```php
public function test_pre_stage_context_halt_preserves_null_halted_at(): void
{
    $event = $this->factory()->create(ActionPipelineOutcome::halted(
        $this->state(),
        null,
        new ActionPipelineHalt('required_context_missing'),
    ));

    self::assertNull($event->haltedAt);
    self::assertSame('required_context_missing', $event->haltCode);
}
```

Non-UTC clock:

```php
$this->expectException(AuditConfigurationViolation::class);
(new AuditEventFactory(new FixedAuditClock(
    new DateTimeImmutable('2026-09-09 19:20:30', new DateTimeZone('Europe/Istanbul')),
)))->create(ActionPipelineOutcome::completed($this->state()));
```

- [ ] **Step 6: Run focused tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/AuditEventFactoryTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit Task 1**

```bash
git add packages/laravel/src/Audit/AuditOutcomeKind.php \
        packages/laravel/src/Audit/AuditTrustedContextEntry.php \
        packages/laravel/src/Audit/AuditEvent.php \
        packages/laravel/src/Audit/AuditClock.php \
        packages/laravel/src/Audit/SystemAuditClock.php \
        packages/laravel/src/Audit/AuditConfigurationViolation.php \
        packages/laravel/src/Audit/AuditEventFactory.php \
        packages/laravel/tests/Unit/AuditEventFactoryTest.php
git commit -m "feat(laravel): add structured audit event projection"
```

---

### Task 2: Append-only Auditor and Store Port

**Files:**
- Create: `packages/laravel/src/Audit/AuditEventStore.php`
- Create: `packages/laravel/src/Audit/StructuredActionPipelineAuditor.php`
- Modify: `packages/laravel/src/Runtime/Pipeline/ActionPipelineAuditor.php`
- Test: `packages/laravel/tests/Unit/StructuredActionPipelineAuditorTest.php`

**Interfaces:**
- Consumes: `AuditEventFactory::create(ActionPipelineOutcome): AuditEvent` from Task 1.
- Produces: `AuditEventStore::append(AuditEvent $event): void`.
- Produces: `StructuredActionPipelineAuditor::__construct(AuditEventFactory $factory, AuditEventStore $store)` implementing the unchanged `ActionPipelineAuditor` port.

- [ ] **Step 1: Write RED auditor tests**

Use an in-memory append spy:

```php
final class RecordingAuditEventStore implements AuditEventStore
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}
```

Completed path test:

```php
public function test_record_appends_exactly_one_event_for_one_finalizer_call(): void
{
    $store = new RecordingAuditEventStore();
    $auditor = new StructuredActionPipelineAuditor($this->factory(), $store);
    $outcome = ActionPipelineOutcome::completed($this->state());

    $auditor->record($this->call(), $outcome);

    self::assertCount(1, $store->events);
    self::assertSame('completed', $store->events[0]->outcomeKind->value);
}
```

Failure propagation spy:

```php
final class ThrowingAuditEventStore implements AuditEventStore
{
    public function __construct(private readonly RuntimeException $failure) {}

    public function append(AuditEvent $event): void
    {
        throw $this->failure;
    }
}
```

```php
public function test_store_failure_propagates_unchanged(): void
{
    $failure = new RuntimeException('test-store-failure');
    $auditor = new StructuredActionPipelineAuditor(
        $this->factory(),
        new ThrowingAuditEventStore($failure),
    );

    try {
        $auditor->record($this->call(), ActionPipelineOutcome::completed($this->state()));
        self::fail('Store failure must propagate.');
    } catch (RuntimeException $caught) {
        self::assertSame($failure, $caught);
    }
}
```

- [ ] **Step 2: Run focused test and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/StructuredActionPipelineAuditorTest.php
```

Expected: FAIL because store/auditor types do not exist.

- [ ] **Step 3: Implement append-only port and structured auditor**

```php
interface AuditEventStore
{
    public function append(AuditEvent $event): void;
}
```

```php
final readonly class StructuredActionPipelineAuditor implements ActionPipelineAuditor
{
    public function __construct(
        private AuditEventFactory $factory,
        private AuditEventStore $store,
    ) {}

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        // ActionCall remains in the public port for compatibility, but is not
        // forwarded into the semantic factory. This keeps raw caller-only
        // input/binding/receipt candidates outside the projection API.
        $this->store->append($this->factory->create($outcome));
    }
}
```

Update only the stale docblock on `ActionPipelineAuditor`: remove “Persistence belongs to later T-404” and state that implementations may persist final outcomes; do not alter the method signature.

- [ ] **Step 4: Add halted and no-mutation tests**

Prove a halted finalizer call appends one halted event and the auditor returns `void` without altering the supplied outcome object/state. Assert the same outcome still has its original halt/state after `record()`.

- [ ] **Step 5: Run Task 1 + Task 2 tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/AuditEventFactoryTest.php tests/Unit/StructuredActionPipelineAuditorTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit Task 2**

```bash
git add packages/laravel/src/Audit/AuditEventStore.php \
        packages/laravel/src/Audit/StructuredActionPipelineAuditor.php \
        packages/laravel/src/Runtime/Pipeline/ActionPipelineAuditor.php \
        packages/laravel/tests/Unit/StructuredActionPipelineAuditorTest.php
git commit -m "feat(laravel): add append-only structured auditor"
```

---

### Task 3: Durable Database Audit Store and Migration

**Files:**
- Create: `packages/laravel/src/Audit/AuditStoreUnavailable.php`
- Create: `packages/laravel/src/Audit/DatabaseAuditEventStore.php`
- Create: `packages/laravel/database/migrations/0000_00_00_000001_create_surfacerelay_audit_events.php`
- Test: `packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php`

**Interfaces:**
- Consumes: `AuditEventStore` and `AuditEvent` from Tasks 1–2.
- Produces: `DatabaseAuditEventStore::__construct(ConnectionInterface $connection, string $table = 'surfacerelay_audit_events')`.
- Produces persistence row with domain-separated `correlation_hash` and nullable `halt_code_hash` derived only inside the database adapter.

- [ ] **Step 1: Write RED database-store projection tests using SQLite**

Create the exact schema in a test helper and append one completed event:

```php
public function test_append_inserts_exact_allowlisted_row_and_index_hashes(): void
{
    $connection = $this->connection();
    $store = new DatabaseAuditEventStore($connection);
    $event = $this->completedEvent(
        eventId: str_repeat('a', 32),
        correlationId: 'corr-unbounded-value',
    );

    $store->append($event);

    $row = $connection->table(self::TABLE)->where('event_id', $event->eventId)->first();
    self::assertNotNull($row);
    self::assertSame(
        hash('sha256', "surfacerelay.audit.correlation.v1\n" . $event->correlationId),
        $row->correlation_hash,
    );
    self::assertNull($row->halt_code);
    self::assertNull($row->halt_code_hash);
    self::assertSame('2026-09-09 16:20:30.123456', $row->recorded_at);
    self::assertSame('completed', $row->outcome_kind);
}
```

Halted event hash assertion:

```php
self::assertSame(
    hash('sha256', "surfacerelay.audit.halt-code.v1\noutput_policy_failed"),
    $row->halt_code_hash,
);
```

- [ ] **Step 2: Run focused store test and verify RED**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/DatabaseAuditEventStoreTest.php
```

Expected: FAIL because database adapter/error/migration do not exist.

- [ ] **Step 3: Implement static safe persistence error**

```php
final class AuditStoreUnavailable extends RuntimeException
{
    public static function operationFailed(): self
    {
        return new self('Audit store operation failed.');
    }

    public static function encodingFailed(): self
    {
        return new self('Audit event encoding failed.');
    }
}
```

Never pass a previous exception into these constructors.

- [ ] **Step 4: Implement one-insert database adapter**

Map the semantic event explicitly:

```php
final readonly class DatabaseAuditEventStore implements AuditEventStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'surfacerelay_audit_events',
    ) {}

    public function append(AuditEvent $event): void
    {
        try {
            $manifest = json_encode(
                array_map(
                    static fn (AuditTrustedContextEntry $entry): array => [
                        'requirement' => $entry->requirement->value,
                        'provider' => $entry->provider,
                    ],
                    $event->trustedContextManifest,
                ),
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw AuditStoreUnavailable::encodingFailed();
        }

        $row = [
            'event_id' => $event->eventId,
            'recorded_at' => $event->recordedAt->format('Y-m-d H:i:s.u'),
            'correlation_id' => $event->correlationId,
            'correlation_hash' => hash('sha256', "surfacerelay.audit.correlation.v1\n" . $event->correlationId),
            'surface' => $event->surface,
            'action_id' => $event->actionId,
            'action_version' => $event->actionVersion,
            'action_scope' => $event->actionScope->value,
            'action_effect' => $event->actionEffect->value,
            'action_risk' => $event->actionRisk->value,
            'idempotency_policy' => $event->idempotencyPolicy->value,
            'output_sensitivity' => $event->outputSensitivity->value,
            'output_content_trust' => $event->outputContentTrust->value,
            'outcome_kind' => $event->outcomeKind->value,
            'halted_at' => $event->haltedAt?->value,
            'halt_code' => $event->haltCode,
            'halt_code_hash' => $event->haltCode === null
                ? null
                : hash('sha256', "surfacerelay.audit.halt-code.v1\n" . $event->haltCode),
            'human_confirmation_present' => $event->humanConfirmationPresent,
            'trusted_context_manifest' => $manifest,
        ];

        try {
            $this->connection->table($this->table)->insert($row);
        } catch (QueryException) {
            throw AuditStoreUnavailable::operationFailed();
        }
    }
}
```

Do not use upsert/update/replace/insertOrIgnore.

- [ ] **Step 5: Write and implement static-error/collision tests**

Missing-table test must prove no SQL/table data leaks and no chained driver exception:

```php
try {
    (new DatabaseAuditEventStore($connection, 'SECRET_MISSING_TABLE'))->append($event);
    self::fail('Database failure must propagate as safe audit error.');
} catch (AuditStoreUnavailable $e) {
    self::assertNull($e->getPrevious());
    self::assertSame('Audit store operation failed.', $e->getMessage());
    self::assertStringNotContainsString('SECRET_MISSING_TABLE', $e->getMessage());
}
```

Collision test:

```php
$store->append($first);
$this->expectException(AuditStoreUnavailable::class);
$store->append($secondWithSameEventId);
```

After catching, fetch the row and assert all first-event columns are unchanged.

For JSON encoding failure, create an `AuditTrustedContextEntry` provider containing invalid UTF-8 bytes such as `"provider-\xB1"`, append, and assert static `Audit event encoding failed.` with no previous exception.

- [ ] **Step 6: Add package migration with exact schema and indexes**

Create `0000_00_00_000001_create_surfacerelay_audit_events.php`:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surfacerelay_audit_events', function (Blueprint $table): void {
            $table->char('event_id', 32)->primary();
            $table->dateTime('recorded_at', precision: 6)->index();
            $table->longText('correlation_id');
            $table->char('correlation_hash', 64)->index();
            $table->longText('surface');
            $table->string('action_id', 160);
            $table->unsignedInteger('action_version');
            $table->string('action_scope', 32);
            $table->string('action_effect', 32);
            $table->string('action_risk', 32);
            $table->string('idempotency_policy', 32);
            $table->string('output_sensitivity', 32);
            $table->string('output_content_trust', 64);
            $table->string('outcome_kind', 16);
            $table->string('halted_at', 32)->nullable();
            $table->longText('halt_code')->nullable();
            $table->char('halt_code_hash', 64)->nullable();
            $table->boolean('human_confirmation_present');
            $table->json('trusted_context_manifest');

            $table->index(
                ['action_id', 'action_version', 'recorded_at'],
                'sr_audit_action_recorded_idx',
            );
            $table->index(
                ['outcome_kind', 'halt_code_hash', 'recorded_at'],
                'sr_audit_outcome_halt_recorded_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surfacerelay_audit_events');
    }
};
```

Use `DATETIME(6)`, not timestamp/session-timezone-dependent semantics.

- [ ] **Step 7: Add migration exact-column/index round-trip test**

Follow the existing `DatabaseIdempotencyStoreTest` pattern: mount the package migration through `Facade::setFacadeApplication`, run `up()`, assert the exact column listing, assert no forbidden column names (`input`, `output`, `metadata`, `idempotency_key`, `binding_id`, `confirmation_receipt`, `actor_id`, `tenant_id`), then run `down()`.

For MySQL when `SURFACERELAY_TEST_MYSQL_HOST` is available, run migration, append an event with `2026-09-09 16:20:30.123456` UTC, fetch the row, and assert the exact microsecond string survives.

- [ ] **Step 8: Run database-store test and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Unit/DatabaseAuditEventStoreTest.php
```

Expected: PASS locally; MySQL-specific test skips only when the integration environment is absent. CI matrix must execute it.

- [ ] **Step 9: Commit Task 3**

```bash
git add packages/laravel/src/Audit/AuditStoreUnavailable.php \
        packages/laravel/src/Audit/DatabaseAuditEventStore.php \
        packages/laravel/database/migrations/0000_00_00_000001_create_surfacerelay_audit_events.php \
        packages/laravel/tests/Unit/DatabaseAuditEventStoreTest.php
git commit -m "feat(laravel): persist append-only audit events"
```

---

### Task 4: Real Pipeline Trust-Boundary Integration

**Files:**
- Create: `packages/laravel/tests/Integration/StructuredAuditPipelineIntegrationTest.php`
- Modify only if fixture wiring requires it: `packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php`

**Interfaces:**
- Consumes: production `StructuredActionPipelineAuditor`, `AuditEventFactory`, in-memory `AuditEventStore` test double, real `ActionBus` stages including confirmation and output policy.
- Produces evidence that T-404 observes current final-state semantics without changing existing pipeline behavior.

- [ ] **Step 1: Write RED real-ActionBus completed/halted audit integration tests**

Build the bus using the same production stage classes used by existing integration tests and inject:

```php
$events = new RecordingAuditEventStore();
$auditor = new StructuredActionPipelineAuditor(
    new AuditEventFactory(new FixedAuditClock($this->utcNow())),
    $events,
);
```

Successful invocation assertion:

```php
$outcome = $bus->dispatch($call);

self::assertTrue($outcome->completed);
self::assertCount(1, $events->events);
self::assertSame(AuditOutcomeKind::Completed, $events->events[0]->outcomeKind);
self::assertSame($call->context->correlationId, $events->events[0]->correlationId);
```

Missing trusted-context halt assertion:

```php
self::assertFalse($outcome->completed);
self::assertNull($outcome->haltedAt);
self::assertSame('required_context_missing', $events->events[0]->haltCode);
self::assertNull($events->events[0]->haltedAt);
```

- [ ] **Step 2: Run integration test and verify initial RED/coverage gaps**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/StructuredAuditPipelineIntegrationTest.php
```

Expected before wiring helpers are complete: FAIL in the new fixture/test setup, not by weakening production semantics.

- [ ] **Step 3: Add consequential confirmation integration proof**

Use the existing confirmation service/stage pattern to issue/consume a real receipt. The fresh confirmed invocation must finalize with:

```php
self::assertTrue($event->humanConfirmationPresent);
self::assertContains(
    ContextRequirement::HumanConfirmation,
    array_map(static fn (AuditTrustedContextEntry $entry) => $entry->requirement, $event->trustedContextManifest),
);
```

Then assert the matching manifest entry provider is exactly `surfacerelay.confirmation` and contains no receipt/challenge/scope data because the event type has no such fields.

- [ ] **Step 4: Add T-403 output-policy failure and sensitive-release proofs**

For sensitive output with a redactor that withholds or throws, dispatch through the real `OutputPolicyStage` and assert:

```php
self::assertFalse($outcome->completed);
self::assertSame(ActionPipelineStage::OutputPolicy, $event->haltedAt);
self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $event->haltCode);
```

Place `SECRET_RAW_OUTPUT` in executor output and assert the semantic `AuditEvent` serialization helper contains no marker.

For a successful sensitive redactor returning `OutputRedactionResult::release(['safe' => 'SECRET_RELEASED_OUTPUT'])`, assert the pipeline completes but the audit event still contains no released-output marker.

- [ ] **Step 5: Prove audit-store failure changes returnability but not already-executed side effect**

Inject an `AuditEventStore` that throws a fixed exception and an executor incrementing `$executions`:

```php
try {
    $bus->dispatch($call);
    self::fail('Audit persistence failure must escape finalization.');
} catch (RuntimeException $e) {
    self::assertSame(1, $executions);
}
```

Do not add rollback or retry behavior to `ActionBus`.

- [ ] **Step 6: Run integration test and existing ActionBus tests**

```bash
cd packages/laravel
./vendor/bin/phpunit \
  tests/Integration/StructuredAuditPipelineIntegrationTest.php \
  tests/Unit/ActionBusTest.php
```

Expected: PASS, including existing exactly-once finalizer order tests.

- [ ] **Step 7: Commit Task 4**

```bash
git add packages/laravel/tests/Integration/StructuredAuditPipelineIntegrationTest.php \
        packages/laravel/tests/Fixtures/PrepList/PrepListTestPipeline.php
git commit -m "test(laravel): verify structured audit pipeline boundary"
```

If `PrepListTestPipeline.php` was not needed, do not touch or stage it.

---

### Task 5: Idempotency Replay and Durable Secrecy Proof

**Files:**
- Create: `packages/laravel/tests/Integration/StructuredAuditIdempotencyReplayIntegrationTest.php`
- Create: `packages/laravel/tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php`

**Interfaces:**
- Consumes: T-402 `IdempotencyService`/database store/stages, T-403 `OutputPolicyStage`, T-404 database auditor/store.
- Produces end-to-end proof that two finalized invocations can yield two audit events while only one protected application execution occurs, and that durable storage excludes every forbidden marker.

- [ ] **Step 1: Write RED replay integration test**

Adapt the existing `OutputPolicyIdempotencyReplayIntegrationTest` wiring rather than inventing new idempotency semantics. Use one `required_key` or `recommended_key` protected write and a counting executor:

```php
$first = $bus->dispatch($this->call('corr-first', 'retry-key'));
$second = $bus->dispatch($this->call('corr-replay', 'retry-key'));

self::assertTrue($first->completed);
self::assertTrue($second->completed);
self::assertSame(1, $executorCalls);
self::assertCount(2, $auditStore->events);
self::assertSame('corr-first', $auditStore->events[0]->correlationId);
self::assertSame('corr-replay', $auditStore->events[1]->correlationId);
self::assertFalse($auditStore->events[1]->humanConfirmationPresent);
```

For a consequential action, first invocation may show confirmation presence while exact completed replay must show false because replay skips confirmation and must not manufacture authority.

- [ ] **Step 2: Run replay test and verify RED before final fixture alignment**

```bash
cd packages/laravel
./vendor/bin/phpunit tests/Integration/StructuredAuditIdempotencyReplayIntegrationTest.php
```

Expected: new test initially fails until all T-404 wiring is correctly composed; production T-402 execution count must never be loosened to make it pass.

- [ ] **Step 3: Complete replay wiring with current T-402/T-403 contracts only**

Reuse existing idempotency migration/store/service, current confirmation semantics and current output policy. Do not add audit IDs to idempotency records, do not persist replay payload in audit, and do not extend idempotency retention.

- [ ] **Step 4: Write RED durable adversarial secrecy test**

Create an ActionCall/final outcome containing unique markers across all reachable forbidden sources:

```text
SECRET_RAW_INPUT
SECRET_VALIDATED_INPUT
SECRET_NORMAL_OUTPUT
SECRET_RELEASED_OUTPUT
SECRET_METADATA
SECRET_RAW_IDEMPOTENCY_KEY
SECRET_BINDING_ID
SECRET_CONFIRMATION_RECEIPT
SECRET_TRUSTED_ACTOR
SECRET_TRUSTED_TENANT
SECRET_TRUSTED_RECORD
SECRET_TRUSTED_SELECTION
SECRET_BROWSER_SESSION
SECRET_PROVENANCE_REFERENCE
SECRET_SCOPE_KEY
SECRET_HALT_DETAILS
SECRET_SCHEMA
SECRET_EXTENSION
```

Persist through `StructuredActionPipelineAuditor + DatabaseAuditEventStore`, then fetch the inserted row and build one string from every scalar column plus `trusted_context_manifest`:

```php
$persisted = json_encode((array) $row, JSON_THROW_ON_ERROR);
foreach ($forbiddenMarkers as $marker) {
    self::assertStringNotContainsString($marker, $persisted);
}
```

At the same time assert allowed provider labels and requirement names remain present.

- [ ] **Step 5: Add direct schema-level forbidden-column assertions**

Using the migration-created table, assert the column list contains none of:

```php
[
    'input', 'validated_input', 'output', 'metadata',
    'idempotency_key', 'idempotency_lookup_hash', 'intent_fingerprint',
    'binding_id', 'confirmation_receipt', 'challenge_id',
    'actor_id', 'tenant_id', 'current_record', 'current_selection',
    'browser_session', 'provenance_reference', 'scope_key', 'halt_details',
]
```

This catches future schema drift even if a test fixture does not populate a newly added forbidden column.

- [ ] **Step 6: Run replay + secrecy integration tests and verify GREEN**

```bash
cd packages/laravel
./vendor/bin/phpunit \
  tests/Integration/StructuredAuditIdempotencyReplayIntegrationTest.php \
  tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit Task 5**

```bash
git add packages/laravel/tests/Integration/StructuredAuditIdempotencyReplayIntegrationTest.php \
        packages/laravel/tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php
git commit -m "test(laravel): prove audit replay and secrecy invariants"
```

---

### Task 6: Full Regression, Scope Audit, Status and Review Preparation

**Files:**
- Modify: `STATUS.md`
- Modify: `TASKS.md`
- Modify: `REVIEW_REQUEST.md`
- Verify only: `spec/0.1/**`
- Verify all production/test files from Tasks 1–5.

**Interfaces:**
- Consumes: complete T-404 implementation and CI evidence.
- Produces: exact review checkpoint with no merge; T-404 may be marked DONE only after full acceptance evidence exists.

- [ ] **Step 1: Run all focused T-404 tests**

```bash
cd packages/laravel
./vendor/bin/phpunit \
  tests/Unit/AuditEventFactoryTest.php \
  tests/Unit/StructuredActionPipelineAuditorTest.php \
  tests/Unit/DatabaseAuditEventStoreTest.php \
  tests/Integration/StructuredAuditPipelineIntegrationTest.php \
  tests/Integration/StructuredAuditIdempotencyReplayIntegrationTest.php \
  tests/Integration/StructuredAuditPersistenceSecrecyIntegrationTest.php
```

Expected: PASS with zero failures/errors.

- [ ] **Step 2: Run the complete PHP suite**

```bash
cd packages/laravel
composer test
```

Expected: PASS. Record exact test/assertion counts in `STATUS.md` and `REVIEW_REQUEST.md`; do not pre-fill counts before this run.

- [ ] **Step 3: Run PHP lint exactly as CI does**

Use the repository workflow command from `.github/workflows/validate.yml` rather than inventing a different lint scope. Expected: all PHP sources lint cleanly.

- [ ] **Step 4: Run browser isolation regression**

```bash
cd browser-runtime
npm ci
npm run typecheck
npm test -- --run
```

Expected: typecheck PASS and all Vitest tests PASS; T-404 must not require browser changes.

- [ ] **Step 5: Run frozen contract validation**

From repository root:

```bash
python scripts/validate.py
```

Expected: PASS.

- [ ] **Step 6: Prove frozen spec has no diff and branch is ahead-only**

```bash
git diff main...HEAD -- spec/0.1
git rev-list --left-right --count main...HEAD
git diff --name-status main...HEAD
```

Expected:

```text
spec/0.1 diff: empty
branch: 0 behind main
```

Review changed files manually and reject unrelated refactors, telemetry/export/query/retention work, or business-payload persistence.

- [ ] **Step 7: Perform acceptance checklist against D-047**

Explicitly verify each statement from the design:

```text
[ ] one immutable semantic AuditEvent
[ ] exact completed/halted semantics
[ ] final trusted-context manifest = requirement + provider only
[ ] human confirmation = boolean only
[ ] no input/output/business identity/capability persistence
[ ] append-only write API only
[ ] persistence-only index hashes
[ ] static non-chained DB/JSON failures
[ ] no arbitrary Throwable audit claim
[ ] replay makes a second audit event but not a second execution
[ ] T-403 output cannot leak through audit
[ ] ActionPipelineAuditor signature unchanged
[ ] spec/0.1 unchanged
```

If any box cannot be supported by a test/diff/runtime artifact, do not mark T-404 done; add the smallest missing focused test/fix first.

- [ ] **Step 8: Update status/review docs with exact evidence**

`STATUS.md` must state:

```text
Current task: T-404 — Structured audit events — DONE / IMPLEMENTED, review pending
Decision: D-047 — ACCEPTED
Base: main@a3112dea3f965e27db8e6904650c97f89d2261fe
Exact code checkpoint: <actual HEAD>
PHP: <actual tests/assertions>
Browser: <actual typecheck/Vitest count>
Contract: green; spec/0.1 unchanged
Merge: not requested; separate explicit gate
```

`TASKS.md`: change only the T-404 operational task marker from TODO to DONE after evidence exists; do not rewrite historical T-401/T-402/T-403 evidence.

`REVIEW_REQUEST.md`: replace prior T-403 final record with a T-404 review request containing exact base/head, security invariants, test counts, MySQL matrix evidence, secrecy proof focus and explicit statement that merge remains a separate gate.

- [ ] **Step 9: Commit documentation-only review checkpoint**

```bash
git add STATUS.md TASKS.md REVIEW_REQUEST.md
git commit -m "docs(status): prepare T-404 review checkpoint"
```

- [ ] **Step 10: Push and verify exact-head GitHub Actions CI**

Push `feat/structured-audit-events`, retrieve the workflow associated with the exact head SHA, and require all seven jobs:

```text
contract
browser
php-lint
php-tests PHP 8.3 / Illuminate 12
php-tests PHP 8.3 / Illuminate 13
php-tests PHP 8.4 / Illuminate 12
php-tests PHP 8.4 / Illuminate 13
```

Expected: **7/7 success**. Do not rely on an older green checkpoint.

- [ ] **Step 11: Open/update the T-404 PR without merging**

PR title:

```text
feat(laravel): add structured audit event persistence
```

PR body must summarize D-047, payload minimization, append-only persistence, static failure semantics, finalization/Throwable boundary, replay semantics, exact CI evidence and `spec/0.1` no-diff. Explicitly state that merge is a separate gate.

- [ ] **Step 12: Request external review and stop at the review gate**

Trigger CodeRabbit/manual review if automatic review is skipped. Resolve actionable findings through focused RED→GREEN changes, rerun exact-head CI, update review evidence, and stop before merge until explicit merge authorization is given.

---

## Plan Self-Review

### Spec coverage

- Event model, UTC clock, opaque ID: Task 1.
- Explicit semantic allowlist and trusted-context/confirmation minimization: Task 1.
- Existing auditor port compatibility and exactly-one append adapter: Task 2.
- Append-only store, database schema, index-helper hashes, static safe failures: Task 3.
- Completed/halted/pre-stage/confirmation/output-policy failure semantics: Task 4.
- T-402 replay and durable forbidden-marker proof: Task 5.
- Frozen contracts, full regression, exact-head CI and review gate: Task 6.
- Non-goals remain absent from every task.

### Placeholder scan

No implementation step uses `TBD`, `TODO`, “similar to another task”, unspecified error handling, or unspecified test behavior. Actual final test/assertion counts and final HEAD are intentionally recorded only after executing verification because inventing them in advance would violate evidence-before-claim requirements.

### Type consistency

- `AuditEventFactory::create(ActionPipelineOutcome $outcome): AuditEvent` is used consistently in Tasks 1–2.
- `AuditEventStore::append(AuditEvent $event): void` is used consistently in Tasks 2–5.
- `StructuredActionPipelineAuditor` preserves the existing `ActionPipelineAuditor::record(ActionCall, ActionPipelineOutcome): void` method.
- Database-only hashes do not appear in the `AuditEvent` constructor or factory output.
- `trustedContextManifest` remains `list<AuditTrustedContextEntry>` end-to-end and is converted to JSON only in `DatabaseAuditEventStore`.
- `recordedAt` remains `DateTimeImmutable` in the semantic event and becomes an explicit UTC microsecond string only at persistence projection.

## Execution Boundary

Do not merge this branch as part of plan execution. Successful implementation ends at exact-head green CI + external review completion; merge remains an explicit separate user gate.
