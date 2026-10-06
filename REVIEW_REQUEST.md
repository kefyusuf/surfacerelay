# First Alpha Publication Review Request

Branch: `release/t-904-alpha-publication`. Task: T-904.
Preparation PRs #36/#37/#38 are merged; T-903 is complete.

## Change

Separate offline final packaging re-verifies both private candidates, preserves
their runtime/license bytes and prepares final alpha README/public metadata.
The Laravel VCS root omits a version field. The npm tarball uses the exact alpha
version, public access, explicit npm registry and `alpha` dist-tag. Evidence stays
outside package contents. Existing private builders and NO-GO tools are unchanged.

The public browser validator checks archive hash, safe entries, exact metadata
and every file's bytes; its installed verifier rejects source aliases, links and
byte drift. Negative tests cover identity/content/metadata/path/output failures.
CI now verifies the public tarball in an isolated fixture consumer: root import,
declarations/typecheck, Vite bundle, DriverRegistry smoke and deep-import rejection.

## Authority and limits

The owner authorized completion of the two-package `0.1.0-alpha.1` publication
with interactive owner/2FA checks. Both registry identities and npm scope Owner
are verified; unattended token/OIDC provisioning is deferred, not claimed.

Final main-source rebuild, hashes, installed consumers, source/mirror/tag mapping
and exact-head CI must pass before registry writes. Then verify registry-installed
consumers. No new runtime/API contract or compatibility/support claim is added.
User Composer lockfiles remain untracked and excluded from release inputs.
See [the publication record](docs/releases/0.1.0-alpha.1-publication.md).
