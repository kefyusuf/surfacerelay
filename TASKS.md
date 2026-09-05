# Implementation Task Board

Tasks are deliberately small so coding agents can complete, verify, and hand off one coherent change at a time.

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

Do not mark a task `DONE` until its verification commands pass and `STATUS.md` / `REVIEW_REQUEST.md` are updated.

## M0 — Contract foundation (DONE)

### T-005 — Align Invocation ActionReference identity constraints — DONE

**Goal:** Invocation `action.id` uses the canonical Action ID grammar; all three ActionIdentity locations (`definition`, `binding.action`, `invocation.action`) share `id` (type/pattern/maxLength) and `version` (type/minimum) constraints, enforced by a generalized validator drift guard.

**Changed:** `spec/0.1/invocation.schema.json`, `scripts/validate.py` (generalized identity guard over three locations), `spec/0.1/fixtures/invocation/invalid/malformed-action-reference-id.json` (+ manifest entry), `docs/GLOSSARY.md`.

**Verify:**
```bash
python scripts/validate.py
```
Drift guard proven on Invocation: temporarily changing `invocation.action.id.maxLength` fails validation; restored.

**Status:** DONE (2026-09-05). M0 acceptance: vocabulary, fixture matrix, binding lifecycle semantics, and Action Identity consistency locked and verified.

---

### T-004 — Align ActionReference identity constraints — DONE

**Goal:** Runtime Binding `action.id` uses the exact same lexical grammar as `ActionDefinition.id` (`type`/`pattern`/`maxLength`), with a validator drift guard.

**Changed:** `spec/0.1/runtime-binding.schema.json` (ActionReference id constraints + description), `scripts/validate.py` (grammar-consistency assertion comparing `type`, `pattern`, `maxLength` across the two schemas), `spec/0.1/fixtures/runtime-binding/invalid/malformed-action-reference-id.json` (+ manifest entry asserting `pattern` at `/action/id`), `docs/GLOSSARY.md` (ActionReference grammar note).

**Verify:**
```bash
python scripts/validate.py
```
Drift guard proven: temporarily changing one schema's `maxLength` makes validation fail; restored.

**Status:** DONE (2026-09-05). M0 acceptance: vocabulary, fixture matrix, and binding lifecycle semantics locked and verified.

---

### T-001 — Lock v0.1 semantic vocabulary — DONE

**Goal:** Confirm the minimum framework-neutral meanings for scope, effect, risk, idempotency, output trust, and context requirements.

**Primary files:**
- `docs/GLOSSARY.md`
- `docs/DECISION-REGISTER.md`
- `spec/0.1/action-definition.schema.json`

**Acceptance:**
- Every enum value has one clear meaning and at least one example.
- No value is named after WebMCP, MCP, Laravel, Livewire, Filament, HTMX, or HTTP.
- `consequential` risk is distinct from write/destructive effect.
- Context requirements distinguish trusted runtime context from caller input.

**Verify:**
```bash
python scripts/validate.py
```

**Stop after:** vocabulary/schema/docs are consistent. Do not implement runtime classes.

---

### T-002 — Add schema fixture matrix — DONE

**Goal:** Add valid and invalid fixtures for each v0.1 schema.

**Acceptance:**
- At least 3 valid and 5 invalid Action Definition fixtures.
- Invalid fixtures cover protocol fields in core, malformed IDs, invalid context, and unknown required fields.
- Validator asserts invalid fixtures fail for the intended reason.

**Verify:**
```bash
python scripts/validate.py
```

---

### T-003 — Lock Runtime Binding lifecycle semantics — DONE

**Goal:** Define binding identity, lifecycle, expiry, and stale-binding behavior without enumerating all future drivers.

**Acceptance:**
- Driver is extensible string/namespace, not a closed framework enum.
- Binding references action ID + version.
- Lifecycle/expiry semantics are explicit.
- Stale/unknown binding fails closed in conformance scenarios.

**Verify:**
```bash
python scripts/validate.py
```

