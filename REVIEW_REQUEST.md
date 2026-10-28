# Review handoff

Open PR: T-916 from `docs/t916-when-to-use` — the Faz 4 positioning
sharpening: [docs/consumers/when-to-use.md](docs/consumers/when-to-use.md),
the consumer-facing decision guide. It leads with the honest exclusion
("stateless actions over clean endpoints: write a plain MCP tool, skip
SurfaceRelay"), gives the decision table (live UI state / consequential
enforcement / multi-surface projection vs the explicit out-of-scope rows),
the niche signals, the canonical Filament-orders contrast, and a cost
statement per action. It marks the proven surface as read-only today and
links POSITIONING, the quickstart and the threat model. POSITIONING.md and
the quickstart now point at it. Docs only; no runtime, schema, fixture or
release bytes change.

Context: the outreach phase is blocked on one owner-authorized action —
sending [Draft 1](docs/consumers/outreach/DRAFTS.md) (live-verified liberu
candidate) — with the merged kit (PR #58), drafts (PR #59), quickstart
(PR #57), wiring (PR #54), native proof (PR #56) and drift repair (PR #55)
all green.

Review focus: the guide admits where SurfaceRelay should NOT be used and
states the write-path boundary honestly (designed, not acceptance-complete);
check it does not contradict POSITIONING or the threat model.
