# Project Status

> Current repository state for implementation and external review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `fix/m1-hardening`
- **Main baseline:** `f9865d04e999054671c43a2c42a26fc1f5c8914b`
- **Stage:** M0 DONE; M1 DONE; M1.1 HARDENING DONE; **M2 BLOCKED pending final external review**
- **Active task after review:** `T-201 — Implement Livewire RuntimeBinding descriptor`
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` was reopened under the documented freeze exception for H-001/H-002, then re-frozen after M1.1.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **188 tests / 444 assertions** across the supported PHP 8.3/8.4 × Illuminate 12/13 CI matrix.
- **Browser baseline:** TypeScript typecheck + **3 Vitest tests**.
- **CI jobs:** contract, PHP matrix, PHP lint, browser typecheck/tests.

## Current objective

Final external review of M1.1 before M2 begins.

## M1.1 changes

### H-001 — Contract shape hardening

- Action Result status-specific payload shapes are enforced by JSON Schema.
- `error` and `confirmation` are real objects when present; explicit null placeholders are invalid.
- Action Result strings have non-empty constraints.
- JSON Schema `date-time` formats are actually checked.
- Invocation/RuntimeBinding `bindingId` lexical constraints are aligned and drift-guarded.
- RuntimeBinding extension keys enforce the shared `namespace/key` grammar.

### H-002 — Output classification redesign

D-021 was superseded by D-032.

Action output now carries two independent dimensions:

- `outputSensitivity`: `normal | sensitive`
- `outputContentTrust`: `trusted_application_data | contains_untrusted_content`

A sensitive result may still contain untrusted content. Browser projection derives `untrustedContentHint` only from `outputContentTrust`.

### H-003 — PHP/package hardening

- `ext-mbstring` is declared.
- `AgentAction.contextRequirements` uses `ContextRequirement` values.
- PHP vocabulary parity tests are exhaustive.
- Reserved halt-code details are reconstructed through narrow safe shapes; associative/non-list shapes are rejected.
- `ConfirmationChallenge.expiresAt` validates RFC3339 values.

### H-004 — Repository hardening

- Complete Apache License 2.0 text is published and D-018 is ACCEPTED.
- CI runs the real PHP compatibility matrix and browser tests.
- Browser CI uses the committed lockfile.
- Stale starter packaging documentation was removed.
- M0 roadmap exit criteria is T-001..T-005.

## Security invariants

1. Caller input cannot manufacture actor, tenant, current selection, confirmation, or binding authority.
2. Trusted context is distinct from invocation metadata and has no metadata/input fallback.
3. Successful Laravel validation forwards only `$validator->validated()`.
4. Authorization evaluates the exact trusted actor through `Gate::forUser()`.
5. Missing validation/authorization configuration fails loudly.
6. Exact action `id + version` is required; no version fallback.
7. Unknown internal halt codes fail loudly.
8. Public result normalization does not auto-project trusted context and does not pass arbitrary reserved-code details through.

## Decisions

- D-017: ACCEPTED — `current_selection` is trusted runtime context; T-105/T-106 operationalize the rule.
- D-018: ACCEPTED — Apache-2.0.
- D-021: SUPERSEDED by D-032.
- D-032: ACCEPTED — output sensitivity and output content trust are independent.
- D-026 remains PROPOSED until binding failure behavior exists in M2.

## Next task

`T-201 — Implement Livewire RuntimeBinding descriptor`

**Do not begin T-201 until the final M1.1 external review passes.**
