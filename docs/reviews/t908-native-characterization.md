# T-908 Native Error Characterization

Status: Historical Phase 0 evidence. The owner subsequently approved Option A;
D-079 and the migration guide record its implementation. This report alone does
not claim application/SQL acceptance, PR or release.

## Observed boundary

Chrome 154.0.8037.98 with the locally configured official
`chrome-devtools-mcp@1.10.1`, isolated headless profile and WebMCP enabled, loaded
the standalone native fixture on `http://127.0.0.1:4187/`. JavaScript evaluation
was disabled; discovery and execution used the native WebMCP tools.

The fixture registers directly with `document.modelContext`; it does not import
SurfaceRelay or make application writes. This reproduces the problem without a
framework driver. It does not prove which upstream layer loses the message.

| Callback behavior | Native status | Native error text/output |
| --- | --- | --- |
| Reject plain object | Error | Empty `errorText` |
| Reject string | Error | Empty `errorText` |
| Reject number | Error | Empty `errorText` |
| Reject null | Error | Empty `errorText` |
| Reject undefined | Error | Empty `errorText` |
| Reject standard Error with fixed safe text | Error | Empty `errorText` |
| Synchronously throw standard Error with fixed safe text | Error | Empty `errorText` |
| Reject DOMException AbortError with fixed text | Error | Empty `errorText` |
| Resolve synthetic server rejection | Completed | Structured `rejected`, `authorization_denied` and synthetic correlation preserved |

[Raw native observations](t908-native-characterization.json) retain the exact
returned payloads. These are synthetic behavior checks, not application outcome
or cancellation-frontier proof. The last control demonstrates that `Completed`
does not itself mean application success.

The installed MCP tool source forwards `status`, `output` and `errorText` from
the underlying native/Puppeteer execution result. Upstream main has a similar
[forwarding boundary](https://github.com/ChromeDevTools/chrome-devtools-mcp/blob/main/src/tools/webmcp.ts),
but main is not proof of a released fix. No client patch, upgrade or issue
publication was performed.

## Baseline verification

Using a fresh temporary Docker filesystem and locked npm install:

- WebMCP registration lifecycle: 12 tests passed.
- Livewire cancellation: 11 tests passed.
- HTMX cancellation: 5 tests passed.
- Livewire and HTMX WebMCP integration: 2 tests each passed.
- Total: 32/32 tests across five files passed.
- `python3 scripts/validate.py`: passed, including 22/22 HTMX fixtures.

Source and existing host dependencies were mounted read-only. No runtime source,
test expectation, lockfile, browser connector or global configuration changed.

## Representation decision: options recorded at Phase 0

The preferred sanitized native rejection fails the plan's visibility gate in this
installed connection. Do not implement it as a purported solution or silently
return a fabricated core `failed` Action Result.

### Option A — Opt-in projection envelope (recommended for a local solution)

Propose an explicit registration option for clients needing readable failures.
Default registrations and direct drivers retain their existing output/rejection
contract. In the new mode, wrap **every** resolved value as a successful surface
execution carrying the original application value, and use a distinct surface
execution failure arm with fixed safe guidance and conservative outcome
uncertainty. Uniform wrapping prevents arbitrary business output from being
mistaken for the adapter's failure marker. Exact names and schema are unresolved.

This is a public, projection-only contract change: it requires an Accepted
decision, schema, positive/negative fixtures, conformance, migration guide,
generated types/exports and native artifact consumer tests. The opt-in success
arm preserves its payload, but its top-level shape changes intentionally.
Core server results remain nested and authoritative; the outer Chrome status can
be `Completed` for a surface failure because the callback resolves.

Consumers must inspect the surface discriminant and then the application result;
no claim that every generic agent automatically understands the new mode is
made. Documentation/tool descriptions must explain this interpretation.
Before specifying the new mode, characterize resolved `undefined`/no-output,
resolved `null` and envelope-shaped business output natively. Decide explicitly
how to preserve the undefined/null distinction through JSON serialization; the
rejected-undefined case above does not establish resolved-value behavior.
No retry, retarget, permission recovery, correlation fabrication or rollback is
authorized by the envelope. Cancellation representation must be decided explicitly;
pre/post-dispatch safeguards and default reason identity remain required.

This changes the T-908 acceptance boundary from default rejection normalization
to an explicitly selected mode. Owner acceptance is needed before encoding it.

### Option B — Preserve native errors and defer visibility upstream

Keep the current SurfaceRelay rejection channel and retain this reproduction for
upstream diagnosis. A later pinned upstream release must pass the same native
fixture before sanitization can be accepted. No new package output contract is
introduced, but T-908's readable native failure acceptance remains unmet.
An upstream report or connector upgrade is a separate authorized action.

### Rejected shortcut

Returning a failure-shaped business object only on rejection, without an opt-in
uniform wrapper, collides with arbitrary successful driver values and disguises
a contract change. Mapping HTTP status or public error class/code into definite
no-effect claims is also insufficient. Neither shortcut is selected.

## Cleanup and next gate

Only task-owned temporary containers/pages were used; no task images, volumes,
networks, consumer demo or worktree were created. The native fixture and evidence
are retained as reproducible test assets. Existing resources and user Composer
locks remain untouched. Phase 1 is pending owner representation choice; T-908
was subsequently resolved by owner approval of Option A. The baseline record
does not establish the implementation's real-consumer acceptance.

Independent read-only source/evidence review found no architectural, security or
evidence blocker. Its Phase 1 undefined/null/business-output recommendation is
recorded above. The reviewer did not independently replay the native calls.
