# ADR 0006 — Reuse maintained MCP implementations

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Do not implement MCP JSON-RPC/transports from scratch.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
