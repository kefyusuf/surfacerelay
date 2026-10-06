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
- Mirror owner/name and creation/push authorization still require a concrete
  repository handoff. No remote mirror, tag or Packagist submission exists yet.
- Final source/tag mapping, registry authority, artifact/consumer verification
  and explicit publication authorization remain T-903/T-904 gates.
