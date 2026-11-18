# Review handoff

Open PR: T-927 from `chore/t927-dependabot` — dependency-update automation,
the next item of the owner-grouped norms backlog. Ships
`.github/dependabot.yml` (new):

- **github-actions** weekly, grouped into single PRs, limit 5 — the actions
  are tag-pinned, so bumps surface deliberately.
- **pip** weekly (grouped) for `requirements-dev.txt`.
- **composer** monthly (limit 2) on `packages/laravel` + `packages/laravel-mcp`
  and **npm** monthly (limit 2) on `packages/browser-runtime` +
  `packages/openapi-importer` — only the publishable package manifests.
- Examples and acceptance fixtures are deliberately NOT watched: they are
  drift probes (fresh Composer resolutions; path-gated proofs with monthly
  schedules per T-925), so automated example bumps would be noise, not
  signal.

Verified: YAML parses; `validate.py`, release guardrails and the docs link
checker green. Config-only; no runtime, schema, fixture or release bytes
change. Dependabot itself starts scheduling after the merge.
