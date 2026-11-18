# Review handoff

Open PR: T-926 from `chore/t926-python-lint` — the Python lint gate, the
next selected item from the
[sector-norms review backlog](docs/plans/2026-11-17-sector-norms-review.md).
Ships:

- `pyproject.toml` (new): dev-tooling config only — ruff, `target-version
  py312`, rule set pinned to the signal-bearing core (pyflakes `F` +
  syntax/runtime `E9`). Ruff's newer releases ship a much broader default
  (99 findings incl. import sorting, simplify, pylint rules), and E7xx/E402
  conflict with the repo's established compact style and deliberate
  sys.path-then-import pattern — both deferred, documented in the config,
  to be raised over time like the PHPStan level (T-924).
- Scope: the `scripts` tree (release/conformance tooling + its unittest
  suite). The examples' Python acceptance tests are a later raise.
- `scripts/tests/test_alpha_publication_artifact.py`: one unused-import
  removal (the only finding; behavior-preserving).
- `requirements-dev.txt`: adds `ruff>=0.17,<0.18` (resolved 0.17.5).
- `validate.yml`: new `python-lint` job (Python 3.12, installs
  requirements-dev, `ruff check scripts`; `persist-credentials: false`).
- `STATUS.md` / `TASKS.md` / `CHANGELOG.md` / review-doc backlog updated.

**Verified:** `ruff check scripts` green (0 findings); Python test suite
green (`unittest discover scripts/tests`, 1 pre-existing skip); `validate.py`
green; docs link checker green; release guardrails green with the new job.

Tooling only; no runtime, schema, fixture or release bytes change.
