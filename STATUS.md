# Project Status

Current state only; history is in Git, PRs and [the archive](docs/archive/STATUS-through-2026-10-04.md).

## Current work

- Main includes T-804–T-809. Preparation PRs #36/#37 remain open.
- [PR #38](https://github.com/kefyusuf/surfacerelay/pull/38) contains T-903 preparation,
  stacked on PR #37. The owner authorized `0.1.0-alpha.1` publication, contingent
  on final artifact/consumer/source/CI verification.
- T-901 two-package artifacts and T-902 installed Laravel acceptance are verified.
  Metadata preview/dry-runs pass; source manifests/private builders remain unchanged.
- The owner approved the distribution-only Laravel mirror approach, private
  reporting/intake ownership and formal disposition table.
- GitHub private vulnerability reporting is enabled and rechecked through the API.
  SECURITY.md assigns owner triage; notification delivery is not independently tested.
- D-069–D-074 and D-076–D-078 are Accepted. D-026/D-075 remain Proposed.
  ADR 0013 preserves the development monorepo as authoritative.
- Local mirror tooling generates a root-manifest tree from verified two-package
  archives; local previews retain null remote/commit and NO-GO.
  UTF-8/LF output is deterministic across host newline defaults.
- The owner authorized concrete mirror creation and the initial untagged push.
  [Laravel distribution mirror](https://github.com/kefyusuf/surfacerelay-laravel)
  is public; its remote tree matches all 160 approved file hashes. See
  [source/commit provenance](docs/reviews/laravel-distribution-mirror.json).
- Tooling evidence: 176 Python tests, canonical/22 HTMX fixtures, guardrails and
  strict Composer validation pass. CI repeats exact-source tree generation.
  Owner lockfiles are preserved; check current-head CI before merge.
- See [the current handoff](docs/releases/0.1.0-alpha.1-publication-handoff.md).

## Remaining gates

- The initial mirror contains the T-901 preparation baseline, not final release
  sources. No tags exist; final source/tag/consumer proof remains a later gate.
- Chrome Packagist session authenticates as `kefyusuf`; existing owned packages
  are visible. Its mirror preflight recognizes `surfacerelay/laravel` and offers
  final Submit; no package registration or namespace reservation has occurred.
- With owner approval, the free public `surfacerelay` npm organization is created.
  After the owner transferred it, Chrome verifies `kefyusuf` as the sole Owner,
  with 2FA enabled for authorization and publishing. The previous isolated CLI
  session belongs to `yukonit` and must not be used for the new publisher.
  Fresh isolated CLI login and registry identity/owner probes verify `kefyusuf`.
  No granular/scope-restricted token or automated CI publisher is claimed.
- Local preview blocker strings are conservative defaults, not live gate status.
  No final publication artifacts or registry-installed consumers are claimed.
- Browser/native/UI and concurrent-session-write qualification limits remain.
  No support SLA or production deployment guarantee is introduced.

## Authorized route and next gate

The owner authorized completion of the two-package alpha publication, using
interactive owner sessions with 2FA and identity/authority rechecks. Unattended
token/OIDC provisioning is deferred; web login does not prove a scoped token.
The new npm CLI login completed its device security-key ceremony.

T-903 is complete; T-904 is active. Integrate the reviewed PR stack and verify
separate final public packages and installed consumers before the authorized
tags/mirror/registry writes. Existing preview tools/private builders stay NO-GO.
