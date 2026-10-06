# Versioning and compatibility

SurfaceRelay is experimental and unofficial. This document separates implemented
version contracts and executable evidence from a proposed future release policy.
It does not promise registry availability or authorize publication.

The owner-selected first alpha target is `0.1.0-alpha.1`. Its
[preparation plan](releases/0.1.0-alpha.1.md) freezes scope and acceptance gates;
target selection does not authorize publication or promote the proposed policy.

## Implemented version identities

| Identity | Current contract | Evidence |
| --- | --- | --- |
| Local candidate package version | Explicit SemVer prerelease without build metadata; staged into candidate manifests only. `0.0.0-alpha1` is an internal verification input. | [Shared artifact tooling](../scripts/release_candidate.py) |
| Source revision | Full 40-character lowercase Git SHA; release preparation requires an exact clean checkout and archived source. | Shared tooling preflight and the consumer guides |
| Action version | Positive integer paired with a stable Action ID. This is application contract identity, independent of a package version. | [Canonical contract](../spec/0.1/action-definition.schema.json) |
| Source package metadata | Browser runtime remains `0.0.0-dev` and private; Laravel source manifest has no version field. Candidate builders supply staged versions. | [Browser manifest](../packages/browser-runtime/package.json), [Laravel manifest](../packages/laravel/composer.json) |

Changing a package version does not automatically change Action versions or the
canonical specification. Changing an application's Action contract must preserve
the existing Action identity/version rules; this document does not add a migration
or version-negotiation mechanism.

## Dependency constraints versus tested consumption

| Area | Declared constraints or artifact shape | Executable consumer evidence | Limits |
| --- | --- | --- | --- |
| Laravel | PHP `^8.3`, `ext-mbstring`, Illuminate `^12.0\|^13.0` | Composer artifact installation, exact identity and autoload: PHP 8.3/8.4 × Laravel 12/13 on Ubuntu | A dependency range is not proof of every patch, platform, database or application configuration. |
| Laravel ActionBus | Installed Laravel ZIP and explicit fixture bus construction | One smoke leg: PHP 8.4 + Laravel 13 | Fixture pass-through validation, authorization, idempotency and confirmation do not prove production controls. |
| Browser runtime | One root ESM export; ES2022 JavaScript and shipped declarations | Node 22, TypeScript 5.9.3, Vite 7.3.6: exact tarball install, root import, typecheck, bundle, DriverRegistry smoke and representative deep-import rejection | No CommonJS entry point, source/deep-import API or real-browser/WebMCP interoperability certification. |
| Artifact tooling | Python development tools; Git, npm or Composer as applicable | CI Python 3.12; guide commands verified in Docker Python 3.12.15 / Node 22.23.3 and PHP 8.4.26 / Composer 2.10.3 | Tested Windows npm launch paths do not establish broad platform/runtime support. |

The [Validate workflow](../.github/workflows/validate.yml) defines the installation
matrix. Its source-tree PHP/framework suites are separate from clean artifact
consumption; do not treat those suites as artifact support promises. The
[Laravel guide](consumers/laravel.md) and
[browser guide](consumers/browser-runtime.md) describe the bounded proofs.
Current results and remaining gates are recorded in [STATUS](../STATUS.md).
Historical evidence is retained in Git, PRs and [the archive](archive/STATUS-through-2026-10-04.md).

An archive content manifest records sorted file paths, sizes and SHA-256 hashes;
artifact evidence records identity and archive SHA-256. These are evidence for a
particular build, not a promise of byte-identical ZIP/tarball output across hosts.

## Proposed future 0.x policy

**Proposed — not an approved publication or support commitment.** Before any
public release, review and approve the version policy, migration notes and actual
consumer evidence. Under this proposed policy:

- Patch releases avoid intentional breaks to the documented package API.
- Minor releases may break the documented API, with changelog and migration notes.
- Prereleases may change before public release approval.

This proposal applies to package API changes. It does not replace positive integer
Action versions. The owner separately approved D-069–D-074 and D-076–D-078 for the
alpha; D-026/D-075 remain Proposed. The accepted decisions establish a coordinated
first-candidate identity, not independent support streams, support windows or a
general public compatibility guarantee. Other monorepo packages remain outside
the first two-artifact candidate scope; this future 0.x policy remains Proposed.

## Release boundary

Use the [release checklist](RELEASE-CHECKLIST.md) to review exact candidate evidence.
The [security policy](../SECURITY.md) records enabled private reporting and owner
triage. Registry authority, distribution implementation and final authorization
remain publication gates. Integrated same-revision/two-artifact verification
belongs to T-805; publication, tags and releases require separate approval.
