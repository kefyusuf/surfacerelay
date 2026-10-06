# Security policy

## Experimental support status

SurfaceRelay is experimental and unofficial. Local release-candidate artifacts
and CI results do not establish a supported public release. No security support
window, response-time SLA, or maintenance guarantee is established by this policy.
Re-check release status and this policy before adopting a candidate.

## Reporting a vulnerability

GitHub private vulnerability reporting is enabled for `kefyusuf/surfacerelay`.
The owner authorized activation on 2026-10-06; the setting was applied and
rechecked through the GitHub API (`enabled: true`).

Use the repository's [Security advisories page](https://github.com/kefyusuf/surfacerelay/security/advisories)
and select **Report a vulnerability**. See [GitHub's private reporting instructions](https://docs.github.com/en/code-security/how-tos/report-and-fix-vulnerabilities/report-privately).

Do not put sensitive vulnerability details, exploit instructions, credentials,
customer data, or private logs in public issues, pull requests, or discussions.
The repository owner, `kefyusuf`, is responsible for checking this intake and
coordinating private triage. Reports should identify the affected version and
provide a minimal reproducer, impact and relevant trust boundary; exclude
unnecessary credentials or customer data. The owner reviews scope privately,
coordinates a tested correction with the reporter and decides disclosure timing.
No response-time SLA or fixed public disclosure deadline is promised.

No alternative private email/form is designated. If the reporting option is
unavailable, retain details privately; a public issue may request a contact but
must not contain the vulnerability details. Activation is verified, but no test
report was submitted and notification delivery has not been independently tested.

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
