# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged release documentation/readiness and WebMCP/Filament work.

## Latest correction — T-805 source isolation

The previous readiness implementation copied the live checkout after a Git clean
check, allowing ignored source files or stale distribution into a SHA-labeled artifact.
Both builders now consume a temporary archive of the requested revision. The default
browser builder gets freshly compiled output there. Unsafe archive entries fail closed;
extraction/build/verification failure does not produce aggregate evidence.

TDD proved three failing regressions first; all 17 readiness tests now pass.
Docker full tooling: 111 tests plus canonical validation and publication guard pass.
Real default builders produced both artifacts; isolated browser and Laravel consumers
passed. Injected ignored `.env` and stale `dist` were excluded and originals preserved.
Independent correction review found no material remaining issue.

## Integration focus

- Review the full PR confirmation/store contract, approved-receipt session boundary,
  HTMX request/result correlation and Filament selection synchronization separately.
- Node/fixture and flag-enabled browser proofs are bounded; they are not general
  WebMCP certification or production-security approval.
- Readiness evidence remains pre-merge. Public API of the readiness function and core
  runtime contracts are unchanged by this correction; publication always stays NO-GO.
- User Composer lockfiles are excluded. Check the latest PR CI before approval.

Next gate: external integration review/finding disposition. Merge, decision promotion,
settings changes and publication need explicit authorization.
