# Project Status

> This file is updated after every completed implementation task. It is the fastest entry point for external review.

## Snapshot

- **Project:** SurfaceRelay
- **Stage:** M0 DONE (frozen); M1 DONE; **M2 BLOCKED pending external architecture/security review**
- **Scaffolding note:** `packages/laravel` runtime is now real implementation (T-101..T-110); `packages/browser-runtime` remains starter scaffolding until M3 tasks pass their own acceptance criteria.
- **Active milestone:** M2 — Livewire binding (BLOCKED; do not start before external M1 review)
- **Active task:** T-201
- **Last completed task:** T-110 (2026-09-06)
- **Test baseline:** `packages/laravel` full suite OK — **173 tests, 420 assertions** (PHP 8.4.16, PHPUnit 11.5.56); `composer validate --strict` clean; `python scripts/validate.py` exit 0.
- **Frozen spec status:** `spec/0.1` is FROZEN — unchanged since M0 (T-005). Action-definition, runtime-binding, invocation, and action-result schemas, the fixture matrix (27 manifest entries) and the conformance-scenario registry (12 scenarios) all validate green. The PHP runtime conforms to it; M1's stricter result-shape invariants remain valid under the frozen schema.
- **Default branch:** `main` (expected after repository creation)
- **Contract version:** `0.1-draft`

## Current objective

External M1 architecture/security review of the completed Laravel action kernel before M2 begins.

## Verified baseline

- `python scripts/validate.py` passes (frozen M0 contract green).
- `packages/laravel` full suite: OK (173 tests, 420 assertions), PHP 8.4.16, composer validate --strict clean.
- No WebMCP/MCP conformance claim is made.

## Known risks

1. Premature standardization before two bindings exist.
2. Browser WebMCP API drift.
3. Accidentally treating browser/session context as normal caller input.
4. Expanding into generic API conversion instead of server-driven UI state.
5. Overbuilding abstractions before the Laravel + Livewire vertical works end-to-end.

## Needs decision

- Final project/repository naming can still change before first public release. `SurfaceRelay` is available on GitHub at the time this starter was prepared, but availability is not reserved.
- Package namespaces/vendor names should be chosen when the GitHub repository is created.

## Next task

`T-201 — Implement Livewire RuntimeBinding descriptor` (M2) — **do not begin until external M1 review completes**

## M1 verification summary (T-101..T-110, completed 2026-09-05/06)

