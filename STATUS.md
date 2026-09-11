# Project Status

> Current repository state for M6 / T-601 external-review preparation.

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-binding-descriptor`
- **Stage:** M0 DONE; M1 DONE; M1.1 DONE/REVIEWED; M2 DONE/REVIEWED; M3 DONE/REVIEWED; M4 DONE/REVIEWED/MERGED; M5 DONE/REVIEWED/MERGED/MAIN REVALIDATED; **M6 IN PROGRESS**
- **Current task:** `T-601 — Explicit HTMX binding descriptor`
- **T-601 status:** **DONE / SELF-REVIEWED / READY FOR EXTERNAL REVIEW**
- **Base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Verified implementation/scope head:** `b06204e2c7acf6be01df3fec5085e55ddd650329`
- **Verified implementation CI:** `34654979136` — **7/7 green**
- **Decision:** `D-053` — **ACCEPTED for T-601 descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Browser:** TypeScript typecheck + **172/172 Vitest tests**; HTMX descriptor file **69/69**
- **PHP baseline:** **595 tests / 3164 assertions** across PHP 8.3/8.4 × Illuminate 12/13 with MySQL 8.4; unchanged by T-601
- **Contract / lint / Composer:** green
- **Pull request:** **NOT CREATED YET**
- **Next task:** `T-602 — HTMX browser driver` — **NOT STARTED**

## T-601 outcome

T-601 adds a pure browser-runtime descriptor boundary for HTMX without adding browser execution.

```text
ActionDefinition
      │
      │ finite closed top-level input schema
      ▼
createHtmxBindingTarget(...)
      │
      ▼
HtmxBindingTarget
      ├── sourceId
      ├── method
      ├── path
      ├── inputNames
      └── requiredInputNames
      │
      ▼
RuntimeBinding
      ├── driver = htmx
      └── lifecycle = page

arbitrary RuntimeBinding JSON
      │
      ▼
parseHtmxBindingTarget(...)
      │ exact target validation
      ▼
immutable HtmxBindingTarget
```

Producer construction and consumer parsing share the same primitive target validation so issuance and browser-side consumption cannot silently disagree on source ID, method, path, input lists, or required-subset semantics.

## Accepted descriptor boundary

1. `driver` must be exactly `htmx`.
2. lifecycle must be exactly `page`.
3. target contains exactly `sourceId`, `method`, `path`, `inputNames`, and `requiredInputNames`.
4. `sourceId` is an opaque exact rendered-source reference, not business identity or authorization proof.
5. supported methods are exactly `GET`, `POST`, `PUT`, `PATCH`, `DELETE`.
6. paths are bounded absolute same-origin references and reject relative/scheme-relative/full-URL/fragment/backslash/control/over-limit forms.
7. named Action input mapping is deterministic and derived only from a closed top-level object schema.
8. `additionalProperties` must be exactly `false`.
9. open/composed/reference top-level schema forms that can make the finite caller-key set ambiguous fail closed with `input_schema_unsupported`.
10. required names must be unique non-empty strings present in exact properties.
11. nested values remain under their top-level key; T-601 invents no form flattening syntax.
12. parser rejects missing/extra target keys and malformed mapping lists.
13. parser and builder return defensive frozen snapshots.
14. caller/schema arrays/objects cannot mutate an already issued descriptor mapping.
15. target contains no trusted actor/tenant/record/selection/browser-session/confirmation/idempotency/authorization authority.
16. descriptor module imports no HTMX runtime and contains no `htmx.ajax()`, DOM, `fetch()`, or Laravel runtime execution code.
17. T-601 adds no HTMX dependency.
18. `packages/laravel/src/**` is unchanged.
19. frozen `spec/0.1/**` is unchanged.
20. browser execution, live DOM method/path revalidation, cancellation, fixture issuance, and portability conformance remain T-602–T-604.

## TDD evidence

```text
Design checkpoint:               cca98af90323819789a56d28a2174b9b7ea25059 / 34625130732 — 7/7 green
Implementation plan:             1e47c6bb4121bcc1fc43e69c73c213d17092b5c1 / 34629136719 — 7/7 green
Parser RED:                      e0fceb6d7cf277dd1a1693daefc9b593d1a5d6a6 / 34653908938
Parser shell GREEN:              cf8436710a21dbb5888d62cd26197e7e7fc3ac62 / 34653972487
Primitive RED:                   f9da2714d7be4890ea4cd49623352224d73415de / 34654093046
Primitive GREEN:                 b1845aab1ed82b32a14f738be08ef668f9d45342 / 34654144660 — 7/7 green
List/freeze RED:                 cbb410aa926c464d4011caccc607215e1f9422ca / 34654294846
List/freeze GREEN:               4e640112d5b9d9129f4ea07feddba538384699a9 / 34654371743
Mapping RED:                     07a86db5756c0d75e7e6b0b854b8d24765a5fca1 / 34654482062 — exactly 2 missing-builder failures; 141 existing tests passed
Mapping valid-path GREEN:        92b392b5304c456465f9fa8ac68e0f2dad9e42a3 / 34654613401
Schema fail-closed RED:          2df42bee8c91dbb609f313a4720408045b8d5f8c / 34654717686
Schema fail-closed GREEN:        831496a119535c31e0d76890b59b8f7ce0fb508c / 34654782553
Immutability checkpoint:         757835dee45e44724feba1972dcacdce43c82228 / 34654885713 — 7/7 green
Type contract checkpoint:        acb82ef246f09cc427da53e20facb567b5093694 / 34654896859
Implementation/scope head:       b06204e2c7acf6be01df3fec5085e55ddd650329 / 34654979136 — 7/7 green
Browser result:                  172/172 tests; 11 test files; typecheck green
HTMX descriptor focused tests:  69/69
Contract:                        python scripts/validate.py green
```

## Exact current change surface

Compared with `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`, the implementation/scope head changes only:

```text
STATUS.md
TASKS.md
docs/DECISION-REGISTER.md
docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
packages/browser-runtime/src/htmx-binding-descriptor.ts
packages/browser-runtime/tests/htmx-binding-descriptor.test.ts
packages/browser-runtime/tests/htmx-binding-descriptor.typecheck.ts
```

Review-prep additionally updates `REVIEW_REQUEST.md` and the three tracking files above; it does not alter the verified descriptor implementation.

Explicitly absent:

```text
packages/laravel/src/**
spec/0.1/**
packages/browser-runtime/src/htmx-browser-driver.ts
examples/htmx/**
package.json / package-lock.json dependency changes
```

## Known limitations

T-601 deliberately does **not** prove execution. It does not:

- resolve an exact DOM source element;
- verify live `hx-*` method/path state against the descriptor;
- call `htmx.ajax()`;
- define response/swap semantics;
- define HTMX cancellation or dispatch frontier;
- issue a descriptor from a non-Laravel host;
- prove shared Livewire/HTMX conformance.

Those are explicitly T-602, T-603, and T-604 concerns. D-020 therefore remains proposed.

## Current boundary

T-601 implementation and self-review are complete. The next gate is **external review / PR creation only after explicit authorization**. Do not begin T-602 automatically.