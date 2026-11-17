# Sector-norms review (2026-11-17)

**Purpose.** The owner commissioned a review of the repository against common
open-source norms (code quality, testing, CI/CD, security posture, release
engineering, packaging, community infrastructure). This note records the
findings and a prioritized follow-up backlog. Like the
[momentum watch](2026-10-28-webmcp-momentum-watch.md), it is a **signal source
for owner task selection, not a promise of work**: entries here become tasks
only by owner decision recorded in `STATUS.md`/`TASKS.md`.

Baseline: `main` at `6fe18ae` (T-922 merged), `validate.py` green, CI green.

## Method

Two independent source-code audits (one implementation-focused, one
infrastructure-focused) plus local re-verification: `python
scripts/validate.py` (green), hosted CI state, package registry stats, and
repository metadata. Read-only; no runtime or contract files changed.

## Verdict

Engineering discipline is above norm for an alpha-stage project; the gaps are
mostly community-infrastructure items that only matter once a community
exists. The dominant risk remains adoption, not engineering debt.

## Strengths (above norm)

| Area | Evidence |
| --- | --- |
| Architecture rules enforced by tests, not prose | [FilamentDependencyPolicyTest](../../packages/laravel/tests/Unit/FilamentDependencyPolicyTest.php) scans source to keep `Definition/` and `Binding/` framework-free; public API is allowlist-tested |
| First-class negative-path security testing | 575 PHPUnit + 199 vitest cases; forged receipts, stale bindings, replay conflicts, `__proto__` and protocol-field-leak probe fixtures |
| Trust boundaries fail closed with named typed exceptions | missing redactor erases output; receipts via `hash_equals` with challenge≠receipt; raw idempotency keys never persisted; zero-argument `TenantResolver::resolve()` |
| CI runs the real suites | PHPUnit 2×2 matrix with a MySQL 8.4 service container, vitest, Playwright (path-gated), ~15 Python unittest modules, registry-installed consumer proofs |
| Release engineering | SHA-256 content manifests, clean-tree preflights, a guardrail scanner that structurally forbids automated publishing, Keep-a-Changelog with PR/task references |
| Hygiene | zero TODO/FIXME markers anywhere; `declare(strict_types=1)` in every PHP file; `tsc --noEmit` strict |

## Norm gaps

| Item | Status | Weight at this stage |
| --- | --- | --- |
| PHP static analysis (PHPStan/Psalm) | absent (`php-lint` is syntax only) | medium-high — cheapest large win; codebase is already typed |
| Python lint/type tooling (ruff, mypy, `pyproject.toml`) | absent; `requirements-dev.txt` is range-pinned without a lock | medium |
| Dependabot/Renovate | absent for deps and actions | medium — Filament drift already bit once (T-912) |
| CI hygiene (composer/pip caching, `concurrency`, `timeout-minutes` on validate jobs, push+PR double runs) | absent | medium |
| Coverage measurement | `coverage: none` everywhere | low |
| Branch protection on `main` | absent; docs commits land by direct push | medium (single-maintainer pragmatic, but norm-outside) |
| Actions pinned by tag, not SHA | `checkout@v4` etc. | low (public repo, zero secrets in workflows) |
| `CODE_OF_CONDUCT.md`, bug/feature issue templates | absent | low until contributors arrive |
| Signing/SBOM/SLSA provenance | SHA-256 manifests only | low at alpha |
| `packages/laravel-mcp` publishability | `dev-main` dependency + path repository — unpublishable as-is | deferred until a publication decision |

## Code findings (ranked)

1. **Hasher duplication.** `ConfirmationScopeHasher` and
   [IdempotencyIntentHasher](../../packages/laravel/src/Idempotency/IdempotencyIntentHasher.php)
   duplicate ~40 lines of identical canonicalization and hardcode the same
   five-entry trusted-context list twice — a drift risk in trust semantics. A
   shared trusted-scope-document builder would remove it.
2. **Anonymous-caller idempotency partition.** Key-less, session-less callers
   share a global partition
   ([IdempotencyKeyHasher](../../packages/laravel/src/Idempotency/IdempotencyKeyHasher.php)),
   so a key+input collision across anonymous callers can replay across
   callers. Documented in [GLOSSARY.md](../GLOSSARY.md) but not surfaced near
   the code.