---

## M1 — Laravel action kernel (DONE)

### T-110 — Implement normalized ActionResult/Error model — DONE

**Goal:** Stabilize success, validation failure, authorization rejection, confirmation-required, stale-binding, and internal-error envelopes.

**Implemented:**
- `packages/laravel/src/Result/` — `ActionResultStatus` (frozen 4-status enum), `ActionError` (extensible code namespace per D-030, verbatim strings, optional JSON-friendly details), `ConfirmationChallenge` (data model only; receipt semantics T-401), `ActionResult` (private constructor + status factories → ambiguous shapes unrepresentable; deterministic `toArray()`/JsonSerializable: `status, correlationId, data|error|confirmation, meta?`), `CoreActionErrorCode` (3 implemented codes only), `ActionResultNormalizer` (completed → succeeded; the three implemented halt codes → rejected with safe generic messages; unknown codes → `UnmappedPipelineOutcome` loud failure; no context auto-projection, no redaction, no fake confirmation).
- `packages/laravel/src/Runtime/Pipeline/ActionPipelineHalt.php` — structured halt (machine code + optional non-authoritative details) replacing free-form reason strings; `ActionPipelineDecision`/`ActionPipelineOutcome` migrated; T-106 kernel emits `required_context_missing` + `['requirements' => [...]]` in canonical order (never trusted values); T-107 emits `input_validation_failed` + unique lexical-order failing field names (no values/messages); T-109 emits `authorization_denied` with no details.
- Tests: 26 new (`ActionResultTest`, `ActionErrorTest`, `ConfirmationChallengeTest`, `ActionResultNormalizerTest`) incl. mandatory `data: null` presence regression, no-context-projection, safe-messages-only, unknown-halt loud failure, private-constructor invariant.

**Verify:**
```bash
cd packages/laravel
composer test -- --filter "ActionResult|ActionError|ActionResultNormalizer|ConfirmationChallenge"  # OK (26 tests, 65 assertions)
composer test -- --filter "ActionBus|LaravelInputValidation|Authorization"                          # OK (43 tests, 115 assertions)
composer test                       # OK (173 tests, 420 assertions)
composer validate --strict          # valid
python scripts/validate.py          # frozen contract green
```

**Status:** DONE (2026-09-06). **M1 — Laravel Kernel DONE.** D-030/D-031 ACCEPTED; D-026 remains PROPOSED (M2 binding codes unimplemented).

### T-101 — Implement ActionDefinition value object — DONE

**Goal:** Immutable PHP representation of the complete v0.1 Action Definition with no adapter dependencies.

**Implemented:**
- `packages/laravel/src/Definition/ActionDefinition.php` — `final readonly` value object covering all v0.1 fields; construction rejects ID grammar/length violations, `version < 1`, empty/oversized title/description, duplicate or non-enum context requirements, and malformed extension keys. Context requirements are canonicalized to enum-declaration order (caller order irrelevant). Schemas are preserved verbatim; no trimming, no JSON Schema compilation, no `additionalProperties` injection, no derived context from risk.
- `packages/laravel/src/Definition/InvalidActionDefinition.php` — dedicated domain exception (separate from the T-110 result taxonomy).
- `packages/laravel/src/Enums/ContextRequirement.php` — backed enum matching the frozen v0.1 vocabulary exactly.
- `packages/laravel/tests/Unit/ActionDefinitionTest.php` — 24 tests: 3 successful construction shapes + risk-does-not-mutate-context + verbatim schema/extension preservation, ID grammar data provider, version, text constraints, context requirement duplicates/arbitrary strings, extension keys, readonly reflection.
- Smoke test extended with `ContextRequirement` frozen-vocabulary assertion.
- `.gitignore` now ignores nested `vendor/` directories (created by `composer install`).

**Verify:**
```bash
cd packages/laravel && composer test -- --filter ActionDefinition  # OK (24 tests, 65 assertions)
composer test                                                      # OK (26 tests, 68 assertions)
composer validate --strict                                         # valid
python scripts/validate.py                                         # frozen contract still passes
```

