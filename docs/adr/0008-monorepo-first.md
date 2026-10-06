# ADR 0008 — Monorepo first

- **Status:** Accepted
- **Date:** 2026-09-05

## Decision

Keep contract, Laravel runtime, and browser runtime together until independent release cadence/dependency pressure justifies split.

## Consequences

- [ADR 0013](0013-laravel-distribution-mirror.md) permits an owner-approved
  distribution-only Laravel mirror; development remains authoritative here.

- Implementations must preserve this boundary.
- Changes that contradict this ADR require a superseding ADR.
