# Review handoff

Open PR: T-923 from `docs/t923-norms-followups` — repo landing + hygiene
follow-ups from the owner-commissioned sector-norms review. Ships:

- [docs/plans/2026-11-17-sector-norms-review.md](docs/plans/2026-11-17-sector-norms-review.md):
  the full review record — method, verdict, strengths, norm gaps, ranked code
  findings, a prioritized signal-gated follow-up backlog, two parked owner
  decisions (GitHub repo description/topics text; npm `latest` dist-tag) and
  the adoption baseline (Packagist 590 downloads, 0 dependents).
- [.gitignore](.gitignore): adds `__pycache__/`, `*.pyc`,
  `packages/*/composer.lock` and `.mimosa/` — removes permanent git-status
  noise (scanner-ledger files, local locks, bytecode) and keeps clean-tree
  release preflights immune to local tool residue. Package locks stay on
  disk, untracked; example/acceptance locks remain tracked.
- `STATUS.md`, `TASKS.md` and `CHANGELOG.md` updated in place.

Verified on this machine: `python scripts/validate.py` green,
`python scripts/check_docs_links.py` green, and
`git status --porcelain --untracked-files=all` shows only the intentional
changes after a full validation run.

Docs and hygiene only; no runtime, schema, fixture or release bytes change.
Two outward-facing items (repo metadata, dist-tag) are deliberately not
applied — they are owner decisions recorded in the review note.
