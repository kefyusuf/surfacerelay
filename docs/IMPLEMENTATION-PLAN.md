# Implementation Plan

The task board is the operational source of truth. This document groups tasks into reviewable PR-sized changes.

| PR | Tasks | Theme |
|---|---|---|
| PR-001 | T-001..T-003 | Contract vocabulary and fixtures |
| PR-002 | T-101..T-102 | Laravel definitions + registry |
| PR-003 | T-103..T-104 | PHP → JSON Schema compiler |
| PR-004 | T-105..T-106 | Invocation context + pipeline |
| PR-005 | T-107..T-110 | Laravel validation/auth/results |
| PR-006 | T-201..T-203 | Livewire binding producer |
| PR-007 | T-301..T-304 | Browser runtime + WebMCP + Livewire driver |
| PR-008 | T-204 | Shared human/agent Prep List vertical |
| PR-009 | T-401..T-402 | Confirmation + idempotency |
| PR-010 | T-403..T-404 | Output policy + audit |
| PR-011 | T-501..T-505 | Filament order operations vertical |
| PR-012 | T-601..T-604 | HTMX portability proof |
| PR-013 | T-701..T-702 | Conformance runner + adapter guide |
| PR-014 | T-703 | MCP projection |
| PR-015 | T-704 | Optional OpenAPI importer |

## PR discipline

Each PR must:

- link task IDs;
- keep scope within those tasks;
- record test/verification evidence;
- note architecture/security impact;
- update `STATUS.md` and `REVIEW_REQUEST.md` before review.
