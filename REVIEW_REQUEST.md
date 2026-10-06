# Alpha Publication Receipt Review

Branch: `docs/t-904-alpha-publication-receipts`. Task: T-904 completion.

The owner-authorized `0.1.0-alpha.1` release is published on npm and Packagist.
This documentation change records exact source/tag/mirror provenance, actual
registry tarball hash, installed consumer results and explicit alpha installation.
Runtime files, development manifests and private builders/NO-GO guards are unchanged.

Verification: 187 Python tests, canonical/22 HTMX fixtures, strict Composer and
guardrails; independent artifact review found no blocker. Tooling PR #39 passed
41/41 checks and final source main passed 20/20 before publication. Final local,
tagged-mirror and Packagist Laravel consumers pass 11 HTTP/race tests and detect
the authorization mutation. The npm registry consumer matches all reviewed bytes
and passes import/typecheck/bundle/smoke/deep-import rejection.

npm's first publication added `latest` alongside `alpha`; its removal returned
HTTP 400. Both aliases currently point to the same experimental alpha; documents
require the explicit version or `alpha`. No stable-release claim is introduced.

Review [durable receipt](docs/reviews/alpha-0.1.0-alpha.1-publication.json) and
[release details](docs/releases/0.1.0-alpha.1-publication.md).
Both user Composer lockfiles remain excluded. No next implementation task is selected.