**Status:** DONE (2026-09-05). M1 in progress.

---

### T-102 — Implement ActionRegistry — DONE

**Goal:** Register action class ↔ ActionDefinition mappings with duplicate detection.

**Implemented:**
- `packages/laravel/src/Contracts/ActionRegistry.php` — rewritten from the starter skeleton (which registered action *class strings*): the contract now stores canonical `ActionDefinition` instances with exact-identity semantics `register(ActionDefinition): void`, `has(id, version): bool`, `get(id, version): ActionDefinition` (throws, never null), `all(): list<ActionDefinition>`.
- `packages/laravel/src/Registry/InMemoryActionRegistry.php` — plain PHP implementation; no container/db/cache/filesystem/adapter dependencies. Internal `{id}@{version}` keying is private only; no version fallback; no latest-version resolution.
- `packages/laravel/src/Registry/DuplicateActionDefinition.php` — duplicate exact-identity registration fails loudly even when metadata is identical (identity determines uniqueness).
- `packages/laravel/src/Registry/ActionDefinitionNotFound.php` — missing exact identity fails loudly; `has()` is the non-throwing check.
- `packages/laravel/tests/Unit/InMemoryActionRegistryTest.php` — 9 tests / 27 assertions: exact lookup, coexisting versions, duplicate (identical + differing metadata), missing identity, no silent fallback v2→v1, deterministic `all()` (id ASC, version ASC) against shuffled registration, empty registry, returned-list mutation safety.

**Verify:**
```bash
cd packages/laravel
composer test -- --filter ActionRegistry   # OK (9 tests, 27 assertions)
composer test                              # OK (35 tests, 95 assertions)
composer validate --strict                 # valid
python scripts/validate.py                 # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. Discovery/attribute compilation (future) feeds this registry; nothing was auto-registered.

### T-103 — Implement scalar PHP input schema compiler — DONE

**Goal:** Compile scalar constructor/handler types into JSON Schema.

**Implemented:**
- `packages/laravel/src/Schema/PhpInputSchemaCompiler.php` — plain PHP reflection compiler (`compile(ReflectionFunctionAbstract): array`) covering `ReflectionFunction` and `ReflectionMethod`. Maps `string`→`string`, `int`→`integer`, `float`→`number`, `bool`→`boolean`. Nullable scalars emit `type: [T, "null"]` (Draft 2020-12 type arrays; explicit `T|null` unions accepted as equivalent to `?T`). Requiredness = no default value, independent of nullability. No `default` keyword, no docblock/attribute inference, declaration order preserved, `additionalProperties: false` emitted by design.
- `packages/laravel/src/Schema/UnsupportedParameterType.php` — loud rejection naming the parameter + reason for: untyped, variadic, non-nullable unions, intersections, classes/DTOs, enums (T-104 boundary enforced by test), and unsupported built-ins (e.g. `array`, `mixed`).
- `packages/laravel/tests/Unit/PhpInputSchemaCompilerTest.php` — 15 tests with exact-output assertions: empty signature (no `required` key), all four scalar mappings, reference example, required-nullable vs optional-nullable vs optional-non-nullable, declaration-order preservation via `ReflectionMethod`, and rejection of untyped/array/DTO/enum/union/intersection/variadic with parameter identification.

**Verify:**
```bash
cd packages/laravel
composer test -- --filter PhpInputSchemaCompiler  # OK (15 tests, 22 assertions)
composer test                                     # OK (50 tests, 117 assertions)
composer validate --strict                        # valid
python scripts/validate.py                        # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. No Laravel validation-rule parsing (that is T-107); enums are T-104.

---

### T-104 — Add enum and constrained-value schema compilation — DONE

**Goal:** Support backed enums and explicit description metadata.

