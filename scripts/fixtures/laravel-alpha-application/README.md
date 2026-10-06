# Installed alpha application acceptance fixture

This disposable Laravel application installs the local `0.1.0-alpha.1` archive.
It uses Laravel session authentication with Eloquent users, database membership
resolution and Gate authorization, actual SurfaceRelay validation, confirmation,
idempotency, output redaction and durable audit adapters. It never uses
pass-through policy stages or business-level effect deduplication.

The runner initializes a fresh application with `php setup.php <appRoot>` and
serves `public/index.php` with several independent PHP workers. SQLite WAL and
shared file-cache locks provide real process concurrency. Each executed action
sleeps 800 ms and inserts an effect; `X-Worker-Pid` identifies the serving process.
The test-only `X-Acceptance-Barrier` header makes two distinct workers rendezvous
before dispatch; it changes no policy decision, store operation or effect.
Confirmation receipts expire after five seconds.

`GET /session` establishes a cookie and returns CSRF token plus server context.
Mutating routes require that token in `X-CSRF-TOKEN`. Login accepts the seeded
`owner`, `peer`, or `denied` addresses at `example.test` with the disposable
password `acceptance-password`. Owner belongs to both tenants; peer belongs only
to tenant A; denied belongs to A without refund permission. Login regenerates
the session identifier and returns the new CSRF token.

`POST /tenant` selects an authorized persisted membership. `POST /context`
selects persisted records in that tenant. `POST /invoke` accepts business input,
an optional receipt candidate, idempotency key, binding ID and non-authoritative
metadata. The server resolves actor, tenant, records, selection and session;
the Gate rechecks membership and every current resource on every invocation.
Invocation returns the package's normalized ActionResult.

`POST /approve` is an explicitly test-only human approval endpoint. It checks
the challenge's originating actor, tenant and HTTP session before minting a
receipt. `POST /binding` changes server registry state to expire, remove or use
an unsupported driver. `GET /evidence` observes effects, structured audit rows
and idempotency states without raw cached outputs or receipt/key candidates.
These controls are acceptance instrumentation, not production UX or endpoints.

This fixture qualifies the installed Laravel package in this narrowly defined
application. It does not prove a production deployment, real browser-agent
integration, external payment effects or atomic concurrent HTTP session writes.
