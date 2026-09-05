# ADR 0007 — Trusted context is runtime authority

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Caller input never manufactures actor, tenant, selection, confirmation, or binding authority.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
