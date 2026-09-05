# ADR 0003 — Use semantic contract plus language runtime cores

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Cross-language sharing is schemas/semantics; Laravel contains real executable runtime code.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
