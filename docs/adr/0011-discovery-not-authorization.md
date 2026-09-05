# ADR 0011 — Discovery is not invocation authorization

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Every invocation re-evaluates authorization against resolved trusted context/resources.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
