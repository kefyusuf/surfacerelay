# T-908 Opt-in Native Execution Result Review

Task: T-908. Branch: feat/t-908-native-error-envelope. Owner approved the opt-in
uniform envelope under D-079 after eight native rejections lost all error text.

Review the runtime registration boundary, public types, adapter schema/41 fixtures,
migration guide and disposable local-candidate Filament response-loss fixture.
Default output/rejection identity and direct drivers remain compatible. Only
opt-in output changes; every business/server result stays nested. Generic driver
failure is unknown, never interpreted through raw objects or public error classes.
Only the callback's pre-abort branch reports its driver was not dispatched.

Native Phase 0/1 controls and implementation RED/GREEN are observed. Docker
browser/schema/cancellation controls pass. Exact candidate artifact and real
Filament native/SQL acceptance are pending; no final CI/PR/release is claimed.

Independent source review found and corrected a fixture provenance issue:
the fault now needs a per-dispatch ledger boundary and exact new effect identity,
not an arm-time global count. Bundled-effect then singleton replay is a required
negative HTTP test. This is a serial single-worker fixture, not concurrency proof.
Reviewers have not replayed native calls. Existing user locks/resources stay
preserved; task-owned runtime/demo resources will be removed after acceptance.