**Implemented:**
- `packages/laravel/src/Schema/PhpInputSchemaCompiler.php` — extended: string/int backed enums compile to `type` + `enum` (case values in declaration order, never sorted); nullable enums satisfy both JSON Schema constraints (`type: [T, "null"]` AND `null` appended last to `enum`); explicit `BackedEnum|null` unions accepted as `?BackedEnum` equivalents; unit (non-backed) enums rejected loudly (no implicit case-name wire values); requiredness semantics unchanged from T-103 (no `default` emission).
- `packages/laravel/src/Attributes/InputDescription.php` — protocol-neutral `TARGET_PARAMETER` attribute; description preserved verbatim (never trimmed/rewritten/derived); no `description` key without the attribute; property key order deterministic: `type`, `enum`, `description`.
- `packages/laravel/src/Schema/UnsupportedParameterType.php` — `unitEnum` factory replacing the T-103 placeholder.
- `tests/Unit/PhpInputSchemaCompilerTest.php` — 13 new tests: string/int-backed enum exact output, required/optional nullable enums (null in both constraints), case-default enum without `default`, declaration-order proof (30, 10, 20), backed-enum+null explicit union, scalar/enum descriptions in deterministic order, verbatim preservation, no synthesized description; unit-enum and enum-involved-union rejections. All T-103 regression tests green.

**Verify:**
```bash
cd packages/laravel
composer test -- --filter PhpInputSchemaCompiler  # OK (28 tests, 37 assertions)
composer test                                     # OK (63 tests, 132 assertions)
composer validate --strict                        # valid
python scripts/validate.py                        # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress.

---

### T-105 — Implement InvocationContext trust boundary — DONE

**Goal:** Separate caller input from trusted runtime authority.

**Implemented:**
- `packages/laravel/src/Runtime/InvocationContext.php` — rewritten from the permissive starter scaffold: trusted authority is now a `ContextRequirement`-typed entry collection (`has/get/require/allTrusted`), no public `actor`/`tenant`/`currentRecord`/`currentSelection`/`confirmationReceipt` loose `mixed` properties, no payload-hydration API. `metadata` is explicitly non-authoritative with no fallback to trusted context; `surface`/`correlationId` are non-empty origin/diagnostic labels, never authorization; `idempotencyKey` remains invocation metadata, not a ContextRequirement and not authority.
- `packages/laravel/src/Runtime/Context/TrustedContextEntry.php` — requirement + non-null value + provenance; null values rejected (absence = entry absence, keeping resolved-empty `[]` distinct from "not resolved").
- `packages/laravel/src/Runtime/Context/ContextProvenance.php` — extensible open provider vocabulary (`provider` non-empty verbatim, optional `reference`); diagnostic, not authority.
- `packages/laravel/src/Runtime/Context/DuplicateTrustedContext.php` / `TrustedContextNotAvailable.php` — duplicates fail loudly (no first/last-wins/merge); strict `require()` fails closed.
- `tests/Unit/InvocationContextTest.php` — 16 tests / 54 assertions incl. the spoofing regressions: caller payload keys matching every canonical requirement name stay ordinary data; `metadata['tenant']` never satisfies `ContextRequirement::Tenant`; idempotency key and surface grant no requirement; empty `[]` selection is present; falsy values present; null rejected; declaration-order `allTrusted()`.

**Verify:**
```bash
cd packages/laravel
composer test -- --filter InvocationContext  # OK (16 tests, 54 assertions)
composer test                                # OK (79 tests, 186 assertions)
composer validate --strict                   # valid
python scripts/validate.py                   # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. D-027 added (ACCEPTED); D-026 remains PROPOSED.

---

### T-106 — Implement ActionBus pipeline shell — DONE

**Goal:** Deterministic middleware pipeline without Laravel-specific policy behavior.

