# Open Questions

These questions are intentionally not resolved in the starter. Resolve them with implementation evidence rather than speculation.

## Contract

- Should `browser_local` remain in the same base Action Definition as server-executed actions, or become a separate action kind after browser-runtime evidence?
- Should risk be a four-level enum or a smaller `routine/consequential` boundary plus extensions?
- Should output trust combine sensitivity and untrusted-content provenance, or split them into separate dimensions?
- What constitutes a breaking action-version change for descriptions/tool-selection semantics?

## Runtime binding

- How should a binding prove freshness across Livewire navigation/morph cycles?
- Does binding identity need a server-issued signature/token in the first Laravel implementation, or is session-bound server registry sufficient?
- How much binding metadata may safely be embedded in page HTML versus referenced by opaque ID?

## Confirmation

- Should confirmation receipts bind an exact normalized input hash, action version, binding ID, actor, tenant, and expiry?
- Can a prepared reversible action produce a confirmation challenge for a separate commit action cleanly enough to avoid generic “confirm any write” UX?

## Filament

Resolved with implementation evidence:

- `current_selection` authority is the exact active table page's public effective-selection result, bounded and canonicalized as exact persisted record identities — D-049 / T-502.
- `filament/active_filters` authority is the exact active `HasTable` page's explicitly exposed public applied-filter state from `getFilters()` + `getTableFilterState()`, physically separated from caller metadata and always bound into confirmation/idempotency when present — D-050 / T-503.

Still open:

- How should page state be rebound after table refresh/pagination/navigation?

## HTMX

- Should the first HTMX driver execute an explicit form submission or call an app-owned endpoint descriptor?
- Which browser events are sufficient to establish completion without pretending HTML swap success equals business success?

## Conformance

- Which tests can remain completely framework-independent?
- Which tests require a driver-specific adapter harness but shared expected semantics?
