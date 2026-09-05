# Review Checklist

## Architecture

- Action Definition has no binding/surface/framework fields.
- Runtime Binding owns executable target details.
- Surface Projection owns WebMCP/MCP-specific metadata.
- New abstraction is justified by at least one concrete use case.

## Trust/security

- Caller cannot spoof actor/tenant/selection/confirmation.
- Invocation re-authorizes.
- Stale/unknown binding fails closed.
- Consequential writes use confirmation/idempotency where required.
- Read paths do not leak sensitive data under the assumption that “read-only is safe”.

## Tests

- Happy path covered.
- Security-sensitive negative path covered.
- Contract fixtures updated if semantics changed.
- Verification output recorded in `STATUS.md` / `REVIEW_REQUEST.md`.

## Scope

- No unrelated cleanup.
- No new dependency without task justification.
- No premature framework/generalization.
