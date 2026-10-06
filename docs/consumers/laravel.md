# Laravel artifact consumer guide

SurfaceRelay is experimental and unofficial. This guide installs a local ZIP for
`surfacerelay/laravel` through a Composer `artifact` repository. It does not assume
a Packagist release. `0.0.0-alpha1` is an internal verification input, not a selected
public version. Source manifests remain development metadata.

The proof uses the existing [artifact helpers](../../scripts/laravel_release_candidate.py)
and [clean-consumer fixture](../../scripts/fixtures/laravel-clean-consumer/smoke.php).
It installs a real vendor copy, outside the repository; path repositories,
`dev-main`, source links, and symlinks do not establish this proof.

## Prerequisites and evidence boundary

Run the three `sh` blocks below, in order, in the same POSIX shell from a clean
checkout of the revision you intend to verify. They require Git, tar, mktemp,
Python 3.12 (`python`), Composer 2 (`composer`), PHP 8.4 (`php`), `ext-mbstring`,
`ext-zip`, and the extensions required by Laravel 13. Composer needs network
access to resolve Laravel and its dependencies. No registry credentials are
needed for the local SurfaceRelay ZIP.

The [CI workflow](../../.github/workflows/validate.yml) installs the artifact on
Ubuntu in four legs: PHP 8.3 / Laravel 12, PHP 8.3 / Laravel 13, PHP 8.4 / Laravel
12, and PHP 8.4 / Laravel 13. Only PHP 8.4 / Laravel 13 runs the ActionBus smoke.
The package's [dependency constraints](../../packages/laravel/composer.json)
(`php: ^8.3`, Illuminate `^12.0|^13.0`) are broader than this tested matrix.
These checks do not establish every patch version, operating system, database,
application configuration, or production security integration.

## Build from one clean revision

The temporary source is a Git archive, so ignored dependencies and local source
edits cannot enter the package. The builder is a Python function, not a CLI.
Temporary artifacts are removed when this shell exits; copy evidence elsewhere
before exiting if you need to retain it.

```sh
set -eu
repo_root=$(git rev-parse --show-toplevel)
test -z "$(git -C "$repo_root" status --porcelain --untracked-files=all)"
source_revision=$(git -C "$repo_root" rev-parse HEAD)
artifact_version=0.0.0-alpha1
work_root=$(mktemp -d)
trap 'rm -rf "$work_root"' EXIT
source_root="$work_root/source"
stage_root="$work_root/stage"
consumer_root="$work_root/consumer"
mkdir "$source_root"
git -C "$repo_root" archive "$source_revision" > "$work_root/source.tar"
tar -xf "$work_root/source.tar" -C "$source_root"

python - "$source_root" "$stage_root" "$consumer_root" \
  "$source_revision" "$artifact_version" <<'PY'
from pathlib import Path
import sys

source, stage, consumer = map(Path, sys.argv[1:4])
revision, version = sys.argv[4:6]
sys.path.insert(0, str(source))
from scripts import laravel_release_candidate as release

candidate = release.build_laravel_release_candidate(
    repo=source,
    stage_root=stage,
    artifact_version=version,
    source_revision=revision,
)
release.validate_laravel_artifact_archive(
    archive_path=candidate["archivePath"], artifact_version=version,
)
release.create_clean_consumer_workspace(
    consumer_root=consumer,
    artifact_directory=Path(candidate["archivePath"]).parent,
    artifact_version=version,
    laravel_constraint="^13.0",
    package_source_root=source / "packages" / "laravel",
)
print("Source revision:", revision)
print("Artifact:", candidate["archivePath"])
print("Content manifest:", candidate["contentManifestPath"])
print("Archive hash evidence:", candidate["artifactEvidencePath"])
PY
```

The helper creates `consumer/composer.json` with one local `artifact` repository,
`laravel/framework: ^13.0`, and the exact `surfacerelay/laravel: 0.0.0-alpha1`
requirement. The content manifest and artifact evidence record candidate identity,
source revision, file hashes, and the archive SHA-256. They are local build
evidence, not publication or production-readiness certification.

## Install and verify identity

This fresh consumer has no lockfile. `composer update` resolves dependencies and
installs the local ZIP with scripts disabled. It does not run Laravel package
discovery or database migrations.

```sh
set -eu
composer update --working-dir="$consumer_root" \
  --no-interaction --no-progress --prefer-dist --no-scripts

python - "$source_root" "$consumer_root" "$artifact_version" <<'PY'
from pathlib import Path
import sys

source, consumer = map(Path, sys.argv[1:3])
sys.path.insert(0, str(source))
from scripts import laravel_release_candidate as release

installed = release.verify_clean_consumer_install(
    consumer_root=consumer,
    artifact_version=sys.argv[3],
    package_source_root=source / "packages" / "laravel",
)
print("Verified installed candidate:", installed)
PY

php -r '
require $argv[1] . "/vendor/autoload.php";
if (!class_exists(\SurfaceRelay\Laravel\SurfaceRelayServiceProvider::class)) {
    fwrite(STDERR, "SurfaceRelayServiceProvider did not autoload\n");
    exit(1);
}
' "$consumer_root"
```

The identity check requires the exact version in both the installed package
manifest and Composer metadata, a real vendor directory, and an isolated
consumer. The PHP check loads the installed provider through vendor autoload.

## Run the bounded ActionBus proof

The [provider](../../packages/laravel/src/SurfaceRelayServiceProvider.php) declares
the package migration path through `loadMigrationsFrom`; it does not assemble or
register a production ActionBus. Loading migrations does not execute them.
The fixture explicitly registers the provider on a minimal Laravel Application
and constructs its own bus.

```sh
set -eu
cp "$source_root/scripts/fixtures/laravel-clean-consumer/smoke.php" \
  "$consumer_root/smoke.php"
output=$(php "$consumer_root/smoke.php" "$consumer_root")
test "$output" = "SurfaceRelay Laravel clean-consumer smoke: PASS"
printf '%s\n' "$output"
```

This proves the installed ActionBus can execute the fixture's side-effect-free
`consumer.health.read@1`, return `{"status":"ok"}`, apply the real output-policy
stage, and audit exactly once. It uses **fixture-only pass-through handlers** for
input validation, authorization, idempotency, and confirmation. Do not copy
those handlers into an application or treat the smoke as proof of these controls.

Application integration must supply the trusted runtime context and actual
policy stages described in [architecture](../ARCHITECTURE.md) and the
[threat model](../THREAT-MODEL.md). Discovery and invocation authorization remain
separate; caller data does not become actor, tenant, selection, or confirmation
authority. This guide does not add application routes, automatic pipeline wiring,
or a new security contract.