**Implemented** (`packages/laravel/src/Runtime/Pipeline/`):
- `ActionBus` — kernel-ordered orchestration: exact registry resolution → trusted context requirement check → six stages in canonical order → audit finalizer. Handler injection order never determines execution order; missing/duplicate stage handlers and non-handler arguments fail loudly at construction (`InvalidPipelineConfiguration`); completing without an explicit execution output fails (`PipelineInvariantViolation` — null output is legitimate, presence decides); missing exact identity throws before any stage; missing trusted context requirement halts before any stage with no input/metadata fallback (D-007/D-027 preserved). Halt short-circuits later stages; the audit finalizer still observes exactly once. Unexpected stage exceptions propagate (T-110 owns normalization).
- `ActionPipelineStage` (internal enum), `ActionCall` (explicit id+version), `ActionPipelineState` (immutable `withInput`/`withOutput`, explicit `hasOutput` so null output is representable), `ActionPipelineDecision` (`continueWith`/`halt` with internal reason), `ActionPipelineOutcome` (internal completed/halted — not public ActionResult taxonomy), `ActionPipelineStageHandler` + `ActionPipelineAuditor` contracts.
- `tests/Unit/ActionBusTest.php` — 14 tests / 43 assertions: shuffled injection order vs canonical execution order (audit last), success, state transformation flow (validation input → authorization observation → execution → output-policy transform), validation/authorization short-circuits with audit-once, context-requirement halt before any stage (input+metadata spoof ignored), context success, empty-selection presence, exact-identity resolution failure runs no stage, missing/duplicate stage configuration, non-handler argument, null execution output, missing execution output invariant.

**Verify:**
```bash
cd packages/laravel
composer test -- --filter ActionBus  # OK (14 tests, 43 assertions)
composer test                        # OK (93 tests, 229 assertions)
composer validate --strict           # valid
python scripts/validate.py           # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. Plain PHP — no Illuminate Pipeline; no real validation/authorization/confirmation/idempotency/redaction/audit persistence (T-107..T-110, T-401+).

---

### T-107 — Add Laravel validation middleware — DONE

**Goal:** Validate caller input using Laravel Validator while keeping core schema semantics separate.

**Implemented** (`packages/laravel/src/Validation/`, intentionally Laravel-specific):
- `ActionValidationRulesProvider` (contract) + `InMemoryActionValidationRules` — explicit, exact-identity (id+version) rule registration; different versions independent; duplicate registration throws `DuplicateValidationRules`; missing configuration throws `ValidationRulesNotConfigured` while explicitly registered `[]` is valid (array_key_exists presence, not truthiness). Rules are returned as configured — no normalization, no schema-derived injection.
- `LaravelInputValidationStage` — real `ActionPipelineStage::InputValidation` handler; injects `Illuminate\Contracts\Validation\Factory` (no facades). `fails()` → halt with stable INTERNAL reason `input_validation_failed` (no ValidationException-as-control-flow, no catch-all). Success continues with `$validator->validated()` only — unvalidated caller fields never proceed, no merge with the original payload. Trusted `InvocationContext` is never touched; rules never mutate `ActionDefinition.inputSchema`; no authorization.
- `illuminate/validation: ^12.0|^13.0` added to composer.json (resolved v13.30.1).
- Tests: `InMemoryActionValidationRulesTest` (8) + `LaravelInputValidationStageTest` (10 incl. ActionBus integration: failure skips every later stage and audits once; later stages observe validated input; context instance/reference preserved; real stage still requires all other handlers).

**Verify:**
```bash
cd packages/laravel
composer update illuminate/validation --with-all-dependencies  # resolved v13.30.1
composer test -- --filter "LaravelInputValidation|InMemoryActionValidationRules"  # OK (18 tests, 41 assertions)
composer test                              # OK (111 tests, 270 assertions)
composer validate --strict                 # valid
python scripts/validate.py                 # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. JSON Schema ⇄ Laravel rules remain separate by design; public error taxonomy is T-110.

---

### T-108 — Add actor and tenant resolver contracts — DONE

**Goal:** Introduce trusted Laravel integration points.

