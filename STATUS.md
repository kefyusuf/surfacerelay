# Project Status

Current state only; history is in Git, PRs and the archive.

## Current work

- T-901–T-903 are complete. Preparation PRs #36/#37/#38 are merged; each was
  checked against its reviewed head and successful CI before integration.
- T-904 is active on `release/t-904-alpha-publication`. The owner authorized the
  two-package `0.1.0-alpha.1` publication, necessary integration/mirror/tags and
  registry writes using interactive owner sessions with 2FA.
- Chrome and isolated npm CLI identity/owner probes verify `kefyusuf` and
  `surfacerelay` scope ownership. Packagist authenticates as `kefyusuf` and its
  mirror Check recognizes `surfacerelay/laravel`; final Submit is still pending.
- Unattended scoped-token/OIDC provisioning is deferred for the first alpha.
  Credentials stay outside the repository; the old `yukonit` CLI session must
  not be used for publication.
- Private vulnerability reporting is enabled and rechecked; SECURITY.md assigns
  owner triage. D-069–D-074/D-076–D-078 are Accepted; D-026/D-075 stay Proposed.
- Separate final public packaging is under verification. Existing private
  builders/readiness/preview tools remain unchanged and retain their NO-GO.
- The public Laravel mirror still contains the verified initial preparation
  tree, with no tags. Final source/content/tag mapping remains a T-904 gate.

## Remaining gates

Review final public package bytes/metadata, integrate the final tooling PR with
successful CI, freeze one final main revision, rebuild both artifacts and verify
isolated installed consumers. Only then update/tag the mirror and publish the
authorized alpha. Verify exact registry-installed consumers afterward.

No package has been published. Browser/native UI, production and support-SLA
qualification limits remain; user Composer lockfiles are preserved.
See [the publication record](docs/releases/0.1.0-alpha.1-publication.md).
