# T-910 Filament Selection Driver Review

Branch: `feat/t-910-filament-selection-driver`. Local acceptance complete;
[PR #47](https://github.com/kefyusuf/surfacerelay/pull/47) carries delivery and live hosted checks.
D-075 is accepted; no new release.

The opt-in public driver captures exact exposure membership and lossless owned
plain binding descriptors. Unknown/cloned/mutated objects and conflicting policies
fail closed. Shared Livewire preflight runs before deferred selection writes.
One explicit coordinator excludes component overlap across driver instances and
holds occupancy through underlying settlement, even after early capture failure.
Selection/authorization/confirmation/idempotency authority remains server-owned.

Review the private Livewire seam, default cancellation/result compatibility,
partial-write bounds and shared coordinator lifetime. The migrated live example
uses the adapter. Historical registry generators retain compatible glue; a separate
T-910 local candidate uses the new root exports/envelope. Published alpha.1/alpha.2
do not acquire those exports.

Actual RED preceded fixes for missing API, clone/mutation/policy bypass and lossy
JSON normalization. Docker 508 browser tests/typecheck/build/conformance, 16 live
Filament browser tests, 30 HTTP/fault controls, 4/6/7 generator tests and canonical
validation pass. Clean tarball consumers and real Chrome/SQL drift, current-record,
response-loss/replay/stale controls pass. [Acceptance and manifests](docs/reviews/t910-filament-acceptance.md).
Final source hashes match all 21 runtime source files; all 45 installed files match
the final candidate manifest. Provenance is explicitly working-tree/base revision,
not registry or exact commit qualification. Independent reviews found no blocker.

Owned Docker resources/native tabs are removed; user locks and unrelated resources
are preserved. Ordinary UI calls/transactional rollback/production are outside proof.
