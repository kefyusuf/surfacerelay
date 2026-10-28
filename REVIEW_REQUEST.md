# Review handoff

Open PR: T-917 from `docs/t917-outreach-intake` — outreach intake readiness,
closing the loop side before any outreach goes out. Ships:

- [REPORT-INTAKE.md](docs/consumers/outreach/REPORT-INTAKE.md): the defined
  process for every `consumer-feedback` report — 48h triage, five finding
  categories (docs fix / terminology / trust concern / product gap / not
  interesting), the evidence rule (one report = input, two independent
  reports of the same blocker = a decision-register input, a completed pass =
  the success criterion), running metrics, and reporter loop-closure.
- [scripts/check_docs_links.py](scripts/check_docs_links.py): standalone
  relative-link integrity check (pruned traversal, anchors skipped). First
  run: **255 links checked, 6 broken** — root-absolute-style pointers in the
  archive doc, an adapter-guide plan, and three in the freshly written
  intake/outreach docs; all six fixed and the check added to the per-candidate
  send checklist in DRAFTS.md.

Docs and one standalone script; no runtime code, schema, fixture or release
bytes change. `python scripts/validate.py` green; link check PASS (255/255).

Context: outreach is blocked on one owner-authorized action — sending
[Draft 1](docs/consumers/outreach/DRAFTS.md) (live-verified liberu
candidate). Merged this cycle with green checks: decision guide (PR #60),
drafts (PR #59), outreach kit (PR #58), quickstart (PR #57), native proof
(PR #56), wiring (PR #54), drift repair (PR #55).

Review focus: the intake's evidence rule deliberately blocks single-report
feature work; the link checker is standalone (not wired into validate.py) so
the verification contract stays unchanged; archive link repair touched only
a pointer, not archived content.
