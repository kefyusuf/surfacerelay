# ADR 0001 — Stateful/server-driven UI first

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Focus initial product scope on actions whose semantics depend on active UI/session/component state; generic endpoint conversion is secondary.

## Consequences

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
