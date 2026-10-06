# Security policy

## Experimental support status

SurfaceRelay is experimental and unofficial. Local release-candidate artifacts
and CI results do not establish a supported public release. No security support
window, response-time SLA, or maintenance guarantee is established by this policy.
Re-check release status and this policy before adopting a candidate.

## Reporting a vulnerability

**A private reporting channel is not currently established.** GitHub private
vulnerability reporting was checked read-only on 2026-10-06 for
`kefyusuf/surfacerelay` and returned `enabled: false`. This dated observation can
change; it is not a promise of future channel availability.

Do not put sensitive vulnerability details, exploit instructions, credentials,
customer data, or private logs in public issues, pull requests, or discussions.
This repository does not designate an alternative private email address, form,
or disclosure channel. Retain sensitive details privately until a verified
private channel is established and documented here.

Establishing and verifying a private intake channel is a **publication blocker**.
Repository settings, contact ownership and a disclosure process require separate
authorized work; writing this document does not enable them. No public disclosure
timeline or coordinated-disclosure commitment is defined yet.

## Application integration responsibilities

The [threat model](docs/THREAT-MODEL.md) and
[architecture](docs/ARCHITECTURE.md) define the implemented trust boundaries:

- Caller input is untrusted. Actor, tenant, current record/selection and
  confirmation authority come from trusted runtime context.
- Discovery authorization and invocation authorization are separate checks.
- Consequential actions require an opaque runtime-issued confirmation receipt;
  a caller-supplied confirmation boolean does not grant authority.
- Required idempotency is enforced server-side. Binding identity, supported
  drivers and lifecycle must be checked; stale or unsupported bindings fail closed.
- Output handling and audit integration must preserve application data boundaries.

Applications must configure and verify their real policy stages and runtime
integration. The [Laravel consumer smoke](docs/consumers/laravel.md) uses
fixture-only pass-through handlers for validation, authorization, idempotency and
confirmation. Those handlers are not production security guidance. The
[browser consumer proof](docs/consumers/browser-runtime.md) checks packaging and
bounded Node behavior; it does not certify browser/WebMCP security or deployment.

This policy describes current limitations and existing trust rules. It does not
introduce a new runtime contract, certify deployments, or authorize publication.
