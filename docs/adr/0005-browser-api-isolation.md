# ADR 0005 — Isolate browser API

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

All document.modelContext compatibility and lifecycle handling stays in browser projection/runtime.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
