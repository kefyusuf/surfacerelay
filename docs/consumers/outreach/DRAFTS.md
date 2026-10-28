# First-contact drafts (send-ready)

**Send rules:** owner-authorized, one candidate at a time, re-verify the
candidate's state on the day of sending (a pushed-at check takes one minute),
log every send in [CONTACT-LEDGER.md](CONTACT-LEDGER.md), wait two weeks
before any follow-up. The drafts are deliberately short; the details live in
the linked documents. Do not mass-post; do not paraphrase away the honesty.

Verified on 2026-10-28 via the GitHub API; re-check before sending.

---

## Draft 1 — liberu-ecommerce/ecommerce-laravel

Channel: GitHub issue on `liberu-ecommerce/ecommerce-laravel` (open issues are active).

> **Subject: Exposing one read-only "order inspect" action to WebMCP agents — would you try it?**
>
> Hi liberu team — your `ecommerce-laravel` is on Filament ^5.11 / Laravel ^13.38 / Livewire ^4.4.8, which is exactly the stack a small experiment of mine is verified against (I ran the whole acceptance on Filament 5.11).
>
> SurfaceRelay is an Apache-2.0, unofficial experiment that lets a Filament 5 app expose **one read-only action** — e.g. "inspect this order" — so a WebMCP-capable Chrome agent can discover and invoke it, while every security decision stays in your code: your Gate, your tenant logic, your audit trail. Caller input is never trusted.
>
> The ask: one unassisted pass (60–120 min, read-only, published packages only) through the quickstart on your own app, then a short report — including "I got stuck and gave up", which is equally valuable: https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/getting-started.md
>
> Details and boundaries: https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/outreach/OUTREACH.md
> Reports go through the *Consumer feedback* issue template; nothing is used as marketing, and no follow-up pressure. Feedback welcome here or on that template — and if this solves a problem you did not have, that is a useful answer too.

---

## Draft 2 — tomatophp/filament-ecommerce

Channel: GitHub discussion or issue on `tomatophp/filament-ecommerce`.

> **Subject: Let agents read order details in Filament apps (WebMCP experiment, Filament ^5.0)**
>
> Hi Tomatophp team — `filament-ecommerce` is one of the actively maintained Filament ^5.0 packages with an orders surface, so I would like to ask for one hour of honest testing rather than promote anything.
>
> SurfaceRelay (Apache-2.0, unofficial, experimental) exposes **one read-only action** from a Filament 5 app to WebMCP-capable Chrome agents — discovery and invocation through `document.modelContext`, with the Gate, tenant logic and audit staying entirely server-side. The published `0.1.0-alpha.3` packages are enough; no dependency on my sources.
>
> The ask: one unassisted quickstart pass on your demo app (60–120 min), then a short report — a blocker report is a complete report: https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/getting-started.md
>
> Boundaries and promises: https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/outreach/OUTREACH.md
> If WebMCP agent access to order details is not interesting for your roadmap, that is a useful answer too.

---

## Draft 3 — generic template (third candidate, e.g. LaravelDaily-directory project)

> **Subject: One read-only action to WebMCP agents — a CI-verified quickstart needs an honest first test**
>
> Hi — your {{project}} on Filament 5 looks like a good fit for a small experiment I am looking to validate with one real application.
>
> SurfaceRelay (Apache-2.0, unofficial, experimental) lets a Filament 5 app expose **one read-only action** to WebMCP-capable Chrome agents while every security decision stays server-side (your Gate, your tenant logic, your audit). The quickstart is CI-verified on a fresh registry consumer; the real-Chrome discovery/invocation proof is public.
>
> The ask: one unassisted pass (60–120 min) of https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/getting-started.md on your own app, then a short report via the *Consumer feedback* issue template — a blocker report is a complete report. Boundaries: https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/outreach/OUTREACH.md
> Not interesting for your roadmap? A one-line "no" is a useful answer.

---

## Send checklist (per candidate)

1. Re-verify `pushed_at` and the Filament constraint on the send day.
2. `python scripts/check_docs_links.py` — every link a candidate may click
   must resolve before outreach goes out.
3. Pick the channel from the draft; open the issue/discussion as @kefyusuf.
4. Log the send in [CONTACT-LEDGER.md](CONTACT-LEDGER.md) with the link and date.
5. Two-week cool-off before any follow-up; every reply (including "no") is
   logged and converted into `consumer-feedback` issues where applicable.
