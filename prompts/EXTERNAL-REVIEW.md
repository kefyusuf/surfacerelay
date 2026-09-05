# Prompt for Periodic External Review

Use this when asking another coding/review agent to inspect the GitHub repository:

```text
Review this repository's current implementation state. Start with STATUS.md and REVIEW_REQUEST.md, then inspect the current task changes/tests and relevant architecture/security docs.

Prioritize:
- correctness and regressions,
- Action Definition / Runtime Binding separation,
- trusted-context spoofing risks,
- stale-binding behavior,
- discovery vs invocation authorization,
- confirmation/idempotency issues,
- missing negative tests,
- scope creep or premature abstraction.

Return findings ordered by severity with concrete file evidence. Then state whether the current task is ready to close and what the single best next task is.
```
