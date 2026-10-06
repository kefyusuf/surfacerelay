# Merged-main readiness verification

Task: T-805 post-merge artifact readiness.
Source: `9e7fd2c6bdd13a6644e08f9456d6470877d14d3c`, the main merge of
[PR #34](https://github.com/kefyusuf/surfacerelay/pull/34).
Internal candidate version: `0.0.0-alpha1`; no public version was selected.

## Verified execution

The source was cloned into a temporary Linux filesystem, checked out at the
exact detached merge revision and verified clean. Default readiness builders
materialized the Git archive and rebuilt browser distribution. The host checkout
was mounted read-only; owner Composer lockfiles were not candidate inputs.

Docker runtimes: Python 3.12.15, Node 22.23.3/npm 10.9.9, PHP 8.4.26 and
Composer 2.10.3. The actual unittest log reports **158 tests passed**. Canonical
validation, **22 HTMX envelope fixtures** and publication guardrails passed.

| Candidate | Archive | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| Laravel | `surfacerelay-laravel-0.0.0-alpha1.zip` | 116630 | `77af948c8467ba3ffb3766f6dee8bf640d84deec564065e45b63c52ea3a68b8f` |
| Browser | `surfacerelay-browser-runtime-0.0.0-alpha1.tgz` | 18760 | `9184a4f9cf30c65136229978f92cc5e01c1d9e22f22ef30a417d045b3b8f6871` |

Content-manifest SHA-256:

- Laravel: `0a6576524742dbd002dc2732e9283643dbb86683a51601acedcaacd263389b1c`.
- Browser: `a5502db40646123d90e5bffbb4cb690524e4bf187380c9afc6555f8d69895ffa`.

Both resulting archives were installed in separate clean consumers:

- Browser: exact installed identity, root import, declarations/typecheck, Vite
  bundle, DriverRegistry smoke and representative deep-import rejection passed.
- Laravel: Composer artifact install under Laravel 13, exact installed identity,
  vendor autoload and bounded ActionBus smoke passed.
- Deliberately ignored `.env` and stale `dist` sentinels were excluded from the
  candidates and preserved in the source copy.

The task container exited successfully. Archives, manifests, per-artifact and
aggregate evidence, verification JSON and complete logs were copied to the local
ignored `.tmp/t805-main-evidence/` directory before removing the task stack.
Copied artifact and content-manifest hashes were independently checked on the host.

Main CI at this merge revision passed **20 checks** across Validate, HTMX and
Filament workflows. Filament reported **16/16**. CI covers the existing four
Laravel artifact-install legs; the local smoke above covers PHP 8.4/Laravel 13.
An independent agent reviewed the verification helper and evidence boundaries;
that review was source-only.

## Limits and remaining gates

This closes the merged-main readiness verification gate for the recorded source,
not for later source revisions. Archives are not claimed reproducible byte for
byte across hosts. The ActionBus consumer uses fixture pass-through policy stages;
native browser behavior is separate CI evidence, not part of this artifact smoke.

Publication remains **NO-GO**: private reporting is unverified, the public version
is unapproved, registry authority/credentials are unverified, and publication is
not authorized. D-026 and D-069–D-078 remain Proposed. No registry publication,
tag, release, settings change or formal decision promotion occurred.
