# External Review Handoff

> Updated at the end of every completed task. Keep it concise and evidence-based.

## Review target

- **Milestone review: M1.1 Hardening (H-000..H-004) on branch `fix/m1-hardening`. M0 + M1 previously completed at root commit `f9865d0`.**
- **M2 is BLOCKED:** T-201 and all M2 work have NOT started and must not start until this M1.1 review completes.
- **Repository:** github.com/kefyusuf/surfacerelay (`main` = tool-neutral baseline `f9865d0`; hardening commits on `fix/m1-hardening`)
- **Scope:** spec/0.1 contract shape, output classification redesign, PHP package hardening, CI/licensing/docs

## Architecture summary

```text
ActionDefinition (immutable, construction-validated; no adapter knowledge)
  → ActionRegistry (exact id+version identity; duplicates/missing fail loudly; deterministic all())
  → PhpInputSchemaCompiler (PHP signature → deterministic JSON Schema:
       scalars, string/int backed enums in declaration order, nullable via type arrays,
       InputDescription attribute verbatim; additionalProperties=false; loud rejections)
  → InvocationContext (ContextRequirement-typed trusted entries ≠ non-authoritative metadata)
  → ActionBus (kernel gates: exact resolution → context-requirement presence
       → 6 canonical stages → audit finalizer, exactly once, observational)
       input_validation (Laravel Validator; validated-dataset boundary)
       authorization   (user-scoped Laravel Gate over trusted actor)
       confirmation    (slot; T-401)
       idempotency     (slot; T-402)
       execution       (output presence invariant: hasOutput distinguishes null from absent)
       output_policy   (slot; T-403)
  → ActionResultNormalizer (machine halt codes → public ActionResult)
```

Public result layer: `ActionResultStatus` (frozen 4 statuses), `ActionError` (extensible code namespace + safe messages + non-authoritative details), `ConfirmationChallenge` (data model only), `ActionResult` (private constructor + status factories → ambiguous shapes unrepresentable; deterministic serialization).

Security invariants implemented and regression-tested:

1. Caller input never becomes trusted authority (D-007/D-027/D-028) — no hydration API exists; spoofing tests use payload keys identical to canonical requirement names.
2. Validated-dataset boundary: unvalidated caller fields cannot reach authorization/execution (T-107 → T-109 regression).
3. User-scoped Gate: `forUser(trusted actor)` only; ambient user never consulted; `forUser(null)` for guest-capable abilities (D-029).
4. Fail-closed configuration: missing validation rules / authorization rules / pipeline stages / duplicate registrations are loud failures, never implicit allow (T-102/T-107/T-109).
5. Exact identity everywhere: no version fallback in registry, validation rules, authorization rules, or binding resolution (M0 frozen semantics).
6. Machine halt codes + loud unknown-code failure: no silent classification into public statuses (D-030/D-031).
7. No trusted-context leakage: actor/tenant/records/selection/session/receipts/provenance never auto-project into results/meta/details; audit observes internal outcomes only.
8. Resolver contracts accept zero arguments; guard selection is constructor-only trusted configuration; no request/route/header tenant inference.

## Accepted decisions (Decision Register, all ACCEPTED unless noted)

