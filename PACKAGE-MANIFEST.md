# Starter Package Manifest

- **Prepared:** 2026-09-05
- **Working project name:** SurfaceRelay
- **Contract status:** 0.1 draft / experimental

## Included

- Research and competitive landscape.
- PRD, architecture, threat model, roadmap, decision register, glossary, open questions.
- 12 accepted architecture decision records.
- Provisional JSON Schemas for Action Definition, Runtime Binding, Invocation, and Action Result.
- Valid reference fixtures for Prep List and order operations.
- Laravel package skeleton with protocol-neutral enums/attribute/context/contracts.
- Browser runtime TypeScript skeleton with driver registry and WebMCP semantic projection.
- Repository workflow documents: root `AGENTS.md`, task board, `STATUS.md`, `REVIEW_REQUEST.md`, and an external-review prompt.
- Granular task board designed for one-task-at-a-time agent execution.
- External review checkpoint files (`STATUS.md`, `REVIEW_REQUEST.md`).
- CI starter and local schema validator.

## Verified before packaging

- JSON Schema Draft 2020-12 schemas: valid.
- All included JSON fixtures: valid against their schemas.
- PHP skeleton: `php -l` clean on all PHP files.
- Browser runtime source: `tsc --noEmit` clean with the included tsconfig (tests require installing dev dependencies).

## First local command

```bash
python -m pip install -r requirements-dev.txt
python scripts/validate.py
```