- **Verification:** full package suite `composer test` → OK (173 tests, 420 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0 (frozen M0 contract green). Focused suites: result 26, pipeline/validation/authorization regressions 43, resolver/composer 14, registry 9, definition 24, ActionBus 14.
- **Kernel shape:** ActionDefinition (immutable, validated) → ActionRegistry (exact identity, fail-closed) → PhpInputSchemaCompiler (scalar + backed-enum + description JSON Schema) → InvocationContext (trusted entries vs non-authoritative metadata) → ActionBus (kernel gates: exact resolution, context requirements; canonical 6-stage pipeline; audit finalizer) → ActionResultNormalizer → ActionResult (succeeded/rejected/failed/confirmation_required).
- **Security invariants implemented and tested:** caller input never becomes trusted authority (D-007/D-027/D-028); validated-dataset boundary; user-scoped Gate without ambient fallback (D-029); fail-closed configuration everywhere (missing validation/authorization rules = loud failure); machine-readable halt codes, unknown codes unmappable silently (D-030/D-031); no trusted-context auto-projection into public results.

## T-110 record (2026-09-06)

- **Added/changed files:** `packages/laravel/src/Result/{ActionResultStatus,ActionError,ConfirmationChallenge,ActionResult,CoreActionErrorCode,ActionResultNormalizer,UnmappedPipelineOutcome}.php` (new), `packages/laravel/src/Runtime/Pipeline/{ActionPipelineHalt,ActionPipelineDecision,ActionPipelineOutcome,ActionBus,AuthorizationStage}.php` (structured-halt refactor), `packages/laravel/src/Validation/LaravelInputValidationStage.php` (safe field details), tests: `ActionResultTest`, `ActionErrorTest`, `ConfirmationChallengeTest`, `ActionResultNormalizerTest` (new) + 5 existing test files migrated to the halt API, `docs/DECISION-REGISTER.md` (D-030/D-031 ACCEPTED), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Halt migration:** free-form reason strings replaced by `ActionPipelineHalt` (machine code + optional non-authoritative details) in T-106 kernel (context gate → `required_context_missing` + canonical requirement identifiers only), T-107 (`input_validation_failed` + unique lexical-order failing fields, no values/messages), T-109 (`authorization_denied`, no details); all tests migrated.
- **Result semantics:** private constructor + status factories make ambiguous shapes unrepresentable; `data: null` presence preserved on success; meta explicit-only (empty omitted); unknown halt codes → `UnmappedPipelineOutcome`; confirmation model implemented but never fabricated; configuration exceptions stay loud; correlationId exclusively from InvocationContext.
- **Verification:** `composer test -- --filter "ActionResult|ActionError|ActionResultNormalizer|ConfirmationChallenge"` → OK (26 tests, 65 assertions); regressions `composer test -- --filter "ActionBus|LaravelInputValidation|Authorization"` → OK (43 tests, 115 assertions); `composer test` → OK (173 tests, 420 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.

## T-109 record (2026-09-05)

- **Added/changed files:** `packages/laravel/src/Contracts/ActionAuthorizer.php` (rewritten to `allows(definition, input, context): bool` port), `packages/laravel/src/Runtime/Pipeline/AuthorizationStage.php` (new), `packages/laravel/src/Authorization/{LaravelAuthorizationRule,ActionAuthorizationRulesProvider,InMemoryActionAuthorizationRules,AuthorizationRuleNotConfigured,DuplicateAuthorizationRule,LaravelGateActionAuthorizer}.php` (new), `packages/laravel/tests/Unit/{InMemoryActionAuthorizationRulesTest,LaravelGateActionAuthorizerTest,AuthorizationIntegrationTest}.php` (new), `docs/DECISION-REGISTER.md` (D-029 ACCEPTED), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Authorization semantics:** exact per-action `LaravelAuthorizationRule` (ability + optional argument resolver over validated input + trusted context); user-scoped Gate (`forUser(trusted actor)` incl. `forUser(null)` for guest-capable abilities — ambient user never consulted, no re-resolution via T-108 resolvers); single Gate+Policy adapter with no `has()` pre-check; `allows()` boolean, never `authorize()` exceptions; missing rule = loud `AuthorizationRuleNotConfigured` (not deny/allow); duplicates rejected; no version fallback; no ability auto-derivation from action ID; no action-definition/spec mutation; no roles/RBAC packages; tenant membership left to application policies.
- **Integration proofs:** §31 denial short-circuits later stages with audit-once; §32 raw caller field (`is_admin`) dropped by T-107 validation before authorization arguments are resolved; §33 attacker `authenticated_actor` in input+metadata never reaches Gate (`forUser('real-user')` only) — T-105/T-108/T-109 chain closed.
- **Verification:** `composer test -- --filter "Authorization|ActionAuthorizer"` → OK (24 tests, 56 assertions); `composer test` → OK (147 tests, 353 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.
- **Note:** the starter `Contracts\ActionAuthorizer` (`canDiscover`/`canInvoke` over an older context shape) had no consumers and was replaced; discovery-permission semantics remain a later concern (discovery layer does not exist yet).

## T-108 record (2026-09-05)

- **Added files:** `packages/laravel/src/Runtime/Context/{ResolvedTrustedValue,TrustedContextComposer}.php`, `packages/laravel/src/Contracts/{AuthenticatedActorResolver,TenantResolver}.php`, `packages/laravel/src/Auth/LaravelAuthenticatedActorResolver.php`, `packages/laravel/tests/Unit/{LaravelAuthenticatedActorResolverTest,TrustedContextComposerTest}.php`, `docs/DECISION-REGISTER.md` (D-028 ACCEPTED), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Trust boundary:** zero-argument resolver contracts (no caller input/request/metadata parameters exist — reflection-asserted); absence = null result = omitted entry; falsy values are legitimate; composer output in canonical declaration order; guard selection is constructor-only trusted configuration with no fallback; no universal `LaravelTenantResolver`, no tenancy package, no Gate/Policy, no service-provider auto-binding.
- **Pipeline boundary preserved:** resolution happens before dispatch; `ActionPipelineStage` untouched; the §16 integration test proves ActionBus accepts composed context when both resolvers resolve, and halts fail-closed before any stage when the tenant resolver returns null despite attacker `tenant` values in both caller input and metadata.
- **Verification:** `composer test -- --filter "AuthenticatedActorResolver|TrustedContextComposer|TenantResolver"` → OK (14 tests, 38 assertions); `composer test` → OK (125 tests, 308 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.
- **Naming note:** the suggested `Laravel/Context/` namespace would produce `SurfaceRelay\Laravel\Laravel\Context`; the actor adapter lives in `Auth/` and the composer in `Runtime/Context/` (protocol-neutral — it depends only on the two contracts).

## T-107 record (2026-09-05)

- **Added/changed files:** `packages/laravel/composer.json` + `composer.lock` (added `illuminate/validation ^12.0|^13.0`, resolved v13.30.1), `packages/laravel/src/Validation/{ActionValidationRulesProvider,InMemoryActionValidationRules,ValidationRulesNotConfigured,DuplicateValidationRules,LaravelInputValidationStage}.php` (new), `packages/laravel/tests/Unit/{InMemoryActionValidationRulesTest,LaravelInputValidationStageTest}.php` (new), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Separation enforced:** JSON Schema ⇄ Laravel rules never translated in either direction; rules are explicit per exact id+version, never inferred from inputSchema/reflection/docblocks; validation never mutates `ActionDefinition`.
- **Fail-closed configuration:** missing rule set ≠ empty rule set (`array_key_exists` semantics); missing throws `ValidationRulesNotConfigured`, deliberate `[]` is valid; duplicates (`DuplicateValidationRules`) never overwrite/merge; no fallback between versions (v2 rules never satisfy v1 — tested).
- **Validated-input boundary:** success continues with `$validator->validated()` only; unvalidated caller fields do not flow downstream; no merge with original payload. Trusted `InvocationContext` untouched (context instance identity asserted; a validated input field named `tenant` stays business data, never `ContextRequirement::Tenant` authority).
- **Pipeline integration:** validation halt (`input_validation_failed`, internal reason) skips authorization/confirmation/idempotency/execution/output-policy; audit observes exactly once; real stage still requires all remaining handlers at construction.
- **Verification:** `composer update illuminate/validation --with-all-dependencies` → v13.30.1; `composer test -- --filter "LaravelInputValidation|InMemoryActionValidationRules"` → OK (18 tests, 41 assertions); `composer test` → OK (111 tests, 270 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.

## T-106 record (2026-09-05)

- **Added files:** `packages/laravel/src/Runtime/Pipeline/{ActionBus,ActionPipelineStage,ActionCall,ActionPipelineState,ActionPipelineDecision,ActionPipelineOutcome,ActionPipelineStageHandler,ActionPipelineAuditor,InvalidPipelineConfiguration,PipelineInvariantViolation}.php` (10 files), `packages/laravel/tests/Unit/ActionBusTest.php`; updated `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Kernel behavior:** canonical stage order imposed by the `ActionPipelineStage` enum regardless of handler injection order; built-in context-requirement presence check halts before any stage (no input/metadata fallback); halt short-circuits later stages; audit finalizer observes exactly once on completion and explicit halts; missing exact identity throws before any stage (no version fallback); execution-output presence invariant distinguishes `hasOutput=true, output=null` from `hasOutput=false`; missing/duplicate stage handlers rejected at construction — security stages cannot silently become pass-through.
- **Deliberate boundaries:** internal outcome uses `completed`/`halted` (public succeeded/failed/rejected taxonomy reserved for T-110); unexpected stage exceptions propagate without audit (documented exception path); no binding resolution (M2); no Laravel Pipeline component; plain PHP only.
- **Verification:** `composer test -- --filter ActionBus` → OK (14 tests, 43 assertions); `composer test` → OK (93 tests, 229 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.

## T-105 record (2026-09-05)

- **Changed/added files:** `packages/laravel/src/Runtime/InvocationContext.php` (rewritten from starter scaffold), `packages/laravel/src/Runtime/Context/{TrustedContextEntry,ContextProvenance,DuplicateTrustedContext,TrustedContextNotAvailable}.php` (new), `packages/laravel/tests/Unit/InvocationContextTest.php` (new), `docs/DECISION-REGISTER.md` (D-027 ACCEPTED), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Trust boundary:** trusted authority is now `ContextRequirement`-typed entries built exclusively by runtime resolvers — no payload hydration API exists, `metadata` has no fallback path to trusted context, `surface`/`correlationId` are diagnostic labels, `idempotencyKey` is metadata not authority. Presence is entry presence (null entry values prohibited); empty `[]` selection counts as present; duplicates fail loudly; `allTrusted()` returns canonical declaration order.
- **Consumers:** only `Contracts\ActionAuthorizer` type-hints `InvocationContext` (no property access) — public shape replacement broke nothing.
- **Verification:** `composer test -- --filter InvocationContext` → OK (16 tests, 54 assertions); `composer test` → OK (79 tests, 186 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.
- **Frozen spec:** untouched; invocation envelope and internal runtime context remain intentionally separate concepts. Confirmation/browser-session semantics stay opaque; real receipts are T-401, idempotency T-402.

## T-104 record (2026-09-05)

- **Changed/added files:** `packages/laravel/src/Schema/PhpInputSchemaCompiler.php` (extended), `packages/laravel/src/Attributes/InputDescription.php` (new), `packages/laravel/src/Schema/UnsupportedParameterType.php` (unitEnum factory), `packages/laravel/tests/Unit/PhpInputSchemaCompilerTest.php` (13 new tests), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Enum support:** string/int backed enums → `type` + `enum` in case declaration order; nullable backed enums emit `type: [T, "null"]` AND append `null` last to `enum` (both constraints allow null — the T-104 critical invariant); explicit `BackedEnum|null` union accepted as `?BackedEnum` equivalent; `Status|string`, `Status|int|null`, `EnumA|EnumB` remain rejected; unit enums rejected with an explanation that a backed enum is required (no implicit case-name serialization).
- **Descriptions:** `InputDescription` (TARGET_PARAMETER) compiled verbatim after `type`/`enum`; no description key synthesized when absent; no docblock/comment/name/LLM derivation.
- **Verification:** `composer test -- --filter PhpInputSchemaCompiler` → OK (28 tests, 37 assertions); `composer test` → OK (63 tests, 132 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.
- **Note:** a nullable-enum bug was caught by the tests during development (enum branch dropped the named type's `allowsNull()`); fixed before completion — both null constraints verified per case.

## T-103 record (2026-09-05)

- **Added files:** `packages/laravel/src/Schema/PhpInputSchemaCompiler.php`, `packages/laravel/src/Schema/UnsupportedParameterType.php`, `packages/laravel/tests/Unit/PhpInputSchemaCompilerTest.php`; updated `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Design:** reflection-only compiler; nullable representation is JSON Schema type arrays (`[T, "null"]`) — never OpenAPI `nullable: true`; explicit `T|null` unions accepted as the same contract as `?T`; defaults affect requiredness only (no `default` emission); `additionalProperties: false` is deliberate compiler output (the signature is the declared accepted property set); declaration order preserved; no docblock/attribute/validation-rule reading; plain PHP — no Laravel/framework dependencies.
- **Rejections (loud, parameter-identified):** untyped, variadic, non-nullable unions, intersections, classes/DTOs, enums (T-104 boundary, test-enforced), unsupported built-ins (`array`, `mixed`, …).
- **Verification:** `composer test -- --filter PhpInputSchemaCompiler` → OK (15 tests, 22 assertions); `composer test` → OK (50 tests, 117 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0.
- **Note:** a fixture initially used the deprecated PHP "optional before required" signature form; it was replaced with a valid signature — the compiler itself follows PHP's reflection semantics unchanged.

## T-102 record (2026-09-05)

- **Changed/added files:** `packages/laravel/src/Contracts/ActionRegistry.php` (rewritten from starter skeleton — see note), `packages/laravel/src/Registry/InMemoryActionRegistry.php` (new), `packages/laravel/src/Registry/DuplicateActionDefinition.php` (new), `packages/laravel/src/Registry/ActionDefinitionNotFound.php` (new), `packages/laravel/tests/Unit/InMemoryActionRegistryTest.php` (new), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Contract note:** the starter `Contracts\ActionRegistry` skeleton registered action *class strings* (`register(string $actionClass): void`, `get(...): object`), which contradicts the T-102 goal of storing canonical `ActionDefinition` values. It was rewritten to the exact-identity definition-registry contract; nothing else consumed the old shape, so no breakage.
- **Semantics:** exact `id + version` identity; versions coexist; duplicate registration throws even for identical metadata; `get()` throws on missing identity (never null, never falls back to another version); `all()` deterministically ordered id ASC then version ASC; internal `{id}@{version}` keying is private convenience only — no public ActionIdentity string format or value object introduced.
- **Verification:** `composer test -- --filter ActionRegistry` → OK (9 tests, 27 assertions); full `composer test` → OK (35 tests, 95 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → exit 0 (frozen contract green).
- **Boundary respected:** no discovery, attribute scanning, authorization filtering, or version negotiation; no ActionIdentity abstraction; `InvocationContext` and all other scaffolding untouched; no spec changes.

## T-101 record (2026-09-05)

- **Changed/added files:** `packages/laravel/src/Definition/ActionDefinition.php` (new), `packages/laravel/src/Definition/InvalidActionDefinition.php` (new), `packages/laravel/src/Enums/ContextRequirement.php` (new), `packages/laravel/tests/Unit/ActionDefinitionTest.php` (new), `packages/laravel/tests/Smoke/EnumVocabularyTest.php` (extended), `.gitignore` (nested `vendor/` + phpunit cache artifacts), `composer.lock` (generated by `composer install`), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Verification:** `composer test -- --filter ActionDefinition` → OK (24 tests, 65 assertions); full `composer test` → OK (26 tests, 68 assertions); `composer validate --strict` → valid; `python scripts/validate.py` → frozen contract passes, exit 0. PHP 8.4.16 (package baseline ^8.3 satisfied). No composer dependency changes.
- **Contract freeze respected:** no spec changes; no contradictions discovered; T-101 not blocked.
- **Scaffolding clarifications:** `AgentAction` attribute and `InvocationContext` were left untouched; their tasks (definition compilation, T-105) remain TODO.
- **Design decisions:** ID length via `strlen` (ASCII grammar ⇒ bytes == characters); title/description via `mb_strlen` (UTF-8, matches JSON Schema character length); context requirements stored as a list canonicalized to enum-declaration order (caller order irrelevant, duplicates rejected); schemas preserved verbatim; no `toArray()`/serialization added; extension keys validated against the frozen `namespace/key` grammar only.

## T-005 record (2026-09-05)

- **Changed files:** `spec/0.1/invocation.schema.json`, `scripts/validate.py`, `spec/0.1/fixtures/invocation/invalid/malformed-action-reference-id.json`, `spec/0.1/fixtures/manifest.json`, `docs/GLOSSARY.md`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Alignment:** Invocation `action.id` now carries the canonical Action ID constraints (`type`/`pattern`/`maxLength`, grammar unchanged); `action.version` semantics confirmed identical in all three locations (`integer`, `minimum: 1`); no max/range/negotiation added.
- **Generalized drift guard:** `scripts/validate.py` now compares canonical Action Identity constraints across Action Definition, Runtime Binding ActionReference, and Invocation ActionReference (id: type/pattern/maxLength; version: type/minimum), reporting location + property + expected + actual. No $ref/shared-schema infrastructure.
- **Negative fixture:** `malformed-action-reference-id.json` in invocation fixtures (`"Orders.Refund"`) fails with `pattern` at `/action/id`; caller-confirmed-field fixture retained.
- **Drift-guard proof:** temporarily set `invocation.action.id.maxLength: 161` → `FAIL Action Identity grammar drift: invocation.action.id.maxLength=161 != canonical maxLength=160` + exit 1 → restored → exit 0.
- **Verification:** `python scripts/validate.py` → 4 schemas + 5 canonical identity checks + 28 fixture entries OK, exit 0.
- **Contract integrity:** grammar unchanged, version semantics unchanged, D-022..D-025 unchanged, D-026 still PROPOSED, `packages/` untouched. **M0 is DONE.**

## T-004 record (2026-09-05)

- **Changed files:** `spec/0.1/runtime-binding.schema.json`, `scripts/validate.py`, `spec/0.1/fixtures/runtime-binding/invalid/malformed-action-reference-id.json`, `spec/0.1/fixtures/manifest.json`, `docs/GLOSSARY.md`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Alignment:** Runtime Binding `action.id` now carries the same `type`/`pattern`/`maxLength` as `ActionDefinition.id` (canonical grammar unchanged: `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$`, maxLength 160). `version` semantics untouched (integer >= 1).
- **Drift guard:** `scripts/validate.py` asserts `type`, `pattern`, `maxLength` equality across the two schemas on every run. Proven by temporarily setting `maxLength: 161` in the definition schema → `FAIL Action ID grammar drift` + exit 1 → restored.
- **Negative fixture:** `malformed-action-reference-id.json` (`"Orders.Refund"`) fails with `pattern` at `/action/id` as asserted in the manifest. Existing `missing-action-version` fixture retained.
- **Verification:** `python scripts/validate.py` → 4 schemas + grammar match (3 keys) + 27 fixture entries OK, exit 0.
- **Contract integrity:** no grammar change, no new shared-schema/$ref infrastructure, `packages/` untouched, D-022..D-026 untouched (D-026 remains PROPOSED).

## T-003 record (2026-09-05)

- **Changed files:** `docs/GLOSSARY.md`, `docs/DECISION-REGISTER.md`, `docs/ARCHITECTURE.md`, `CONFORMANCE.md`, `spec/0.1/runtime-binding.schema.json`, `spec/0.1/fixtures/runtime-binding/**` (5 new fixtures), `spec/0.1/fixtures/conformance-scenarios.json` (new), `spec/0.1/fixtures/manifest.json`, `scripts/validate.py`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Semantics locked:** binding identity invariants (D-022), extensible drivers with explicit registry (D-016 ACCEPTED), lifecycle value meanings for `page`/`component`/`session`/`persistent` (D-023), cumulative validity formula with `expiresAt` as independent bound (D-024), stale-binding definition with no silent retargeting (D-025), recommended provisional failure codes (D-026 PROPOSED).
- **Conformance:** 12 registered scenarios in `conformance-scenarios.json` — 6 schema-level (executable via validate.py fixtures) and 6 runtime-level (documented fail-closed until T-604/T-701). `schema-invalid != runtime-stale` distinction is normative in CONFORMANCE.md.
- **Verification:** `python scripts/validate.py` → 4 schemas + 26 fixture entries + scenario registry integrity OK, exit 0. Negative mechanism re-proven: `missing-action-version.json` temporarily made valid → validator FAILED exit 1 → fixture restored.
- **Contract integrity:** lifecycle enum values unchanged (locked, not renamed); no framework names entered enum sets; driver remains a free identifier; `packages/` untouched.
- **Known limitations:** runtime scenarios are documentation-level until executable conformance (T-604/T-701); `expiresAt` format checking is not enforced (jsonschema format checker not enabled — noted for T-604).

## T-002 record (2026-09-05)

- **Changed files:** `spec/0.1/fixtures/**` (10 new fixtures + `manifest.json`), `scripts/validate.py`, `docs/GLOSSARY.md` (portable wording clarification, user-directed), `spec/0.1/action-definition.schema.json` (same description-only clarification), `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Fixture matrix:** 11 valid / 10 invalid fixtures across all four schemas. Action Definition: 7 valid (3 new materially different + 4 starter), 7 invalid covering protocol field in core, malformed ID, unknown context requirement, missing required property, invalid enum, invalid version, unexpected top-level property.
- **Reason assertions:** `scripts/validate.py` now asserts invalid fixtures fail for the intended validator keyword and, where specified, exact instance path (or offending property name for `additionalProperties`, which jsonschema reports at object level). Orphan fixtures not in the manifest are a hard error.
- **Negative-mechanism proof:** temporarily made `malformed-id.json` valid — validator failed with `FAIL fixture marked invalid validated cleanly` and exit 1; fixture restored, final run passed with exit 0.
- **Verification:** `python scripts/validate.py` → all 4 schemas + 21 fixture entries OK, exit 0.
- **Contract integrity:** no enum value sets, required fields, or patterns changed; D-021 untouched; `packages/` untouched.

## T-001 record (2026-09-05)

- **Changed files:** `docs/GLOSSARY.md`, `docs/DECISION-REGISTER.md`, `spec/0.1/action-definition.schema.json`, `TASKS.md`, `STATUS.md`, `REVIEW_REQUEST.md`.
- **Decisions locked:** D-013, D-014, D-015 → ACCEPTED; D-021 (outputTrust precedence: `sensitive` > `contains_untrusted_content` > `trusted_application_data`) added as ACCEPTED per user decision.
- **Verification:** `python scripts/validate.py` passes (4 schemas + 7 fixtures; `jsonschema` installed via pip).
- **Known limitations:** no new fixtures were added (that is T-002); `browser_session` naming is flagged in `REVIEW_REQUEST.md` as a possible rename before runtime implementation.
- **Note:** repository is not yet a git repository, so the diff was reviewed file-by-file by hand; schema enum value sets, required fields, and patterns were left unchanged.

## Latest verification

Run after extracting the package:

```bash
python scripts/validate.py
```

## External review note

No implementation review requested yet. Review architecture and task decomposition before T-101 if desired.