| ID | Decision (short) |
|---|---|
| D-001 | Stateful/server-driven UI actions first, not generic backend frameworks. |
| D-002 | Action Definition and Runtime Binding are separate contracts. |
| D-003 | Cross-language sharing is semantic/schema-level; Laravel has the real runtime core. |
| D-004 | Core semantics protocol-neutral; WebMCP/MCP annotations are projections. |
| D-005 | Browser WebMCP API isolated in one adapter. |
| D-006 | Do not implement MCP transport stack from scratch. |
| D-007 | Caller input cannot manufacture actor/tenant/selection/confirmation/binding authority. |
| D-008 | Monorepo first; split only with release evidence. |
| D-009 | No public cross-framework spec before two materially different bindings pass shared scenarios. |
| D-010 | Explicit exposure; no expose-all convention. |
| D-011 | Discovery permission does not imply invocation permission. |
| D-012 | OpenAPI is a secondary/import adapter. |
| D-013 | Explicit integer action versions bound by RuntimeBinding. |
| D-014 | Consequential risk is a categorical confirmation gate, orthogonal to effect. |
| D-015 | Confirmation authority via opaque, scoped, expiring runtime receipts only. |
| D-016 | Binding drivers are extensible identifiers with explicit registry; unknown drivers fail closed; no fallback. |
| D-017 | `current_selection` is trusted runtime context, never caller record IDs. |
| D-021 | SUPERSEDED by D-032 (single-valued `outputTrust` precedence model). |
| D-032 | Output sensitivity and output content trust are independent dimensions (`outputSensitivity`, `outputContentTrust`). |
| D-022 | Binding identity invariants: one exact instance, exact action id+version, no silent retargeting, no ID reuse, unknown IDs fail closed. |
| D-023 | Lifecycle semantics locked: page / component / session / persistent (never "permanent"). |
| D-024 | Cumulative binding validity (existence, non-revocation, lifecycle, expiresAt, exact action version, driver); whichever invalidates first wins. |
| D-025 | Stale bindings fail closed; no silent rediscovery/substitution of replacement targets. |
| D-027 | InvocationContext separates trusted entries from non-authoritative metadata; no fallback; duplicates loud. |
| D-028 | Actor/tenant resolved from injected trusted services before dispatch via zero-argument resolvers. |
| D-029 | Authorization evaluates the exact trusted actor via user-scoped Gate; no ambient fallback, no implicit ability mapping. |
| D-030 | `ActionError.code` is an extensible namespace, not a closed enum; codes reserved only when implemented. |
| D-031 | Public status meanings locked (succeeded/rejected/failed/confirmation_required); unknown halt codes fail loudly. |

Still PROPOSED: D-018 (Apache-2.0 license — LICENSE file present), D-019 (Filament first vertical), D-020 (HTMX second binding), D-026 (binding error codes — M2 unimplemented).

## Files added/changed by M1 (packages/laravel unless noted)

- **Definition:** `src/Definition/ActionDefinition.php`, `src/Definition/InvalidActionDefinition.php`
- **Enums:** `src/Enums/ContextRequirement.php` (new); existing ActionScope/Effect/Risk/IdempotencyPolicy reused; OutputSensitivity/OutputContentTrust added in M1.1 (D-032)
- **Registry:** `src/Contracts/ActionRegistry.php` (rewritten), `src/Registry/{InMemoryActionRegistry,DuplicateActionDefinition,ActionDefinitionNotFound}.php`
- **Schema:** `src/Schema/{PhpInputSchemaCompiler,UnsupportedParameterType}.php`
- **Runtime:** `src/Runtime/InvocationContext.php` (rewritten), `src/Runtime/Context/{TrustedContextEntry,ContextProvenance,ResolvedTrustedValue,TrustedContextComposer,DuplicateTrustedContext,TrustedContextNotAvailable}.php`
- **Pipeline:** `src/Runtime/Pipeline/{ActionBus,ActionPipelineStage,ActionCall,ActionPipelineState,ActionPipelineDecision,ActionPipelineHalt,ActionPipelineOutcome,ActionPipelineStageHandler,ActionPipelineAuditor,AuthorizationStage,InvalidPipelineConfiguration,PipelineInvariantViolation}.php`
- **Validation (Laravel-specific):** `src/Validation/{ActionValidationRulesProvider,InMemoryActionValidationRules,LaravelInputValidationStage,ValidationRulesNotConfigured,DuplicateValidationRules}.php`
- **Authorization (Laravel-specific):** `src/Authorization/{ActionAuthorizationRulesProvider,LaravelAuthorizationRule,InMemoryActionAuthorizationRules,LaravelGateActionAuthorizer,AuthorizationRuleNotConfigured,DuplicateAuthorizationRule}.php`
- **Auth adapter:** `src/Auth/LaravelAuthenticatedActorResolver.php`; **Contracts:** `src/Contracts/{AuthenticatedActorResolver,TenantResolver,ActionAuthorizer}.php`
- **Result:** `src/Result/{ActionResultStatus,ActionError,ConfirmationChallenge,ActionResult,CoreActionErrorCode,ActionResultNormalizer,UnmappedPipelineOutcome}.php`
- **Attributes:** `src/Attributes/InputDescription.php`
- **Tests:** `tests/Unit/{ActionDefinitionTest,InMemoryActionRegistryTest,PhpInputSchemaCompilerTest,InvocationContextTest,ActionBusTest,InMemoryActionValidationRulesTest,LaravelInputValidationStageTest,LaravelAuthenticatedActorResolverTest,TrustedContextComposerTest,InMemoryActionAuthorizationRulesTest,LaravelGateActionAuthorizerTest,AuthorizationIntegrationTest,ActionResultTest,ActionErrorTest,ConfirmationChallengeTest,ActionResultNormalizerTest}.php`; smoke test extended
- **Contract/docs (M0, frozen):** `spec/0.1/**` (schemas, fixtures, manifest, conformance scenarios), `docs/{GLOSSARY,DECISION-REGISTER,ARCHITECTURE}.md`, `CONFORMANCE.md`, `scripts/validate.py`
- **Repo:** `.gitignore` (nested vendor, phpunit caches, local agent-tool state), `composer.json` + `composer.lock` (added `illuminate/validation ^12.0|^13.0`)
- Untouched starter: `packages/browser-runtime/**`, `src/Attributes/AgentAction.php` (compilation is a future task), `docs/THREAT-MODEL.md`

