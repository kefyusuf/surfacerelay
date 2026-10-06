# Registry-installed Browser Pilot Review

Branch: `feat/t-905-docker-browser-pilot`. Task: T-905, awaiting owner acceptance.

The standalone local Docker application installs the exact published Laravel
and browser-runtime alpha from Packagist/npm locks. It exposes tenant-scoped
orders and one simulated refund through an app-owned HTTP driver. No core,
public contract or existing adapter changes; no real financial side effects.

Review authentication/membership/Gate rechecks, exact binding identity,
challenge-bound human summary, receipt scope, concurrent replay and session-only
evidence. `/review` carries app-owned display context separately from the
canonical ActionResult. The UI fails closed when review retrieval fails.

Verification: Docker 19 HTTP tests including two-worker races, real expiry,
observer/discovery/summary negative checks; 4 Node DOM-wiring regressions using
the published runtime; PHP syntax, build, strict Composer and canonical/22 HTMX
fixtures pass. Independent static review found no remaining blocker. Native
Codex in-app WebMCP calls require approval, execute once, replay without another
effect and require a new accurate summary after selection changes.

The earlier HTML HTTP 500 was reproduced and fixed by setting the middleware
header through Symfony's response headers API. No pre-implementation test RED
is claimed for the entire new app. Node tests use a deterministic DOM/HTTP stub;
native evidence is specific to the live in-app pilot, not general certification.

Owner manual results and PR CI are pending. The loopback-only
`surfacerelay-t905-pilot` stack remains running for owner tests; no volumes or
new images. Existing Docker resources and both user Composer locks are preserved.
See [manual guide](examples/alpha-pilot/README.md).
