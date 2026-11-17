# Review handoff

Open PR: T-924 from `chore/t924-phpstan` — PHPStan static analysis for the
Laravel kernel, the first selected item from the
[sector-norms review backlog](docs/plans/2026-11-17-sector-norms-review.md)
("Next" list, owner-approved order). Ships:

- `packages/laravel/phpstan.neon`: level 5, `src` only, includes the
  committed baseline; header comment records the policy (deliberate
  fail-closed guards live in the baseline; raise level / shrink baseline
  over time).
- `packages/laravel/phpstan-baseline.neon`: 23 entries — 22 are deliberate
  fail-closed runtime guards (`is_string`/`instanceof`/`array_is_list` on
  declared-typed values, `array_values` on already-lists) that removing
  would weaken trust-boundary checks; 1 is the consumer-trait-mediated
  `presentSurfaceRelayConfirmation()` bridge call PHPStan cannot see; 1 is
  the consumer-applied trait flagged unused in-repo.
- `packages/laravel/composer.json`: `phpstan/phpstan ^2.1` dev dependency
  (resolved 2.3.6) + `composer phpstan` script.
- `.github/workflows/validate.yml`: new `phpstan` job (PHP 8.4, latest
  Illuminate cell, no DB service) running `vendor/bin/phpstan analyse
  --error-format=github`; fails on any finding outside the baseline.
- `STATUS.md` / `TASKS.md` / `CHANGELOG.md` / review-doc backlog updated.

Verified on this machine (Docker `sr-php84-node`, PHP 8.4): level sweep
(L1: 0, L5: 23, max: 47) → level 5 + baseline chosen; `composer phpstan`
green with baseline; canary check — a deliberately broken file produced
exactly one new error while the baseline held, then was removed;
`composer validate --strict` green; `python scripts/validate.py` and the
docs link checker green.

Tooling only; no runtime, schema, fixture or release bytes change. The
baseline does not mask trust-boundary logic: it documents it.
