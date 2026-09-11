# External Review Request — T-601 HTMX Binding Descriptor

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Scope:** `T-601 — Explicit HTMX binding descriptor`
- **Feature branch:** `feat/htmx-binding-descriptor`
- **Pull request:** `#10` — **OPEN / REVIEW HARDENING**
- **Base / merge-base:** `main@5b22eef928d2fb1f8fac8ab13507e2f22661d3df`
- **Pre-review head:** `c4e9b841d98464cc2fdb0b3fb259e9cebba08c76`
- **Review-hardening code head:** `0fd29621c1ae3084649095f8a8595e741c504e50`
- **CodeRabbit run:** `77dbb63c-4223-4177-a56a-8cad74daddb8`
- **Decision:** `D-053` — **ACCEPTED for descriptor semantics only**
- **Portability decision:** `D-020` — **PROPOSED; gated on T-604**
- **T-602:** **NOT STARTED**
- **Merge:** **NOT AUTHORIZED / NOT MERGED**

## What is being reviewed

T-601 adds a pure browser-runtime descriptor contract for `RuntimeBinding(driver=htmx, lifecycle=page)`. The exact driver-owned target is:

```text
sourceId
method
path
inputNames
requiredInputNames
```

The producer derives `inputNames` / `requiredInputNames` only from a finite closed top-level `ActionDefinition.inputSchema`; arbitrary RuntimeBinding JSON is independently validated by the consumer parser. Returned targets are immutable defensive snapshots.

There is no DOM lookup, HTMX execution, network dispatch, cancellation behavior, fixture application, Laravel production change, HTMX dependency, or `spec/0.1/**` change in T-601.

## Review-hardening result

CodeRabbit reported **1 Major + 3 Minor**.

The Major finding was reproduced before changing production code: JSON Schema `dependentRequired` and legacy `dependencies` can create conditional required keys that are not represented by the plain `required` array.

```text
RED:   9b241e4ef58740e512d57fde16efd1caaf8db5bd
CI:    34656195469
Result: browser 172 passed / exactly 2 failed
        - dependentRequired
        - dependencies
```

The builder now rejects both keywords fail-closed alongside the other top-level mapping forms that can make required-key semantics conditional or ambiguous.

```text
GREEN: 0fd29621c1ae3084649095f8a8595e741c504e50
CI:    34656249493 — 7/7 green
Browser: TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor: 71/71
Contract / lint / PHP matrix: green
```

The remaining Minor findings are documentation/tracking hygiene: milestone status token consistency, a concise review handoff, and the distinction between the committed pre-implementation design snapshot and the later implementation-plan refinement.

## Focused review questions

Please focus on these boundaries:

1. **Finite input mapping:** Can any accepted top-level JSON Schema form add or condition caller keys / required keys without being rejected?
2. **Exact target identity:** Does any descriptor rule permit silent target rediscovery or replacement identity reuse?
3. **Method/path ambiguity:** Can producer and future consumer disagree because of normalization, authority, fragment, control-character, or path-boundary behavior?
4. **Authority leakage:** Can any target field manufacture actor, tenant, record, selection, confirmation, idempotency, browser-session, or authorization authority?
5. **Immutability:** Can mutation of source arrays/schema objects alter an already-issued descriptor?
6. **Scope creep:** Has any T-602 behavior (DOM, `htmx.ajax()`, network, cancellation) leaked into T-601?
7. **Frozen contract drift:** Is any implementation policy accidentally being promoted into `spec/0.1/**` or Laravel production code?

## Deliberate limitations

T-601 does **not** resolve an HTMX source in the DOM, revalidate live `hx-*` state, call `htmx.ajax()`, define response/swap semantics, define cancellation guarantees, or prove a non-Laravel/shared conformance path. Those are T-602–T-604 responsibilities.

## Reference material

- Current delivery state and verification: `STATUS.md`
- Current task/evidence board: `TASKS.md`
- Original design snapshot: `docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md`
- Implementation-plan refinement: `docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md`
- Accepted decision: `docs/DECISION-REGISTER.md` (`D-053`)

**External review is the current gate. Do not merge or begin T-602 from this handoff.**
