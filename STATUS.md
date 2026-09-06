# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Reviewed T-302 checkpoint:** `b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Last completed/reviewed task:** `T-302 — WebMCP semantic projection`
- **Next task:** `T-303 — Async registration lifecycle` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-302.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Browser baseline:** TypeScript typecheck + **30/30 Vitest tests**.
- **CI:** T-302 feature review checkpoint and merged `main` checkpoint are green across all 7 jobs.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with no aliases/default/fallback; invalid, non-string and unsupported names fail closed. D-035 remains ACCEPTED.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

The existing projection skeleton is now a reviewed deterministic semantic contract:

```text
ActionDefinition.effect == read
        → readOnlyHint

ActionDefinition.outputContentTrust == contains_untrusted_content
        → untrustedContentHint

ActionDefinition.risk == consequential
        → consequentialHint
```

Reviewed invariants:

1. The three mappings are independent.
2. Destructive/external effects do not imply consequential risk.
3. Sensitive output does not imply untrusted content.
4. `read + consequential` may produce both hints as true.
5. Idempotency/effect/sensitivity do not synthesize unrelated hints.
6. Projection is pure and does not mutate the ActionDefinition.
7. Projection owns no registration, authorization, driver selection, binding resolution or execution.
8. The reference projection always returns all three booleans; the TypeScript return type matches that runtime shape.
9. A compile-time projection-shape fixture is included in browser typecheck.
10. D-036 records the orthogonal projection boundary.

## TDD / verification evidence

```text
RED:               8ef99e86331b1a6d81f4755ab7cd65294b639075
GREEN:             13253898b568bd0a52b0a2dc8d3d9f0be113483f
Reviewed/merged:   b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0
RED run:           34052956365
GREEN run:         34053052459
Review run:        34053277732
Merged main run:   34053337527
```

RED proved the public return type permitted `undefined` although runtime projection always emitted three booleans.

Final evidence:

```text
browser:  TypeScript typecheck + 30/30 Vitest tests
PHP:      266 tests / 783 assertions
contract: 52 fixture manifest entries + 12 conformance scenarios
CI:       all 7 jobs green on review checkpoint and merged main
```

## Decisions

- D-014 remains ACCEPTED — consequential risk is independent of effect.
- D-032 remains ACCEPTED — output sensitivity and content trust are independent.
- D-035 remains ACCEPTED — exact browser driver registration boundary.
- D-036 ACCEPTED — WebMCP hint projection is orthogonal and deterministic; unsupported/unrelated hints are not synthesized.

## Next task

`T-303 — Async registration lifecycle`

**Status: TODO / not started.**
