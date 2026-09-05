# Product Requirements Document

## Product thesis

Stateful/server-driven web frameworks already contain business actions, authorization rules, sessions, component lifecycle, and user-visible state. Agent integrations should reuse those application seams rather than duplicate them behind agent-only endpoints.

SurfaceRelay defines the contract and reference runtime boundaries needed to do that safely.

## Primary users

1. **Framework/package maintainers** building agent-callable surfaces for Livewire, Filament, HTMX, LiveView, Hotwire, Blazor, or similar systems.
2. **Application developers** who want browser agents to operate the same business actions humans use, under the same policy/tenant boundaries.
3. **Security-conscious teams** that need explicit confirmation, replay protection, audit, and current-UI-state provenance.

## Jobs to be done

- “Expose this current component action to an in-browser agent without creating a parallel REST controller.”
- “Let the agent operate the records currently selected in my admin table without trusting record IDs supplied by the agent.”
- “Reuse one action contract across WebMCP, a human UI, and later an MCP tool.”
- “Keep browser-spec churn away from my backend/domain code.”
- “Prove an adapter follows the trust/lifecycle rules with executable conformance tests.”

## Non-goals

SurfaceRelay is not an agent, planner, browser automation system, generic API gateway, or new MCP implementation.

## V0 success criteria

V0 is successful when all of these are true:

1. A Laravel action is defined independently from Livewire/WebMCP.
2. Human Livewire UI and WebMCP execute the same action pipeline.
3. Actor/tenant/current-selection context is resolved from trusted runtime state.
4. A stale Livewire binding fails closed.
5. A consequential action uses confirmation receipt + idempotency.
6. Filament current selection works without caller-controlled record IDs becoming authority.
7. The same Action/Binding contract is exercised by an HTMX proof.
8. Shared conformance scenarios run against at least Livewire and HTMX.

## Adoption strategy

### Stage 1 — Laravel proof

Prove the architecture where the author has deep framework leverage.

### Stage 2 — Filament reference vertical

Demonstrate why page state matters in a real admin workflow.

### Stage 3 — HTMX portability proof

Show the contract is not merely Livewire metadata with generic names.

### Stage 4 — ecosystem projection

Add MCP/OpenAPI bridges only after the stateful UI seam is proven.
