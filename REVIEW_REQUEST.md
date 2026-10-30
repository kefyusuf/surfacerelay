# Review handoff

Open PR: T-918 from `docs/t918-webmcp-watch` — the Faz 5 momentum watch
([docs/plans/2026-10-28-webmcp-momentum-watch.md](docs/plans/2026-10-28-webmcp-momentum-watch.md)),
converting the project's last conditional bet into a defined monitoring
process: six signals (unflagged Chrome stable ship, second Chromium browser,
chrome-devtools-mcp graduation, `document.modelContext` API changes,
two-quarter stall → MCP-first re-weight, competing adoption → positioning
review) each with a **pre-agreed pivot**, an append-only monthly observation
log with the 2026-10-28 baseline, and rules that keep predictions out of the
log and task selection inside `STATUS.md`/`TASKS.md`. Monitoring only; no
runtime, schema, fixture or release bytes change.

Also on `main` this cycle: T-917 merged ([PR #61](https://github.com/kefyusuf/surfacerelay/pull/61))
plus one missed OUTREACH.md link-depth repair completed directly (0c69288;
260/260 links resolve — now 262 with this PR).

Context of the standing queue: the outreach phase is blocked on one
owner-authorized action — sending
[Draft 1](docs/consumers/outreach/DRAFTS.md) (live-verified liberu
candidate); consumer reports will arrive through the
`consumer-feedback` template with a defined intake; WebMCP momentum entries
land monthly. **After this PR, the repository-side queue is empty by
design** — every next task selection should come from one of those three
signal sources.

Review focus: the pivots are pre-agreed (so momentum observations cannot be
argued into scope creep), signal #4 respects ADR-0005 isolation and the
public-contract decision rule, and the baseline statements match the A2-08
evidence.