**Implemented:**
- `packages/laravel/src/Contracts/AuthenticatedActorResolver.php` + `TenantResolver.php` — zero-argument `resolve(): ?ResolvedTrustedValue` contracts; caller input/request/metadata can never select authority. Tenant has no universal Laravel implementation (application-supplied).
- `packages/laravel/src/Runtime/Context/ResolvedTrustedValue.php` — non-null resolved value + provenance; falsy values (0/false/''/[]) are legitimate; null resolver result is the only absence representation.
- `packages/laravel/src/Runtime/Context/TrustedContextComposer.php` — composes the two resolvers into canonical `TrustedContextEntry` list (declaration order); null results omit entries (no placeholder actors/tenants).
- `packages/laravel/src/Auth/LaravelAuthenticatedActorResolver.php` — the only Illuminate-dependent piece (`Illuminate\Contracts\Auth\Factory`, no facades); guard is trusted constructor configuration, single source, no fallback; unauthenticated → null; provenance `laravel.auth` + guard reference (no secrets).
- No service-provider auto-binding; no tenancy package dependency; no current_record/selection/session/confirmation resolvers; no Gate/Policy usage.
- Tests: `LaravelAuthenticatedActorResolverTest` (4) + `TrustedContextComposerTest` (10 incl. the critical §16 integration: ActionBus accepts composed context; tenant unresolvable + attacker `tenant` in input AND metadata → fail-closed halt before any stage).

