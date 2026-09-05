# Contributing

SurfaceRelay is architecture-sensitive infrastructure. Small changes are preferred over broad speculative abstraction.

## Before a change

1. Pick a task from `TASKS.md` or create a narrowly scoped task with equivalent acceptance criteria.
2. Read `AGENTS.md` and relevant ADRs.
3. For contract/security/public API work, start with a written plan.

## Required properties

- Protocol-neutral core types remain independent from adapters.
- Trusted runtime authority is never taken from ordinary caller input.
- New security-sensitive paths include negative tests.
- Public contract changes update schemas, fixtures, conformance notes, decision records, and migration impact.

## Verification

Always run:

```bash
python scripts/validate.py
```

Then run package-specific tests for the changed area.

## Commits and pull requests

Prefer one coherent task per PR. Include:

- task ID;
- problem statement;
- architectural impact;
- tests/verification evidence;
- security impact;
- follow-up work deliberately deferred.