3. **Silent indeterminate transition.** The `catch (\Throwable)` around the
   indeterminate mark in
   [ActionExecutionStage](../../packages/laravel/src/Runtime/Pipeline/ActionExecutionStage.php)
   is justified (conservative in-progress row blocks re-execution) but
   unobservable without audit instrumentation.
4. **Conformance target depends on a `.tmp` build output**
   ([browser-htmx.json](../../conformance/targets/browser-htmx.json)) — a
   build step must precede conformance runs or they fail; fragile.
5. **TS strictness gaps.** `noUncheckedIndexedAccess` /
   `exactOptionalPropertyTypes` are off; safe today by manual bounds checks,
   not compiler-enforced.

## Prioritized backlog

### Now — pre-outreach (owner-selected as T-923)

- [x] `.gitignore` hygiene: `__pycache__/`, `*.pyc`, package-local Composer
  locks, `.mimosa/` — removes permanent status noise and keeps clean-tree
  release preflights immune to local tool residue.
- [x] **Owner decision (2026-11-18): applied** — the GitHub repo
  description/topics proposal below was set via the API with owner approval.
- [x] **Owner decision (2026-11-18): keep** — npm `latest` remains
  `0.1.0-alpha.1`; the note below stays for future reference.

### Next — parked follow-up candidates (each needs owner selection)

- PHPStan at a low level on `packages/laravel`, raised over time.
- ruff + `pyproject.toml` for `scripts/` and the Python test suite.
- Dependabot, at minimum for GitHub Actions.
- CI hygiene: composer/pip caching, `concurrency` groups, `timeout-minutes`
  on validate.yml jobs, dedup push/PR runs.
- Branch protection on `main` (require PR + validate).

### Deferred (recorded, not scheduled)

- CoC and bug/feature issue templates (when contributors arrive); coverage
  measurement; signing/SBOM/SLSA (pre-publication); `laravel-mcp`
  publishability; the code findings above.

## Static-scanner triage (2026-11-18)

The repository's pre-commit scanner reported 12 pre-existing high-severity
findings, all in acceptance tooling that T-923 did not touch. Review of the
flagged lines classifies each as a recognized static-analysis
false-positive class for this repo's own harness:

- **SSRF** — `urllib.request.urlopen` targets the harness's own locally
  spawned `php -S` server (`self.url`), not external or user-controlled
  input.
- **Hardcoded credentials** — `password: "acceptance-password"` for
  `user@example.test`: synthetic fixture data for a disposable generated
  consumer (RFC 2606 `.test` domain), not a real secret.
- **Code injection** — the T-910 generator `exec()`s the T-908 generator
  source through a count-guarded transformation of committed repo files;
  no external input.

No runtime code is affected. The findings stay visible to the scanner;
accepting them as tooling patterns is the owner decision recorded here.

## Parked owner decisions

1. **GitHub repo description/topics.** Proposed text (applied 2026-11-18 with
   owner approval):
   description — "Protocol-neutral action contract and reference runtimes for
   exposing stateful web-app actions to AI agents (Laravel/Livewire/Filament/
   HTMX; WebMCP projection). Experimental."; topics — `php`, `laravel`,
   `livewire`, `filament`, `htmx`, `mcp`, `webmcp`, `ai-agents`,
   `agent-tools`. Rationale: outreach recipients land on an empty
   description/topics page today.
2. **npm `latest` dist-tag.** `latest` points at `0.1.0-alpha.1` while
   `alpha` points at `0.1.0-alpha.3`; README says "install explicit versions".
   Deliberate, but unusual for consumers browsing npm. Move `latest` to
   alpha.3 or keep — owner decision 2026-11-18: **keep** at alpha.1.

## Adoption baseline (2026-11-17)

Packagist `surfacerelay/laravel`: 590 total downloads (306 monthly, 0 daily),
0 dependents. npm `@surfacerelay/browser-runtime`: alpha.1/2/3 published
2026-10-06/07/08. GitHub: 0 stars/forks/watchers. 972 commits, single
contributor, repository created 2026-09-05. This confirms the standing
assessment: the binding constraint is external demand, and the queue stays
signal-gated per `TASKS.md`.
