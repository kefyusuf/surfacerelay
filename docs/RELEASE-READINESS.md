# Release readiness (T-805)

`scripts/release_readiness.py` provides `build_release_readiness()`: one clean, exact
source revision and one internal prerelease version in; both release candidates out,
independently re-verified, with one aggregate `readiness-evidence.json`. It is a Python
function like the existing builders, not a CLI, and it never publishes, tags or selects
a public version (D-073).

## What it checks

1. The checkout is exactly the requested 40-character revision with no tracked or
   untracked changes (`preflight_repository`), and the version is a SemVer prerelease.
2. The stage root is empty; each candidate builds into its own subdirectory
   (`laravel/`, `browser-runtime/`) with the unchanged existing builders.
3. For each candidate: reported identity, `artifact-evidence.json` and
   `content-manifest.json` identity (package name, version, revision) match the inputs;
   archive filename, size and SHA-256 and the content-manifest SHA-256 are recomputed
   and must match the evidence.
4. Only then is `readiness-evidence.json` written. Any mismatch fails without it.
5. `publication.decision` is always `NO-GO`, with the remaining blockers listed.

The browser builder packs an existing `packages/browser-runtime/dist`; build it in the
clean checkout first (ignored output does not dirty the tree).

## Recorded run — 2026-10-04 (branch revision, pre-merge)

Run on Windows 11 from a detached `git worktree` of
`e722d01dd9a0b9e3617428673a18c5db7ee1601e` (branch `feat/t-805-release-readiness`,
stacked on the open PR series; **not** a merged `main` revision). Python 3.13, Node
26.8.2/npm, PHP 8.4.25 (Herd), Composer 2.

| Package | Archive | Size | SHA-256 |
| --- | --- | ---: | --- |
| `surfacerelay/laravel` | `surfacerelay-laravel-0.0.0-alpha1.zip` | 117884 | `eb27daccffe1b365aa20eb79c55639cf10ca671207baa2a5637b79ba193f90c3` |
| `@surfacerelay/browser-runtime` | `surfacerelay-browser-runtime-0.0.0-alpha1.tgz` | 18415 | `ae398854030a865e381019c72738b3a33a001e9a562c827d03e44e3fb7432bc3` |

Content-manifest SHA-256: Laravel `b287468f…cb5c`, browser `e7f8d45a…65e2`
(full values in the run's `readiness-evidence.json`).

The same archives were then installed into isolated clean consumers with the existing
helpers:

- Browser: root import, typecheck, Vite bundle, `DriverRegistry` smoke and deep-import
  rejection — **PASS**.
- Laravel (`^13.0`, PHP 8.4): Composer artifact-repository install, exact installed
  identity, ActionBus smoke — **PASS** (fixture-only policy stages, as documented).

Publication handoff: **NO-GO** — `private-vulnerability-reporting-unverified`,
`public-version-not-approved`, `registry-namespace-and-credentials-unverified`,
`publication-not-authorized`.

## Before any publication decision

- Re-run on the merged `main` revision after the open PR stack lands; this record is
  pre-merge evidence, and archives are not claimed byte-identical across platforms.
- Ubuntu CI keeps the four Laravel install legs and the browser consumer job per artifact.
- The owner resolves the listed blockers and gives explicit authorization (D-073).
