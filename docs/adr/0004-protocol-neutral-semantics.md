# ADR 0004 — Keep core semantics protocol-neutral

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

WebMCP/MCP annotations are mapped by projections, never stored as core vocabulary.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
