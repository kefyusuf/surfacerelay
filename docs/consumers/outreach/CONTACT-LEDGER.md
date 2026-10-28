# Outreach contact ledger

One row per candidate contact attempt. The success criterion of the outreach
phase: **one external maintainer completes the quickstart unassisted** and
files a report. Every row links its outcome (report issue, "no", or silence
after the two-week window). Append rows; never rewrite history.

| Candidate | Channel | Sent (date) | Thread link | Outcome | Report issue |
| --- | --- | --- | --- | --- | --- |
| liberu-ecommerce/ecommerce-laravel | GitHub issue | — | — | not sent | — |
| tomatophp/filament-ecommerce | GitHub discussion/issue | — | — | not sent | — |
| (third candidate) | GitHub issue | — | — | not sent | — |

## Statuses used

- `sent` — first contact is out; two-week cool-off starts.
- `replied` — any reply; link it. Convert findings into a `consumer-feedback`
  issue even when the answer is "not interesting", then close the row.
- `report filed` — the maintainer completed (or attempted) the pass and filed
  feedback; link the issue.
- `completed` — an **unassisted** quickstart completion with discovery +
  invocation on their app. One such row ends the outreach phase successfully.
- `declined` / `no reply after follow-up` — logged, not chased.