**Verify:**
```bash
cd packages/laravel
composer test -- --filter "AuthenticatedActorResolver|TrustedContextComposer|TenantResolver"  # OK (14 tests, 38 assertions)
composer test                       # OK (125 tests, 308 assertions)
composer validate --strict          # valid
python scripts/validate.py          # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. D-028 added (ACCEPTED); D-026 remains PROPOSED.

---

### T-109 — Add Gate/Policy authorization adapter — DONE

**Goal:** Support separate discover/invoke checks.

**Implemented:**
- `packages/laravel/src/Contracts/ActionAuthorizer.php` — rewritten to the protocol-neutral port `allows(definition, validatedInput, context): bool`; no Gate/Illuminate mentions; denial is boolean, never exception control flow.
- `packages/laravel/src/Runtime/Pipeline/AuthorizationStage.php` — generic Authorization stage handler; denial → halt `authorization_denied`; observational (never mutates state).
- `packages/laravel/src/Authorization/` — `LaravelAuthorizationRule` (explicit `UnitEnum|string` ability, empty string rejected, no trimming; optional trusted argument resolver over validated input + context, non-array results wrapped, no default injection); `ActionAuthorizationRulesProvider` + `InMemoryActionAuthorizationRules` (exact id+version keyed, `array_key_exists` presence); `AuthorizationRuleNotConfigured` (missing config is a loud failure, not implicit allow); `DuplicateAuthorizationRule`; `LaravelGateActionAuthorizer` — single Gate+Policy adapter (no `has()` pre-check, no `authorize()` control flow), actor exclusively from `ContextRequirement::AuthenticatedActor` applied via `forUser()` (missing actor → `forUser(null)` for guest-capable abilities; ambient user never consulted; no re-call of T-108 resolvers).
- Tests: `InMemoryActionAuthorizationRulesTest` (9), `LaravelGateActionAuthorizerTest` (8 incl. stage unit tests, fake Gate asserting `forUser`/`allows` calls, policy-style arguments, UnitEnum ability, metadata-spoof non-reachability), `AuthorizationIntegrationTest` (7: §31 denial short-circuit with audit-once, §32 sanitized-input boundary through real T-107+T-109 stages, §33 trusted-actor spoof closing the T-105/T-108/T-109 chain, §25 missing-config-loud-failure, pipeline config strictness).

**Verify:**
```bash
cd packages/laravel
composer test -- --filter "Authorization|ActionAuthorizer"  # OK (24 tests, 56 assertions)
composer test                        # OK (147 tests, 353 assertions)
composer validate --strict           # valid
python scripts/validate.py           # frozen contract green
```

**Status:** DONE (2026-09-05). M1 in progress. D-029 added (ACCEPTED); D-026 remains PROPOSED.

---

### T-110 — Implement normalized ActionResult/Error model — TODO

**Goal:** Stabilize success, validation failure, authorization rejection, confirmation-required, stale-binding, and internal-error envelopes.

---

## M2 — Livewire binding

### T-201 — Implement Livewire RuntimeBinding descriptor — TODO

**Goal:** Create driver-specific target data without polluting ActionDefinition.

**Acceptance:** component identity and method mapping live only in binding/driver code.

---

### T-202 — Implement explicit Livewire action exposure API — TODO

**Goal:** A component explicitly lists action classes/bindings available while mounted.

**Acceptance:** no reflection-based “all public methods” exposure.

---

### T-203 — Implement Livewire binding lifecycle producer — TODO

**Goal:** Emit mounted binding descriptors and invalidate them on component/navigation lifecycle changes.

---

### T-204 — End-to-end Prep List through shared ActionBus — TODO

**Goal:** Human Livewire button and agent binding execute the same application action.

**Acceptance:** business logic exists once; tests prove equivalent state transition.

---

## M3 — Browser runtime and WebMCP projection

### T-301 — Implement browser DriverRegistry — TODO

**Goal:** Register binding drivers by name and fail closed on unknown driver.

---

### T-302 — Implement WebMCP semantic projection — TODO

**Goal:** Map core effect/risk/output-trust into the current WebMCP tool definition behind one adapter.

**Acceptance:** no browser terminology leaks into ActionDefinition.

---

### T-303 — Implement async registration lifecycle — TODO

**Goal:** Register tools with `document.modelContext`, await failures, and clean them up with AbortController/lifecycle semantics.

---

### T-304 — Implement Livewire browser driver — TODO

**Goal:** Execute Livewire RuntimeBindings from WebMCP invocations.

**Acceptance:** stale component/binding returns explicit failure and does not guess/fallback.

---

### T-305 — Add cancellation propagation — TODO

**Goal:** Propagate browser execution cancellation as far as supported without claiming server rollback semantics.

---

## M4 — Production trust controls

### T-401 — Confirmation challenge/receipt contract — TODO

**Goal:** Consequential actions require opaque runtime-issued receipts.

**Acceptance:** `confirmed: true` in caller input is ignored/rejected as authority.

---

### T-402 — Idempotency store contract — TODO

**Goal:** Prevent duplicate consequential/write execution for required-key actions.

---

### T-403 — Output trust/redaction pipeline — TODO

**Goal:** Redact sensitive output and mark untrusted content without assuming read-only means safe.

---

### T-404 — Structured audit events — TODO

**Goal:** Record action ID/version, surface, actor/tenant references, outcome, correlation, duration, and hashes without storing secrets by default.

---

## M5 — Filament vertical

### T-501 — Filament record context binding — TODO
### T-502 — Filament current-selection trusted context — TODO
### T-503 — Filament active-filter context — TODO
### T-504 — Filament confirmation bridge — TODO
### T-505 — Multi-tenant order operations demo — TODO

M5 is complete only when the demo proves selected-record context cannot be forged through action input.

## M6 — HTMX portability proof

### T-601 — Define explicit HTMX binding markup/descriptor — TODO
### T-602 — Implement HTMX browser driver — TODO
### T-603 — Build non-Laravel HTMX fixture app — TODO
### T-604 — Run shared conformance scenarios against Livewire and HTMX — TODO

## M7 — Conformance and ecosystem bridges

### T-701 — Promote executable conformance runner — TODO
### T-702 — Add adapter author guide — TODO
### T-703 — Add Laravel MCP projection using maintained MCP package — TODO
### T-704 — Add optional OpenAPI importer as secondary adapter — TODO

## Deferred until evidence exists

- Rails/Hotwire runtime package.
- Phoenix LiveView runtime package.
- Blazor/Vaadin packages.
- A public registry/discovery service.
- Browser automation or reverse-engineered tool generation.