## Verification commands/results (final re-run at checkpoint)

```text
$ cd packages/laravel
$ composer test
OK (173 tests, 420 assertions)          — PHP 8.4.16, PHPUnit 11.5.56, no warnings
$ composer validate --strict
./composer.json is valid
$ cd ../..
$ python scripts/validate.py
OK schema: action-definition / runtime-binding / invocation / action-result
OK canonical Action Identity checks (id: type/pattern/maxLength; version: type/minimum × 3 locations)
OK fixture (valid): 16 entries | OK fixture (invalid as intended): 11 entries
OK scenario registry integrity (12 scenarios)
SurfaceRelay starter validation passed.  (exit 0)
```

## Known limitations

- Confirmation and idempotency pipeline stages are slots; real behavior arrives with T-401/T-402. `failed`/`confirmation_required` public models exist but are not produced by the current pipeline (no fabricated challenges; no catch-all exception normalization).
- `ActionDefinitionNotFound` and configuration exceptions remain loud/internal by design; M2 binding semantics will enrich resolution failures.
- Audit observes internal outcomes only; durable audit records are T-404.
- `packages/browser-runtime` remains starter scaffolding (M3).
- Discovery/attribute→definition compilation does not exist; the registry holds only explicitly registered definitions (per T-102 scope).
- TOCTOU between trusted-context construction and Gate evaluation is out of scope until binding/session freshness work.

## Unresolved reviewer questions

1. **Halt shape:** is `ActionPipelineHalt` (machine code + optional non-authoritative details) the right granularity, or should per-code typed detail objects be required before agent-facing surfaces exist?
2. **Validation details:** failing field names only (lexical order) — sufficient for v0.1, or should safe field-level labels be standardized now?
3. **Discovery port:** when discovery arrives, should discoverability get its own contract (e.g. `ActionVisibilityFilter`) to keep D-011's discovery/invocation separation structural?
4. **Serialization:** `ActionResult::toArray()` is the single serialization surface — sufficient, or is a versioned JSON envelope needed before WebMCP/MCP projections (M3+)?
5. **Repository bootstrap:** repo is not yet git-initialized; naming/vendor decisions (STATUS "Needs decision") should be settled before first public history.

## Explicit statement

**T-201 (M2 — Livewire RuntimeBinding descriptor) has NOT been started.** No M2 code, bindings, or browser-runtime changes exist. The next action after this review is M2 kickoff only if the review passes.

## Suggested next action after review

Address reviewer findings, then begin M2 with T-201.
