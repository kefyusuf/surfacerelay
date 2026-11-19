# Review handoff

Open PR: T-928 from `chore/t928-ci-hygiene` — CI hygiene, the next item of
the owner-grouped norms backlog. Config-only workflow changes:

- **validate.yml**: top-level `concurrency` group per ref with
  cancel-in-progress; `timeout-minutes` on all 17 jobs (10 lint/contract,
  15 release-contract/phpstan, 30 the rest); `cache: composer` on all 10
  setup-php steps; `cache: pip` + `cache-dependency-path:
  requirements-dev.txt` on all 12 setup-python steps.
- **filament-live.yml / htmx-fixture.yml**: concurrency + 30-minute job
  timeout; `htmx-fixture` gains a monthly schedule (`0 7 1 * *`) mirroring
  filament-live, so its fresh npm/Playwright resolutions surface drift
  monthly too.
- **Six consumer-gate workflows** (a2-02-browser-b2, a2-02-read, a2-02b-b1,
  a2-08-page-wiring, a2-alpha3, t913-quickstart): concurrency groups.

Honest limitation: the branch-push + PR double run remains — same-tree ref
groups cannot dedup a push and its pull_request event; caches keep both
cheap. npm-cache additions to the remaining 5 setup-node steps are deferred
(each needs a distinct lockfile path; the heavy npm jobs already cache).

Verified: every workflow YAML-parses with `concurrency: True` and no job
without `timeout-minutes`; guardrails and `validate.py` green. No runtime,
schema, fixture or release bytes change.
