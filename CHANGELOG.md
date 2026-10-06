# Changelog

This changelog records implemented consumer and release-readiness work. Entries
under Unreleased are not registry releases, public version selections, or release
dates. SurfaceRelay remains experimental and unofficial.

## Unreleased

### Added

- Credential-free publication metadata previews from reverified alpha archives,
  with offline npm packing inventory and explicit Composer manifest validation.
  These disposable proposals always report NO-GO; existing private candidate
  builders are unchanged. See [the preparation handoff](docs/releases/0.1.0-alpha.1-publication-handoff.md).

- Shared release-candidate contracts for explicit staged package versions, source
  revisions, isolated staging, content manifests and archive SHA-256 evidence.
  Source manifests remain development metadata. See
  [the contract tooling](scripts/release_candidate.py) and
  [task evidence](TASKS.md).
- A Laravel ZIP builder and isolated Composer artifact consumer. Installed package
  identity and autoload checks cover PHP 8.3/8.4 with Laravel 12/13 in CI; the
  ActionBus smoke runs only PHP 8.4 with Laravel 13. See
  [the Laravel consumer guide](docs/consumers/laravel.md).
- A curated browser runtime ESM root API, ES2022 JavaScript and TypeScript
  declarations, local npm tarball staging, and isolated consumer checks for root
  import, typecheck, Vite bundle, DriverRegistry smoke and deep-import rejection.
  See [the browser consumer guide](docs/consumers/browser-runtime.md).
- Laravel and browser artifact consumer documentation that separates local
  installation from registry publication, fixture policy stages from production
  security wiring, and Node tooling evidence from real-browser interoperability.
- A [security policy](SECURITY.md) recording experimental support status,
  owner-authorized private vulnerability reporting and private triage responsibility.
- Integrated release-readiness verification: `build_release_readiness()` builds
  both candidates from one clean revision and prerelease version, re-verifies
  identity and archive/manifest hashes, and writes `readiness-evidence.json` with
  an always-NO-GO publication handoff listing the remaining blockers. See
  [release readiness](docs/RELEASE-READINESS.md).
- `resolveDocumentModelContext()` in the browser runtime root API: feature-detects
  the native `document.modelContext` and returns `null` when WebMCP is absent or
  malformed. It does not polyfill or fall back to `navigator.modelContext`.
- A native WebMCP proof in the HTMX fixture: the projected
  `prep_list.add_item.v1` tool registers on Chromium's own `document.modelContext`
  (`--enable-blink-features=WebMCP`), is invoked through the browser's
  `executeTool()`, drives the same real HTMX `POST /items`, fails closed for stale
  bindings, undeclared input, server rejections and unsent requests, and
  unregisters when the lease is disposed. See
  [the fixture](examples/htmx-prep-list/README.md#native-webmcp-proof).
- A live Filament proof: a real Filament 5 panel served by `testbench serve`
  registers the order demo's Livewire-bound Actions on the native
  `document.modelContext`. An agent hold mutates exactly the trusted current
  record; an agent refund over the human-visible table selection stops at
  `confirmation_required`; forged order ids are rejected. Filament's client-side
  selection is pushed to the server before agent calls, as Filament's own
  `mountAction` does (proposed D-075). See
  [the example](examples/filament-orders-live/README.md).
- `InteractsWithSurfaceRelayConfirmation` keeps a receipt approved in the
  Filament modal in server-side session state for the approving component, and
  exposes it only through protected `pullApprovedSurfaceRelayConfirmationReceipt()`
  by removing it from the session on retry (D-076). Exposed page methods
  can complete an approved retry
  without ever accepting a receipt argument. The live Filament proof covers
  approval → exactly-once retry, selection/input drift, and forged confirmation
  fields. Session pull is not an atomic concurrency guard; the confirmation
  store's scope-checked atomic consumption governs single-use authority.

### Changed

- **Breaking for custom `ConfirmationStore` implementations:** approval now
  returns a fresh random receipt instead of the challenge id.
  `approvePending()` gains a `$receiptHash` parameter and must atomically move
  the approvable pending record to it. `ConfirmationService` accepts an optional
  receipt token generator. A challenge id seen by a human or page script never
  becomes receipt authority (D-077).

### Fixed

- An event-based HTMX confirmation veto is reported as `htmx_request_failed`,
  because the application's callback can still send the request later. It is no
  longer misreported as `htmx_request_not_sent`; confirmation is never bypassed.

- Cache confirmation approval locks both challenge and receipt addresses in a
  consistent order. Concurrent approvals targeting the same receipt cannot
  overwrite another approval; a failed lock acquisition leaves records unchanged.

- Integrated release readiness now builds from an isolated archive of the requested
  Git revision and freshly rebuilds browser distribution there. Ignored source
  files and stale checkout distribution cannot silently enter the default
  candidates; unsafe archive entries and build failures stop aggregate evidence.
  See [release readiness](docs/RELEASE-READINESS.md).

- HTMX-backed Actions can return business output: when the business route sets
  `HX-Trigger` to `{"surfacerelay:result":{"value":{...}}}`,
  `GlobalHtmxBrowserRuntime.ajax()` resolves with the business `value`, read from the
  issued request's own response. Absent or malformed declarations resolve
  `undefined`; output is never derived from HTML. `HtmxBrowserRuntime.ajax()`
  now returns `Promise<unknown>` (D-078).
  The exact envelope isolates business `target`, `value` and `elt` from HTMX
  event routing. This changes the unpublished wire format: update the server and
  runtime together; declarations outside the exact envelope resolve `undefined`. See
  [migration guidance](docs/consumers/browser-runtime.md#htmx-result-envelope).
- The HTMX driver no longer reports failed requests as success. `htmx.ajax()`
  resolves after HTTP error responses and on paths that never send the request;
  `GlobalHtmxBrowserRuntime.ajax()` now tracks its own request and rejects with
  `htmx_request_failed` (unsuccessful status, transport or response-handling
  failure) or `htmx_request_not_sent`. Custom `HtmxBrowserRuntime` implementations
  must follow the same contract, and sources must support `addEventListener`.
  Accepted as D-074 with owner approval for the alpha.

- Browser artifact tooling now resolves npm for the tested POSIX and Windows
  launch paths. Windows command shims run through Node's npm CLI without a shell;
  missing launch prerequisites fail before execution. See
  [the tooling](scripts/browser_release_candidate.py) and
  [launch contract tests](scripts/tests/test_browser_release_candidate.py).

### Release boundaries

The consumer guides use `0.0.0-alpha1` only as an internal verification input.
There is no public version or registry installation promise in these entries.
Artifact and fixture checks do not certify production readiness or general
WebMCP interoperability; the native WebMCP proof covers one flag-enabled Chromium
build with the page itself acting as the tool caller, not a real AI agent. Private reporting availability remains a publication
blocker; integrated readiness evidence so far is pre-merge and must be re-run on
the merged revision.
