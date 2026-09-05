# ADR 0009 — Two bindings before spec promotion

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Do not claim cross-framework protocol maturity until Livewire and a materially different binding such as HTMX pass common scenarios.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
