# Project Status

> Current repository state after M4 merge and revalidation.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; **M4 DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **Last merged/revalidated task:** `T-404 — Structured audit events`
- **Merge PR:** `#4`
- **Feature head:** `7a5760fa8a527ca1ccb803c2ecfab2ab762acedd`
- **Merge commit:** `f2c0a7eda4c0d44a621c13e44b8fd3506e794430`
- **Post-merge main workflow:** `34403750390` — **success**
- **PHP:** **453 tests / 2418 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4
- **Browser:** TypeScript typecheck + **103/103 Vitest tests**
- **Contract:** `python scripts/validate.py` green; frozen `spec/0.1/**` unchanged
- **Decision:** `D-047 — ACCEPTED`
- **External review:** PASSED; actionable findings fixed and reviewer-confirmed; **0 unresolved review threads**

## M4 result

M4 Production Trust Controls is complete:

- T-401 confirmation challenge/receipt — DONE / REVIEWED / MERGED
- T-402 idempotency store — DONE / REVIEWED / MERGED
- T-403 output policy/redaction — DONE / REVIEWED / MERGED
- T-404 structured audit events — DONE / REVIEWED / MERGED / MAIN REVALIDATED

T-404 provides an append-only, payload-minimized final-dispatch audit record with explicit allowlist projection. Raw/validated input, every form of output, generic metadata, idempotency material, binding IDs, confirmation capabilities, trusted identity values, provenance references/scope keys, halt details, exception diagnostics, schemas and extensions are excluded from persistence.

The Laravel package installation path now auto-discovers `SurfaceRelayServiceProvider`, which loads the shipped audit migration. This review finding was reproduced TDD-first and revalidated across the full PHP/Illuminate matrix.

## Review evidence

- Original T-404 implementation checkpoint: `79df19c3a95f1a95b03e4b5c6578d8195903d22a` / `34384669898` — 7/7 green
- Pre-finding external-review head: `52aaee0e5e446311fb4554c67789ebefbbf116fb` / `34385980442` — 7/7 green
- Review-hardening checkpoint: `403927a0f2fdb60170f24c3d02756cb41e87965e` / `34401520520` — 7/7 green
- Feature final operational head: `7a5760fa8a527ca1ccb803c2ecfab2ab762acedd` / `34403206952` — 7/7 green
- Merge commit: `f2c0a7eda4c0d44a621c13e44b8fd3506e794430`
- Post-merge main workflow: `34403750390` — success

## Next boundary

**M5 — Filament Vertical** is next. `T-501 — Record context binding` has **not started**. No M5 work was started as part of the T-404 merge/closure.
