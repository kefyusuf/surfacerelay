# T-908 Opt-in Native Execution Result Review

Task: T-908. [PR #44](https://github.com/kefyusuf/surfacerelay/pull/44).
Owner approved the explicit opt-in uniform envelope under D-079 after eight
native rejection forms lost all error text.

Review the registration boundary, public types, adapter schema/41 fixtures,
migration guidance and disposable candidate Filament fault/control fixture.
Default output/rejection identity and direct drivers remain compatible.
Opt-in application results stay nested; driver failures use fixed safe text and
unknown outcome without reading hostile objects or exported error classes.
Only callback pre-abort reports no driver dispatch. No automatic recovery.

Docker verification passes: 447 browser tests, typecheck/build, 193 Python tests,
canonical/22 HTMX/41 envelope fixtures, 23 existing + 7 new Filament HTTP tests,
5 generator and 4 CLI tests. Clean artifact consumer controls and the committed
source CI-helper equivalent pass. [Native/SQL evidence](docs/reviews/t908-native-acceptance.md)
covers stale list/edit, denied permission, tenant change, receipt expiry and
committed effect with response loss; explicit replay keeps one effect.

Independent source/evidence review has no remaining blocker without native replay.
The fault requires this dispatch's ledger boundary and exact new effect identity;
bundled effect then singleton replay is a negative regression. Serial single-worker
simulation is not concurrency or real-payment proof. Final-head hosted CI remains
a separate PR gate. Owned demo/stack/volumes/tabs/helpers are removed; user locks
and existing resources are preserved. Merge/publication need separate authority.
