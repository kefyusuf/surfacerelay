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
every operating system. Separate Windows npm launch checks cover tested tooling
paths; this POSIX recipe is not a Windows shell recipe.

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

## Related checks

The repository tooling suite is `python -m unittest scripts.tests.test_browser_release_candidate`.
The [approved documentation plan](../superpowers/plans/2026-10-02-release-facing-documentation-compatibility.md)
records the broader release gates. Publication and integrated two-artifact
orchestration remain separate work.
