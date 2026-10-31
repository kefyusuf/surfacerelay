# Review handoff

Open PR: T-921 from `docs/t921-docs-refresh` — the entry-point documents
updated to everything merged since alpha.3 (PRs #54–#64). Ships:

- `README.md`: the repository-status acceptance sentence now includes the
  real-Chrome `document.modelContext` discovery/invocation proof with
  fail-closed negatives (A2-08 evidence); the guide list and the development
  reading order link [when-to-use.md](docs/consumers/when-to-use.md); the
  Validation section documents the standalone link checker.
- `CHANGELOG.md`: the Unreleased section now records the full post-alpha.3
  cycle (T-912…T-920) — quickstart, decision guide, outreach kit and drafts,
  momentum watch, link checker, the A2-08 native proof under Added, and the
  T-912 notification-drift / T-919 Windows-cleanup fixes under Fixed. No
  registry claim changes: packages remain `0.1.0-alpha.3`.

Docs only; no runtime, schema, fixture or release bytes change.
`python scripts/validate.py` green; `python scripts/check_docs_links.py`
green.

Review focus: the CHANGELOG claims stay descriptive of merged, CI-verified
work (no registry claim changes, no dates for Unreleased); README positioning
remains conservative per the documentation discipline.
