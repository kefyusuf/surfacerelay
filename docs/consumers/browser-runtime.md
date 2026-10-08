# Browser runtime artifact consumer guide

SurfaceRelay is experimental and unofficial. This guide consumes a locally built
release-candidate tarball; it does not imply npm publication or production readiness.
`0.0.0-alpha1` is a non-public verification input. Source metadata remains private
development metadata; the builder writes candidate metadata only in staging.

## Prerequisites and evidence boundary

Use a clean committed checkout, Git, a POSIX shell with tar, Node 22/npm,
Python 3.12, and the dependencies from `requirements-dev.txt`. Network access is
needed for development and consumer tooling dependencies. The SurfaceRelay package
itself comes from the exact local tarball below. Run both shell blocks in the same
shell, from the repository root. Temporary outputs remain outside the checkout.

The existing CI consumer uses TypeScript 5.9.3 and Vite 7.3.6. Its evidence covers
root ESM import, shipped declarations, typecheck, bundle, and a Node DriverRegistry
smoke. It does not establish real-browser/WebMCP interoperability or support for
every operating system. Native WebMCP evidence is separate and narrower: the
[HTMX fixture](../../examples/htmx-prep-list/README.md#native-webmcp-proof) registers
through Chromium's own `document.modelContext` behind a feature flag. Separate Windows npm launch checks cover tested tooling
paths; this POSIX recipe is not a Windows shell recipe.

## Opt-in native execution results (since 0.1.0-alpha.2)

The default registration contract is unchanged: resolved driver values and
rejected values retain their existing behavior. To make execution failures
readable in native clients that discard rejection text, explicitly select:

```ts
const lifecycle = new WebMcpRegistrationLifecycle(modelContext, drivers, {
  resultMode: 'envelope',
});
```

Every invocation in this mode returns the projection-only
`WebMcpExecutionResult` union (`kind: 'surfacerelay.webmcp.execution.v1'`):

| Surface status | Meaning |
| --- | --- |
| `returned` | The driver returned. `output.kind: 'value'` carries the original value; `output.kind: 'undefined'` denotes absent output, distinct from null. Inspect the nested application result: `returned` is not business success. |
| `execution_failed` | Fixed safe error guidance, `outcome: 'unknown'`. No raw error data, server correlation or inferred HTTP/domain code is supplied. Verify application state before considering a retry. |
| `cancelled` | The callback observed an already-aborted signal before resolving/invoking its driver. `outcome: 'not_dispatched'` refers only to this callback's driver dispatch, not a claim that no earlier operation exists. |

Chrome may report outer `Completed` for **all** these arms because the callback
resolved. Read the surface status before the nested server Action Result.
Successful business objects resembling the surface envelope stay inside
`output.value`; do not recursively reinterpret them. Core authorization,
confirmation, idempotency and correlation remain server-owned. Drivers still
apply their output policy; the envelope does not redact successful values.

Only errors from invocation are normalized. Registration validation/rollback
and direct driver consumers retain their contracts. A cancellation after driver
initiation preserves its natural completion; if it rejects, outcome stays unknown.
The adapter never retries, remounts, changes keys, retargets or claims rollback.
Unsupported result modes fail closed. Mode choice is captured at construction.

Migration is explicit: leave existing registrations unchanged, or opt in and
update consumers to inspect the outer discriminant and nested application result.
JSON-compatible application values and explicit undefined are covered by the
[adapter schema/fixtures](../../packages/browser-runtime/conformance/webmcp-execution-result.schema.json).
This feature is published in alpha.2 and is absent from alpha.1. Install the
explicit alpha.3 version and test the opt-in mode before adoption. It does not fix Chrome's native rejection text channel
or qualify the Codex in-app browser.

## Opt-in Filament selection driver (since 0.1.0-alpha.3)

The published alpha.3 package includes `FilamentBrowserDriver`,
`FilamentSelectionCoordinator` and `GlobalFilamentSelectionRuntime`. These exports
are absent from published alpha.1 and alpha.2; do not import them from those versions.
Install explicit alpha.3 versions; [registry acceptance](../releases/0.1.0-alpha.3-publication.md)
verifies root exports and installed bytes.

```ts
import {
  FilamentBrowserDriver, FilamentSelectionCoordinator, GlobalFilamentSelectionRuntime,
  GlobalLivewireBrowserRuntime,
} from '@surfacerelay/browser-runtime';

// Create once and share with every helper in this Livewire environment.
const coordinator = new FilamentSelectionCoordinator();
drivers.register('livewire', new FilamentBrowserDriver(new GlobalLivewireBrowserRuntime(), {
  tools: explicitlyExposedTools,
  selectionRuntime: new GlobalFilamentSelectionRuntime(),
  coordinator,
}));
```

Register the same exact binding objects with the WebMCP lifecycle. The driver
captures owned descriptor data and selection policy at construction; clones,
unknown objects, changed descriptors, unsupported descriptor values and conflicting
selection policies for one exact binding fail closed. It does not freeze caller
objects. Rebuild the driver/lease when the application issues new bindings; do not
retarget old bindings. Selection is derived from the exposed definition's
`current_selection` requirement, never from invocation input.

Normal Livewire preflight runs before selection writes. The runtime validates one
exact component-owned Filament table and captures its boolean tracking flag and
two Sets of string record keys before three deferred writes. A missing, ambiguous,
foreign-owned or malformed table stops invocation. Current-record actions need no
table but share component exclusion. Different components remain independent.

Sharing the coordinator is required to exclude overlap across helper instances.
Rejected contenders do not release the active call's guard. Occupancy lasts until
the underlying framework call settles, including when the public promise rejects
early because exact-action capture failed. That post-initiation failure has unknown
dispatch; do not retry automatically. Ordinary UI/Livewire calls outside these
helpers are not covered. Three deferred writes are not transactional: a throwing
write stops dispatch but can leave partial local state; the next selection-required
call must fully revalidate/overwrite it. No rollback guarantee.

Browser selection supplies no authority. Server record resolution, tenant/record
authorization, confirmation and idempotency remain authoritative. Existing
`LivewireBrowserDriver` behavior and registration result modes remain compatible.
Use the [T-910 local consumer](../../scripts/acceptance/t910-filament/README.md) to
verify a built candidate; historical registry fixtures keep compatible older glue.

## Build an exact candidate

The builder is an existing Python function, not a command-line interface.

```sh
set -eu
test -z "$(git status --porcelain)"
SOURCE_REVISION="$(git rev-parse HEAD)"
ARTIFACT_VERSION=0.0.0-alpha1
WORK_ROOT="$(mktemp -d)"
SOURCE_ROOT="$WORK_ROOT/source"
STAGE_ROOT="$WORK_ROOT/stage"
CONSUMER_ROOT="$WORK_ROOT/consumer"
mkdir "$SOURCE_ROOT"
git archive "$SOURCE_REVISION" > "$WORK_ROOT/source.tar"
tar -xf "$WORK_ROOT/source.tar" -C "$SOURCE_ROOT"
npm --prefix "$SOURCE_ROOT/packages/browser-runtime" ci --ignore-scripts
npm --prefix "$SOURCE_ROOT/packages/browser-runtime" run build
python - "$SOURCE_ROOT" "$STAGE_ROOT" "$SOURCE_REVISION" "$ARTIFACT_VERSION" <<'PY'
import json
import sys
from pathlib import Path

source, stage = map(Path, sys.argv[1:3])
sys.path.insert(0, str(source))
from scripts import browser_release_candidate as release

candidate = release.build_browser_release_candidate(
    repo=source, stage_root=stage,
    source_revision=sys.argv[3], artifact_version=sys.argv[4],
)
release.validate_browser_artifact_archive(
    archive_path=candidate["archivePath"], artifact_version=sys.argv[4],
)
(stage / "consumer-input.json").write_text(json.dumps(candidate), encoding="utf-8")
print(candidate["archivePath"])
print(candidate["artifactEvidencePath"])
PY
```

Keep the generated `content-manifest.json` and `artifact-evidence.json` with the
tarball. They record package identity, source revision, file contents and archive
SHA-256. They do not promise byte-identical tarballs across platforms.
`consumer-input.json` above is only a local handoff of the builder's return value;
it is not a new public evidence format or part of the installed package.

## Install and prove consumption

Reuse the committed consumer fixture and proof function. The helper installs the
exact tarball with npm, verifies installed identity, rejects source coupling and
symlink installations, then checks the root import, declarations, bundle, registry
smoke and a representative forbidden deep import.

```sh
python - "$SOURCE_ROOT" "$STAGE_ROOT" "$CONSUMER_ROOT" "$ARTIFACT_VERSION" <<'PY'
import json
import sys
from pathlib import Path

source, stage, consumer = map(Path, sys.argv[1:4])
sys.path.insert(0, str(source))
from scripts import browser_release_candidate as release

candidate = json.loads((stage / "consumer-input.json").read_text(encoding="utf-8"))
release.create_clean_consumer_workspace(
    consumer_root=consumer,
    fixture_root=source / "scripts/fixtures/browser-clean-consumer",
    package_source_root=source / "packages/browser-runtime",
)
proof = release.execute_clean_consumer_proof(
    consumer_root=consumer, archive_path=candidate["archivePath"],
    artifact_version=sys.argv[4],
    package_source_root=source / "packages/browser-runtime",
)
assert all(proof[key] for key in ("rootImport", "typecheck", "bundle", "deepImportRejected"))
print(proof["smokeOutput"])
print("Browser artifact consumer checks: PASS")
PY
```

The proof uses a temporary install without a saved SurfaceRelay dependency or lock
file and disables package lifecycle scripts. This is a verification fixture;
application dependency locking and deployment remain application responsibilities.

## Use only the package root

The artifact exposes ES2022 JavaScript and TypeScript declarations through one
curated ESM root. CommonJS and imports into `src/` or `dist/` are outside the public
entry point. The existing fixture imports:

```ts
import { DriverRegistry, type BindingDriver } from '@surfacerelay/browser-runtime';

const registry = new DriverRegistry();
const driver: BindingDriver = {
  async execute() {
    return undefined;
  },
};
registry.register('fixture', driver);
const resolved: BindingDriver = registry.requireDriver('fixture');
void resolved;
```

This driver is a fixture for registration and type resolution. An application must
provide reviewed binding drivers and preserve server-side authorization, trusted
context, confirmation receipts and idempotency. Browser discovery never grants
invocation authority. An unsupported driver fails closed in the existing smoke.

## HTMX result envelope

The experimental D-078 result declaration is:

```json
{"surfacerelay:result":{"value":{"itemId":"1","target":"archive"}}}
```

Set this JSON object in the issued response's `HX-Trigger` header. The runtime
returns `{"itemId":"1","target":"archive"}`. The envelope has exactly one member,
`value`, which must be a JSON object. Keep all business fields inside it; outer
HTMX event-routing fields are not allowed. Other root trigger names may coexist.
Server-side output validation and redaction remain application responsibilities.

Migration impact: this replaces the unpublished direct business-object declaration.
Update route producers and browser runtimes together. There is no legacy fallback:
absent, malformed or old declarations return `undefined` after a successful request.
An old object containing only an object-valued `value` is structurally the new
envelope, so inventory producers before upgrading. The returned business object
and neutral ActionDefinition/RuntimeBinding schemas do not change. Response errors
continue to reject; this migration does not suppress HTMX response-handling failures.

The [adapter schema](../../packages/browser-runtime/conformance/htmx-result-envelope.schema.json)
and [fixtures](../../packages/browser-runtime/conformance/htmx-result-envelope.fixtures.json)
are checked by `python scripts/validate.py`. The
[real HTMX fixture](../../examples/htmx-prep-list/README.md) verifies native dispatch
and persistence with business control-like fields.

## Related checks

The repository tooling suite is `python -m unittest scripts.tests.test_browser_release_candidate`.
The [approved documentation plan](../superpowers/plans/2026-10-02-release-facing-documentation-compatibility.md)
records the broader release gates. Implemented same-revision/two-artifact
orchestration is documented in [release readiness](../RELEASE-READINESS.md).
Publication remains a separate authorization gate.
