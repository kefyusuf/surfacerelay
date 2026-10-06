# Installed Alpha Application Review Request

Branch: `test/t-902-installed-app-acceptance`.
Task: T-902. Base: `release/t-901-alpha-scope-candidates`, open [PR #36](https://github.com/kefyusuf/surfacerelay/pull/36).

## Change and evidence

The isolated application installs the actual Laravel alpha ZIP, rather than a
path package. It uses Laravel session Auth/Eloquent, database tenant membership,
Gate, package validation/confirmation/idempotency/output stages and durable audit.
Eleven HTTP tests cover the alpha negative matrix. Independent processes share
SQLite WAL/file-cache locks; a test-only barrier precedes dispatch and effect
counts prove receipt/key races do not duplicate execution.

The Gate-bypass mutation produces the expected denied-actor test failure; the
restored fixture passes. Independent test review tightened exact rejection
codes, correlation-specific audit, safe replay output and binding rejection.
Docker Python 159 tests, canonical validation/22 HTMX fixtures, guardrails,
fixture PHP syntax and diff checks pass. See [evidence and reproduction](docs/reviews/alpha-installed-application.md).
The new CI job rebuilds a local alpha from an exact source archive and repeats
the HTTP acceptance. Check this branch's current-head CI before merge.

## Review boundary

- No production runtime, public contract, package metadata or decision promotion.
- Fixture approval, binding controls, observer and barrier are test-only.
- This proves the fixture HTTP host, not browser UI, native agents, production
  operation, concurrent session writes or every scope field in isolation.
- The two owner lockfiles remain excluded. PR #36 must land before this stacked
  branch can be merged to main. Publication remains NO-GO.
- T-903/T-904 are future scopes; settings, registry writes, tags and final release
  still need their applicable owner authorization.
