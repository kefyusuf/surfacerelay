# Changelog

This changelog records implemented consumer and release-readiness work. Entries
under Unreleased are not registry releases, public version selections, or release
dates. SurfaceRelay remains experimental and unofficial.

## Unreleased

### Added

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
- A [security policy](SECURITY.md) recording experimental support status and the
  private vulnerability-reporting channel gap.
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

### Fixed

- The HTMX driver no longer reports failed requests as success. `htmx.ajax()`
  resolves after HTTP error responses and on paths that never send the request;
  `GlobalHtmxBrowserRuntime.ajax()` now tracks its own request and rejects with
  `htmx_request_failed` (unsuccessful status, transport or response-handling
  failure) or `htmx_request_not_sent`. Custom `HtmxBrowserRuntime` implementations
  must follow the same contract, and sources must support `addEventListener`.
  Proposed as D-074.

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
blocker; integrated two-artifact release verification remains T-805 work.
