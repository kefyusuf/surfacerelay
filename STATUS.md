# Project Status

> Current repository state for implementation and review.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/webmcp-semantic-projection`
- **T-302 implementation checkpoint:** `13253898b568bd0a52b0a2dc8d3d9f0be113483f`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; **M3 IN PROGRESS**
- **Last implementation task:** `T-302 — WebMCP semantic projection`
- **Review state:** ready for final review
- **Next task:** `T-303 — Async registration lifecycle` — **not started**
- **Contract version:** `0.1-draft`
- **Spec status:** `spec/0.1` remains frozen and unchanged by T-302.
- **Contract baseline:** **52 fixture manifest entries + 12 conformance scenarios**.
- **PHP baseline:** **266 tests / 783 assertions**.
- **Browser baseline:** TypeScript typecheck + **30/30 Vitest tests**.

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with no aliases/default/fallback; invalid, non-string and unsupported names fail closed. D-035 remains ACCEPTED.

### T-302 — WebMCP semantic projection — IMPLEMENTED / REVIEW READY

The existing projection skeleton was completed as a deterministic semantic contract rather than redesigned.

```text
ActionDefinition.effect == read
        ↓
readOnlyHint

ActionDefinition.outputContentTrust == contains_untrusted_content
        ↓
untrustedContentHint

ActionDefinition.risk == consequential
        ↓
consequentialHint
```

Reviewed implementation intent:

1. The three mappings are independent.
2. Destructive/external effects do not imply consequential risk.
3. Sensitive output does not imply untrusted content.
4. `read + consequential` legitimately produces both hints as true.
5. Idempotency/effect/sensitivity do not synthesize unrelated hints.
6. Projection is pure and does not mutate the ActionDefinition.
7. Projection performs no registration, authorization, driver selection or binding resolution.
8. The reference projection always returns all three booleans; its TypeScript return type now matches that runtime fact.
9. Typecheck includes an explicit projection-shape contract fixture.
10. D-036 records orthogonal projection semantics.

### TDD evidence

```text
RED:   8ef99e86331b1a6d81f4755ab7cd65294b639075
GREEN: 13253898b568bd0a52b0a2dc8d3d9f0be113483f
RED run:   34052956365
GREEN run: 34053052459
```

RED proved that the runtime always emitted three booleans while the public TypeScript return type weakened them to `boolean | undefined`.

Final GREEN evidence:

```text
browser typecheck: success
browser tests:     30/30
PHP:               266 tests / 783 assertions
contract:          52 fixture entries + 12 conformance scenarios
CI:                all 7 jobs green on implementation head
```

## Decisions

- D-014 remains ACCEPTED — consequential risk is independent of effect.
- D-032 remains ACCEPTED — output sensitivity and content trust are independent.
- D-035 remains ACCEPTED — exact browser driver registration boundary.
- D-036 ACCEPTED — WebMCP hint projection is orthogonal and deterministic; unsupported/unrelated hints are not synthesized.

## Next task

`T-303 — Async registration lifecycle`

**Status: TODO / not started.**
