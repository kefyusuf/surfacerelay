# Consumer outreach — the honest ask

**Owner-authorized activity.** This kit prepares everything; actually contacting
maintainers happens only with the owner's approval. The research snapshot in
[TARGETS.md](TARGETS.md) is dated and must be re-verified before any contact.

## What we ask an external maintainer to do

One unassisted pass through
[Getting started: expose one read-only action](../getting-started.md) on **your
own** Laravel + Filament 5 application, then file the structured report
([feedback form](FEEDBACK-FORM.md) or the GitHub *Consumer feedback* issue
template). Estimated time: 60–120 minutes. No SurfaceRelay source checkout is
needed — only the published `0.1.0-alpha.3` packages and your app.

## Why this is worth your hour

- Your application's actions are currently invisible to WebMCP agents. The
  quickstart makes **one read-only action** discoverable and invokable from a
  Chrome 150+ agent surface while every security decision stays in your code:
  your Gate, your tenant logic, your audit trail. The caller input is never
  trusted.
- The path is CI-verified end to end on a fresh registry consumer; the real
  Chrome discovery/invocation proof is public
  ([A2-08 evidence](../../../scripts/acceptance/a2-08-native-chrome/README.md)).
- Your report directly shapes the next release: blockers and terminology
  confusion go into the decision register as first-class inputs.

## What we promise in return

- Read and respond to every filed report.
- No marketing claims from your report: results are recorded as evidence, not
  endorsements, and the repository stays marked experimental.
- No follow-up pressure: one report completes the ask.

## What we do NOT ask

- No production exposure, no write or destructive actions, no payment data —
  the quickstart is deliberately read-only.
- No dependency on unreleased source: everything runs from published alpha
  packages.
- No code contributions required (welcome, though — see CONTRIBUTING.md).

## Contact

Open a *Consumer feedback* issue on this repository, or reach the owner
(@kefyusuf) directly. Reports may be private if the template's public form
feels wrong for your findings — say so and a private channel will be provided.
