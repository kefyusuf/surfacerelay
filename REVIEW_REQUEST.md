# External Review Handoff

## Review target

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Base:** `main` at `f9865d04e999054671c43a2c42a26fc1f5c8914b`
- **Head branch:** `fix/m1-hardening`
- **Scope:** completed M0 + M1 kernel plus M1.1 hardening and final external-review fixes.
- **M2 status:** BLOCKED. `T-201` has not started.

## Architecture under review

```text
ActionDefinition
  → ActionRegistry
  → trusted InvocationContext
  → ActionBus
      exact resolution
      trusted-context gate
      input validation
      authorization
      confirmation slot
      idempotency slot
      execution
      output-policy slot
      audit finalizer
  → ActionPipelineOutcome
  → safe ActionResult normalization
```

Output semantics:

```text
outputSensitivity  = normal | sensitive
outputContentTrust = trusted_application_data | contains_untrusted_content
```

These dimensions are independent (D-032).

## Security invariants to re-check

1. Caller action input and `metadata` cannot satisfy trusted `ContextRequirement` entries.
2. Actor/tenant are resolved before dispatch from injected trusted runtime/application services.
3. Laravel validation forwards only the validated dataset.
4. Laravel authorization uses the exact trusted actor with `Gate::forUser()`; no ambient-user fallback.
5. Missing exact action versions, validation rules, authorization rules, or pipeline handlers do not become implicit allow.
6. Binding/action identity does not silently float across versions.
7. Reserved halt codes expose only code-specific safe detail shapes.
8. Associative/non-list detail arrays are rejected rather than normalized into public details.
9. Unknown halt codes are not guessed into public result statuses.
10. Sensitive output does not suppress the untrusted-content signal.

## M1.1 hardening summary

### H-001

- Status-specific Action Result shapes are normative in JSON Schema.
- `error` and `confirmation` are object-only when present; explicit `null` placeholders are invalid.
- `date-time` formats use a real `FormatChecker`.
- RuntimeBinding/Invocation `bindingId` constraints are drift-guarded.
- RuntimeBinding extension key grammar is enforced.

### H-002

- D-021 single-valued `outputTrust` model was superseded.
- D-032 introduced independent `outputSensitivity` and `outputContentTrust`.
- PHP, schema, fixtures, examples, TypeScript types, and WebMCP projection are aligned.
- `sensitive + contains_untrusted_content` keeps `untrustedContentHint=true`.

### H-003

- `ext-mbstring` is declared.
- `AgentAction` context requirements align with the canonical enum.
- PHP enum vocabulary parity is exhaustive.
- Halt details are sanitized by reserved error-code shape.
- Confirmation challenge timestamps are RFC3339-validated.

### H-004

- Full Apache-2.0 license published; D-018 ACCEPTED.
- CI covers contract validation, PHP 8.3/8.4 × Illuminate 12/13, PHP lint, browser typecheck/tests.
- Browser dependency install is lockfile-reproducible (`npm ci`).
- Stale starter-package artifact removed.
- Roadmap/task/status documentation aligned.

## Verification target

Expected final branch evidence after this review-fix commit:

```text
contract: 52 fixture manifest entries + 12 conformance scenarios
PHP:      188 tests / 444 assertions
browser:  typecheck + 3 tests
CI:       contract + four PHP matrix jobs + php-lint + browser
```

All GitHub Actions jobs must be green before review approval.

## Decision status

Accepted and relevant:

- D-007 trusted authority cannot come from caller input.
- D-017 `current_selection` is trusted runtime context.
- D-018 Apache-2.0.
- D-027 trusted/non-authoritative invocation-context separation.
- D-028 actor/tenant trusted resolver boundary.
- D-029 exact trusted actor authorization.
- D-030 extensible error-code namespace.
- D-031 public result status semantics.
- D-032 independent output sensitivity/content trust.

Still PROPOSED:

- D-019 Filament first production vertical.
- D-020 HTMX second binding.
- D-026 binding error codes until M2 behavior exists.

D-021 is SUPERSEDED by D-032.

## Explicit statement

**M2/T-201 has not started.** The next action is M2 kickoff only after this external review passes.
