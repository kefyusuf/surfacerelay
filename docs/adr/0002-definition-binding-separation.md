# ADR 0002 — Separate Action Definition and Runtime Binding

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Action semantics and execution target/lifecycle are independent contracts.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
