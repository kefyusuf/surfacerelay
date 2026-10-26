# Review handoff

No open PR. Two deliveries merged to `main` with green hosted checks:

- [PR #55](https://github.com/kefyusuf/surfacerelay/pull/55) — T-912,
  test-only repair of the Filament notification drift: the confirmation
  approval integration test registers the Filament support/notifications
  providers with array stores, restoring `assertNotified` capture under
  Filament >= 5.10.2's scoped `NotificationMailbox`. Full `php-tests`
  matrix is green again; the published `^5.0` support range is unchanged.
- [PR #54](https://github.com/kefyusuf/surfacerelay/pull/54) — A2-02 B2,
  app-owned browser descriptor and wiring (consumer-only): the mounted
  Filament page issues the exact descriptor document, the glue consumes
  only the published alpha.3 root export, and hosted receipts pass
  (`PASS_B2_DESCRIPTOR_EMISSION_ONLY` 3/3, `PASS_B2_BROWSER_WIRING_ONLY`
  15/15, plus A2-02a 12/12 and B1 2/2 regressions). Real Chrome
  `document.modelContext` discovery/invocation remains NOT RUN (A2-08).

Security review focus for external reviewers: the descriptor stays public
discovery metadata and never an authorization capability (negative emission
test), and the Node suite proves forged input, stale binding, expired
binding, unsupported surface and identity mismatch all fail closed.
Evidence: [B2 pilot](scripts/acceptance/a2-02-browser-pilot/README.md),
[B1 record](scripts/acceptance/a2-02b-binding-pilot/README.md),
[A2-02 integration gate](scripts/acceptance/a2-alpha3/A2-02-INTEGRATION-GATE.md).
