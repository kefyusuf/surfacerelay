# Consumer report intake — from feedback to decision input

Every `consumer-feedback` report — including non-completions and "not
interesting" answers — is processed through this intake. The point is that
the first external signals land in a defined process instead of ad-hoc
reactions, and that **a single report never triggers feature work**.

## 1. Triage (within 48h of filing)

- Thank the reporter; state what happens next. No debate in the issue.
- Record the report **verbatim** — quotes, not paraphrases — in the triage
  table below.
- Classify the primary finding:
  - **Docs fix** — the document was wrong, missing a step, or ambiguous.
    Fix in one small PR referencing the report.
  - **Terminology** — a defined term did not carry its meaning. Fix in the
    [glossary](../../GLOSSARY.md) or the document; recurring occurrences
    become a naming decision.
  - **Trust concern** — something felt risky or unexplained. Cross-check
    against the [threat model](../../THREAT-MODEL.md); any real gap opens a
    security review, not a doc patch.
  - **Product gap** — a missing capability the reporter needed for their
    real action. Log it; it becomes feature work only per rule 3 below.
  - **Not interesting** — the problem we solve is not one they have.
    Recorded as market signal; no action.

## 2. Evidence rules

- **One report** = an input, not a mandate. Docs fixes are always allowed;
  contract or feature changes are not.
- **Two independent reports** of the same blocker = a
  [decision register](../../DECISION-REGISTER.md) input (proposed decision
  with the reports as evidence).
- **A completed pass** (discovery + invocation on their own app) = the
  outreach success criterion; record it in the
  [ledger](CONTACT-LEDGER.md) and propose the next task with that evidence.

## 3. Metrics kept here (updated per report)

| Metric | Value |
| --- | --- |
| Reports filed | 0 |
| Completed unassisted passes | 0 |
| Median time-to-first-tool (where reported) | — |
| Blockers converted to docs fixes | 0 |
| Blockers escalated to decision register | 0 |

## 4. Reporting back

The reporter is told the outcome of their triage (fixed in PR X / logged as
decision input Y / recorded as market signal). Closing the loop with the
reporter is part of the intake, not an extra.
