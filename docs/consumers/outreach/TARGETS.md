# Candidate list for first external consumers

**Research snapshot: 2026-10-28.** Web-sourced, not contacted. Re-verify each
candidate's activity and Filament version immediately before any contact —
this list ages fast. Selection criteria, in order:

1. Stack match: Filament 5 + Livewire 4 on Laravel 12/13 (what the quickstart
   and the A2 evidence actually run).
2. Active maintenance: commits or releases within the last quarter.
3. A natural **read-only** action (orders, products, invoices) whose exposure
   is useful but harmless.
4. A reachable, public maintainer channel.

## Candidates

| Candidate | Why it fits | Watch-outs | Channel |
| --- | --- | --- | --- |
| [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Exact stack (Laravel 12/13, Livewire 4, Filament 5); orders + customers admin resources; modular and actively maintained | The old `liberu-eccommerce` Packagist vendor is abandoned (June 2026) — use `liberusoftware/`; confirm current Filament minor before contact | GitHub issues |
| [tomatophp/filament-ecommerce](https://github.com/tomatophp/filament-ecommerce) | Filament e-commerce plugin with orders and multi-tenancy; plugin-maintainer audience already ships Filament code | Plugin (not a full app) — the quickstart still applies to the author's demo app; confirm Filament 5 line | GitHub issues |
| [Lunar (lunarphp)](https://github.com/lunarphp/lunar) | Influential headless commerce with a Filament admin; an endorsement or a sharp report carries weight | Larger codebase, slower pace, verify Filament 5 alignment; treat as a second-wave target | GitHub discussions |
| [LaravelDaily Filament project directory](https://laraveldaily.com/code-examples/projects?stack=filament) | Curated list of complete community Filament apps — a sourcing channel for solo-maintainer candidates rather than one target | Individual projects vary in maintenance quality | Per-project |
| [AlizHarb/filament-themes-manager](https://github.com/AlizHarb/filament-themes-manager) | Recently updated (Jan 2026), solo maintainer, small surface — a fast first pass | Themes manager has no obvious order-style read action; candidate would pick their own action | GitHub issues |

## Sequencing

1. Owner picks 2–3 candidates (suggested start: liberu, tomatophp, one
   LaravelDaily-listed app) and re-verifies activity + stack on the day of
   contact.
2. Send [OUTREACH.md](OUTREACH.md) one-to-one (not mass-posted); link the
   feedback issue template.
3. File every report — including failures and non-responses — as
   `consumer-feedback` issues; the first **unassisted** completion is the
   T-914 success criterion.
4. Convert recurring blockers into decision-register inputs before any new
   feature work.
