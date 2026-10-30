# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-920 — independent QA follow-ups (selected; PR pending). An adversarial sub
agent review reproduced the verification baseline exactly, reviewed 11
consumer-facing files against all architecture rules (no critical or major
findings), and verified the quickstart document is verbatim-faithful to the
shipped example. Its two cosmetic recommendations are fixed here: the
quickstart glue catches registration failures diagnostically and pre-checks
descriptor identity; when-to-use.md disambiguates "hosted acceptance
receipts" from confirmation receipts. The owner has parked the first
outreach send (Draft 1) for later; no runtime code changes.

## Next gate

Merge of the T-920 QA follow-ups; after that the repository-side queue is
empty by design — next task selection comes from the three signal sources
(the parked outreach send, consumer-feedback reports, monthly momentum
watch entries) or an owner decision.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
