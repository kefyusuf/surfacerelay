# ADR 0013 — Laravel distribution mirror

- **Status:** Accepted
- **Date:** 2026-10-06

## Decision

Keep the current repository as the authoritative development monorepo, as
required by ADR 0008. Use a distribution-only Laravel repository whose root
contains the package's Composer manifest, runtime sources, migrations and license
for the public Packagist channel. The owner approved this approach during T-903.

The mirror is generated from an exact approved monorepo revision. It is not a
second development workspace and must not receive independent runtime fixes.
Record the upstream revision, generated content manifest and resulting mirror
commit mapping before publication. Consumer and package versions must remain
consistent with the approved two-package alpha source.

## Consequences and remaining gates

- No development repository split or core runtime/contract change.
- Root Composer metadata uses VCS tags for versions; local artifact metadata is
  a separate consumer-proof format.
- Preserve license, package paths, autoload/provider semantics and migration files.
- The owner authorized `kefyusuf/surfacerelay-laravel` as a public distribution-only
  mirror and its initial untagged push. Remote content and source/commit mapping
  are verified in [provenance](../reviews/laravel-distribution-mirror.json).
  That record is the initial preparation baseline. The final `v0.1.0-alpha.1`
  mirror/tag is published through Packagist, with exact installed bytes verified
  in [the release receipt](../reviews/alpha-0.1.0-alpha.1-publication.json).
- T-903/T-904 gates are complete for this owner-authorized experimental alpha;
  future releases require fresh verification.
