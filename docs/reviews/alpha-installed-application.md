# Installed alpha application acceptance

T-902 qualifies the Laravel alpha candidate in a separate installed application.
This change adds acceptance instrumentation and CI without changing production
package code or the protocol-neutral contract.

## Candidate and environment

- Package: `surfacerelay/laravel`, installed `0.1.0-alpha.1`.
- Candidate source: `68511dee72a8246ef86a0e8931a63c3fcda0e961`, the T-901 baseline.
- ZIP SHA-256: `0aefa0e9c2366188d08e83d978b903f2686e99d5fc9778aff14d5b284510825b`.
- Docker PHP 8.4.26, Laravel 13.34.0, Python 3.12; SQLite WAL with 10-second
  busy timeout and shared file-cache locks. Composer installs the ZIP into an
  isolated real vendor directory; clean-consumer verification rejects source,
  path and symlink coupling and checks installed identity.
- [Application fixture](../../scripts/fixtures/laravel-alpha-application/README.md):
  real session Auth/Eloquent, DB membership and Gate. Actual package validation,
  confirmation, database idempotency, output redaction and structured database
  audit stages; no pass-through policy stages.

## Executed acceptance

| Boundary | Executable proof |
| --- | --- |
| Permitted actor | Safe output; one effect for actor 1, tenant A, order 101; matching completed audit with human confirmation |
| Untrusted authority | Guest/denied actor, forged metadata/resource fields and cross-tenant records cannot execute; matching authorization-halt audit |
| Input/key policy | Missing/empty key, oversized key and invalid reason return exact rejection codes |
| Approval scope | Actor/tenant transitions, changed input/record/selection and another session cannot reuse a receipt |
| Receipt lifecycle | Actual five-second expiry; consumed receipt with a fresh key cannot execute again |
| Idempotency | Safe completed replay; changed intent conflicts; revoked persisted permission rejects completed replay |
| Bindings | Approved call with unknown, expired, unsupported or removed binding returns HTTP 409 |
| HTTP security | Failed credentials, cross-session approval and forged CSRF token fail closed |
| Receipt race | Same receipt/different keys, distinct processes: one effect and one successful result |
| Key race | Same key/different receipts, distinct processes: one effect; subsequent replay succeeds without another effect |

Two independent HTTP processes serve the same installed app and stores. A
fixture-only filesystem barrier makes both enter before either dispatches;
tests assert distinct response PIDs and overlapping request windows. Each
execution waits 800 ms and inserts an effect, without business deduplication
that could hide a duplicate. Audit assertions exclude raw input, key, receipt,
password and executor secret. Safe evidence retains no raw receipt/key/output.

Test adequacy control: temporarily replacing the app Gate closure with `true`
fails the denied-actor test (`confirmation_required` versus `rejected`). The
runner restores the fixture in `finally`; all 11 real-policy tests pass.
OPcache is disabled so the control observes actual code. This mutation RED/GREEN
control is not a demonstrated production defect.

Retained local `.tmp/t902-evidence/application-acceptance.json` records archive,
fixture and test-suite fingerprints, installed versions, 11 passed tests,
5 controlled effects, 44 audit rows and completed idempotency states. Audit
count includes the mutation control. Python tooling: 159 tests; canonical validation,
22 HTMX fixtures, guardrails and all fixture PHP syntax pass.

## Reproduce

Use Linux/Docker with PHP 8.4, Composer, SQLite/mbstring/zip and Python 3.12:

```bash
python scripts/laravel_alpha_acceptance.py \
  --artifact /artifacts/laravel/surfacerelay-laravel-0.1.0-alpha.1.zip \
  --sha256 0aefa0e9c2366188d08e83d978b903f2686e99d5fc9778aff14d5b284510825b \
  --source-revision 68511dee72a8246ef86a0e8931a63c3fcda0e961 \
  --consumer /tmp/alpha-application --evidence /tmp/alpha-evidence
python -m unittest discover -s scripts/tests -q
python scripts/validate.py
python scripts/check_release_guardrails.py
```

The artifact directory needs matching `artifact-evidence.json`; consumer must
be empty. CI job `installed-alpha-application` builds from `git archive HEAD`,
records that revision/hash and uploads safe evidence. Check current-head PR CI
separately. A cleanup regression test verifies that an already-exited first
HTTP server does not prevent terminating the second owned process.

## Review and limits

Independent review weaknesses were fixed: exact errors, correlation-specific
completed/denied audit, safe replay data, approved unknown-binding rejection
and cleanup after server failure. HTTP binding registry is app-owned and separate
from ActionDefinition; this fixture does not qualify Livewire lifecycle behavior.

This is Laravel fixture-host acceptance; browser installed proof remains T-901.
Scope transitions do not isolate every changed scope field. No production UX,
real agent, purchase, distributed database, concurrent session-write safety or
all-platform qualification is claimed. Approval, observer, binding controls and
process barrier are test-only. Publication remains NO-GO; T-903 is next.
