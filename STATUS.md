# Project Status

Current state only; history is in Git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- Main includes T-804–T-809. Preparation [PR #36](https://github.com/kefyusuf/surfacerelay/pull/36)
  and installed-application [PR #37](https://github.com/kefyusuf/surfacerelay/pull/37) remain open.
- T-901 two-package alpha candidates and T-902 installed Laravel application
  acceptance are verified. The owner selected `0.1.0-alpha.1`; publication is NO-GO.
- T-903 is active on `release/t-903-publication-preview`, stacked on PR #37.
  Separate proposed metadata is derived from both reverified actual archives;
  offline npm dry-run inventory and strict explicit Composer validation pass.
- Source manifests/private builders remain unchanged. The preview grants no
  publication authority and always records NO-GO. No final artifacts or registry
  consumer qualification are claimed.
- Docker Python: 170 tests pass, including 11 preview tests. Existing T-902:
  11 HTTP tests, mutation control and two-process receipt/key races pass.
  Current-head CI must pass before merge. Owner lockfiles remain preserved.
- See [the concrete preparation handoff](docs/releases/0.1.0-alpha.1-publication-handoff.md).

## Needs decision / open gates

- Public Packagist needs a package-root VCS manifest. The Laravel manifest is
  inside the development monorepo. Recommended proposal: a distribution-only
  mirror while keeping this repository authoritative; owner/channel choice pending.
- Private vulnerability reporting is disabled, rechecked read-only. GitHub admin
  rights do not authorize enabling it; intake ownership/process remains pending.
- Both public package endpoints return 404; this does not prove namespace ownership.
  npm identity did not authenticate; both registry authorities/credentials unverified.
- D-026 and D-069–D-078 stay Proposed. Review the handoff's disposition table;
  settings and formal promotions need explicit owner authorization.
- Browser/native/UI qualification limits remain as recorded in T-901/T-902.
  No production deployment or concurrent-session-write guarantee is added.

T-903 is not complete while these gates are open. T-904 is not started. Do not
merge, create mirrors, change settings, promote decisions, tag or publish automatically.
