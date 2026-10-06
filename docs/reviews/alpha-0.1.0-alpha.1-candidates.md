# T-901 alpha local candidate baseline

Target: `0.1.0-alpha.1`, selected by the owner for preparation.
Tested source: `68511dee72a8246ef86a0e8931a63c3fcda0e961`, merged main before
the alpha preparation documentation. This is baseline evidence, not a final
publication artifact or proof for a later revision.

## Execution and retained evidence

Default readiness builders ran from a clean detached clone of the exact source.
They materialized its Git archive and freshly rebuilt browser distribution.
The host checkout was read-only; owner lockfiles were excluded from the clone.

Docker environment: Python 3.12.15, Node 22.23.3/npm 10.9.9, PHP 8.4.26,
Composer 2.10.3. Actual logs report **158 Python tooling tests passed**;
canonical validation including **22 HTMX fixtures** and publication guardrails pass.

| Package | Archive | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| `surfacerelay/laravel` | `surfacerelay-laravel-0.1.0-alpha.1.zip` | 116632 | `0aefa0e9c2366188d08e83d978b903f2686e99d5fc9778aff14d5b284510825b` |
| `@surfacerelay/browser-runtime` | `surfacerelay-browser-runtime-0.1.0-alpha.1.tgz` | 18762 | `9fe205f7fce60e987853a2933253bc06894d31b8b98e9a276de18afecb5d384a` |

Content-manifest hashes:

- Laravel: `b1a73246b4a7ae386d03016a5ad4159071627d4fa397d99b089ff22a620f58d5`.
- Browser: `f2b4fdc1c09c0cb18fcb7588872dabfacf7fbb2fb9f88c09e857790415ff43d5`.

Both candidate identities were verified after actual archive installation in
separate clean consumers. Browser root import, declarations/typecheck, Vite bundle,
DriverRegistry smoke and representative deep-import rejection passed. Laravel
Composer artifact install and bounded ActionBus smoke passed on PHP 8.4/Laravel 13.
Ignored `.env` and stale `dist` sentinels were excluded and preserved in the
temporary source. The task container exited successfully.

Logs, archives, manifests and JSON evidence were copied to the ignored local
`.tmp/t901-alpha1-evidence/` directory before removing the task container/network.
Copied archive sizes and archive/manifest hashes were independently checked on
the host. The browser tarball manifest was inspected: version `0.1.0-alpha.1`,
`private: true`. No image or worktree was created; pre-existing resources remain.
Independent review of the plan and verification helper found no blockers;
that review was source-only.

## What this does not close

This proves a local candidate baseline. The browser tarball retains its M8
private/non-public metadata; it is not ready for npm publication unchanged.
The ActionBus smoke uses fixture pass-through policy stages and does not satisfy
T-902's actual application security acceptance. The local Laravel proof covers
one PHP/framework leg; baseline CI covers the existing installation matrix using
its internal verification version, not this exact alpha archive.

The readiness tool still returns its fixed NO-GO/M8 defaults, including
`public-version-not-approved`. The selected alpha target is recorded in
[the preparation plan](../releases/0.1.0-alpha.1.md); the default string is not
a current owner-approval audit. T-903 must prepare the reviewed publication path.
Private security reporting remains disabled; registry authority/credentials,
applicable decision dispositions and publication authorization remain open.
No package source metadata, decision status, tag, release or settings changed.
