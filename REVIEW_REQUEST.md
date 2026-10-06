# Alpha Preparation Review Request

Branch: `release/t-901-alpha-scope-candidates`.
Task: T-901, owner-selected `0.1.0-alpha.1` scope freeze and local candidate baseline.

## Change and evidence

[The alpha plan](docs/releases/0.1.0-alpha.1.md) freezes the Laravel/browser pair,
defines finite T-901–T-904 gates and the next installed-application acceptance
matrix. README, compatibility and tracking now distinguish selected target from
publication approval. No executable code, source package metadata or decision
status changes.

[Candidate evidence](docs/reviews/alpha-0.1.0-alpha.1-candidates.md) records the
exact merged baseline, environment, archive/manifest hashes and retained logs.
Both actual alpha archives passed installed-identity and clean-consumer proofs.
Docker Python: 158 tests; canonical validation: 22 HTMX fixtures; guardrails pass.
Sentinel exclusion/preservation and copied hashes passed. Independent source
review found no blockers. Check this branch's CI before merge.

## Review boundary

- Candidates belong to the recorded baseline, not a later source revision.
- The browser candidate remains `private: true`; M8 tooling always reports NO-GO.
  Its default version-approval blocker does not model the recorded owner selection.
- Local ActionBus smoke uses pass-through fixture policies; T-902 actual
  application authentication/tenant/concurrency acceptance is still pending.
- Private reporting is disabled; registry authority/credentials remain unverified.
- Owner lockfiles remain excluded. Logs/candidates were retained before task
  container/network cleanup; pre-existing images and other resources remain.

Next task: T-902 after this handoff. T-903 prepares publication eligibility through
reviewed work; T-904 needs explicit final publication authorization. Settings,
formal decision promotion, tags/releases and registry publication remain gated.
