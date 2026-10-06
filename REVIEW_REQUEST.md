# Integration Review Request

Review [PR #34](https://github.com/kefyusuf/surfacerelay/pull/34) against main.
It contains unmerged T-804–T-809 documentation, readiness and browser/runtime work.

## Integration review handoff

The full integrated source diff has now received three independent agent reviews:
Laravel/Filament, browser/HTMX/WebMCP, and readiness/CI. No new actionable
production defect was established. [Coverage and finding disposition](docs/reviews/integration-pr-34.md)
records the reviewed revision, evidence and limits. This does not claim human
PR approval or authorize merge/publication.

Latest changes correct claims about session pull versus atomic receipt
consumption, current/history tracking and implemented readiness orchestration.
Only documentation and a PHP comment change; executable contracts are unchanged.

Docker Python tooling: 158 tests pass; canonical validation includes 22 HTMX
fixtures. Publication guardrails, changed PHP syntax and relative links pass.
Reviewed-source CI had all five workflows successful, including both Filament
16/16 runs. Check current-head CI before approval.

## Migration and review boundaries

- Custom ConfirmationStore implementations must migrate the unpublished
  `approvePending` signature and distinct-receipt atomic handoff (D-077).
- HTMX producers and runtimes must migrate together to the exact
  `surfacerelay:result.value` envelope. No legacy fallback; old output containing
  only an object-valued `value` is ambiguous. See [migration guidance](docs/consumers/browser-runtime.md#htmx-result-envelope).
- Fixed fixture identity, concurrent session writes, unrelated UI concurrency,
  reentrant HTMX hooks and back/forward-cache restoration remain unqualified.
- Native WebMCP proof is bounded flag-enabled page-caller evidence. Corrected
  artifact readiness is pre-merge and must be repeated on merged main.
- User Composer lockfiles are excluded. Decisions remain Proposed; publication NO-GO.

Next gate: human PR review/disposition and explicit merge authorization. Decision
promotion, settings changes, public version and publication remain separate gates.
