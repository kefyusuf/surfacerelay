from __future__ import annotations

import json
import os
from pathlib import Path
import stat
import subprocess
import tarfile

from scripts.release_candidate import (
    ReleaseCandidateContractError,
    build_artifact_evidence,
    build_content_manifest,
    ensure_empty_target,
    resolve_staging_path,
    serialize_evidence_json,
    validate_artifact_version,
    validate_source_revision,
)


PACKAGE_NAME = "@surfacerelay/browser-runtime"
_PACKAGE_SOURCE = Path("packages") / "browser-runtime"
_SOURCE_VERSION = "0.0.0-dev"
_ROOT_EXPORTS = {
    ".": {
        "types": "./dist/index.d.ts",
        "import": "./dist/index.js",
    }
}
_PACKAGE_FILES = ["dist", "README.md", "LICENSE"]


class BrowserReleaseCandidateError(ReleaseCandidateContractError):
    """Raised when the browser release-candidate package cannot be built safely."""


def _require_regular_file(path: Path, *, label: str) -> Path:
    if path.is_symlink():
        raise BrowserReleaseCandidateError(f"{label} must not be a symlink")

    try:
        mode = path.lstat().st_mode
    except OSError as exc:
        raise BrowserReleaseCandidateError(f"{label} must exist") from exc

    if not stat.S_ISREG(mode):
        raise BrowserReleaseCandidateError(f"{label} must be a regular file")

    return path


def _copy_regular_file(source: Path, target: Path) -> None:
    _require_regular_file(source, label=f"source file {source.name}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(source.read_bytes())


def _load_source_manifest(source_manifest: Path) -> dict[str, object]:
    _require_regular_file(
        source_manifest,
        label="browser-runtime source package.json",
    )

    try:
        payload = json.loads(source_manifest.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise BrowserReleaseCandidateError(
            "browser-runtime source package.json must be valid UTF-8 JSON"
        ) from exc

    if not isinstance(payload, dict):
        raise BrowserReleaseCandidateError(
            "browser-runtime source package.json must contain a JSON object"
        )
    if payload.get("name") != PACKAGE_NAME:
        raise BrowserReleaseCandidateError(
            f"browser-runtime source package name must be {PACKAGE_NAME}"
        )
    if payload.get("version") != _SOURCE_VERSION:
        raise BrowserReleaseCandidateError(
            f"browser-runtime source version must remain {_SOURCE_VERSION}"
        )
    if payload.get("private") is not True:
        raise BrowserReleaseCandidateError(
            "browser-runtime source package must retain private: true"
        )
    if payload.get("type") != "module":
        raise BrowserReleaseCandidateError(
            "browser-runtime source package must remain ESM"
        )
    if payload.get("types") != "./dist/index.d.ts":
        raise BrowserReleaseCandidateError(
            "browser-runtime source declarations entry must be ./dist/index.d.ts"
        )
    if payload.get("exports") != _ROOT_EXPORTS:
        raise BrowserReleaseCandidateError(
            "browser-runtime source package must expose only the reviewed root export"
        )
    if payload.get("files") != _PACKAGE_FILES:
        raise BrowserReleaseCandidateError(
            "browser-runtime source package files allowlist is invalid"
        )
    if "main" in payload:
        raise BrowserReleaseCandidateError(
            "browser-runtime source package must not define a CommonJS/main entry"
        )

    scripts = payload.get("scripts")
    if (
        not isinstance(scripts, dict)
        or scripts.get("build") != "tsc -p tsconfig.build.json"
    ):
        raise BrowserReleaseCandidateError(
            "browser-runtime source package must define the reviewed build script"
        )

    return payload


def _staged_manifest_bytes(source_manifest: Path, version: str) -> bytes:
    payload = _load_source_manifest(source_manifest)
    payload["version"] = version

    return (
        json.dumps(
            payload,
            ensure_ascii=False,
            indent=2,
        )
        + "\n"
    ).encode("utf-8")


def _candidate_readme(version: str, revision: str) -> bytes:
    return (
        "# SurfaceRelay browser-runtime release-candidate artifact\n\n"
        f"Package: {PACKAGE_NAME}\n"
        f"Artifact version: {version}\n"
        f"Source revision: {revision}\n\n"
        "This is not a public release. It exists only for bounded release-readiness "
        "and clean-consumer verification.\n"
    ).encode("utf-8")


def _is_distribution_file(path: Path) -> bool:
    return path.suffix == ".js" or path.name.endswith(".d.ts")


def _copy_distribution_tree(source: Path, target: Path) -> None:
    if source.is_symlink():
        raise BrowserReleaseCandidateError(
            "browser-runtime dist root must not be a symlink"
        )

    try:
        mode = source.lstat().st_mode
    except OSError as exc:
        raise BrowserReleaseCandidateError(
            "browser-runtime dist root must exist"
        ) from exc

    if not stat.S_ISDIR(mode):
        raise BrowserReleaseCandidateError(
            "browser-runtime dist root must be a directory"
        )

    copied = 0
    pending: list[tuple[Path, Path]] = [(source, target)]

    while pending:
        source_dir, target_dir = pending.pop()
        target_dir.mkdir(parents=True, exist_ok=True)

        for entry in sorted(source_dir.iterdir(), key=lambda item: item.name):
            if entry.is_symlink():
                raise BrowserReleaseCandidateError(
                    f"symlink is not allowed in browser-runtime dist: {entry.name}"
                )

            entry_mode = entry.lstat().st_mode
            destination = target_dir / entry.name

            if stat.S_ISDIR(entry_mode):
                pending.append((entry, destination))
                continue

            if not stat.S_ISREG(entry_mode):
                raise BrowserReleaseCandidateError(
                    f"only regular files and directories are allowed in browser-runtime dist: {entry.name}"
                )

            if entry.name.endswith(".map"):
                continue

            if not _is_distribution_file(entry):
                raise BrowserReleaseCandidateError(
                    f"unexpected browser-runtime distribution file: {entry.name}"
                )

            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_bytes(entry.read_bytes())
            copied += 1

    if copied == 0:
        raise BrowserReleaseCandidateError(
            "browser-runtime dist must contain JavaScript or declaration files"
        )
    if not (target / "index.js").is_file():
        raise BrowserReleaseCandidateError(
            "browser-runtime dist must contain index.js"
        )
    if not (target / "index.d.ts").is_file():
        raise BrowserReleaseCandidateError(
            "browser-runtime dist must contain index.d.ts"
        )


def _package_regular_files(package_root: Path) -> list[str]:
    files: list[str] = []

    for path in package_root.rglob("*"):
        if path.is_symlink():
            raise BrowserReleaseCandidateError(
                f"symlink is not allowed in staged browser package: {path.name}"
            )

        mode = path.lstat().st_mode
        if stat.S_ISDIR(mode):
            continue
        if not stat.S_ISREG(mode):
            raise BrowserReleaseCandidateError(
                f"only regular files are allowed in staged browser package: {path.name}"
            )

        relative = path.relative_to(package_root).as_posix()
        if relative.startswith("/") or ".." in Path(relative).parts:
            raise BrowserReleaseCandidateError(
                "staged browser package contains an unsafe path"
            )
        files.append(relative)

    return sorted(files)


def _run_npm_pack(package_root: Path, stage_root: Path) -> Path:
    archive_root = resolve_staging_path(stage_root, "artifacts")
    archive_root.mkdir(parents=True, exist_ok=False)

    try:
        completed = subprocess.run(
            [
                "npm",
                "pack",
                "--json",
                "--ignore-scripts",
                "--pack-destination",
                str(archive_root),
            ],
            cwd=package_root,
            check=True,
            capture_output=True,
            text=True,
        )
    except (OSError, subprocess.CalledProcessError) as exc:
        raise BrowserReleaseCandidateError(
            "npm pack failed for browser-runtime release candidate"
        ) from exc

    try:
        payload = json.loads(completed.stdout)
    except json.JSONDecodeError as exc:
        raise BrowserReleaseCandidateError(
            "npm pack --json must return valid JSON"
        ) from exc

    if (
        not isinstance(payload, list)
        or len(payload) != 1
        or not isinstance(payload[0], dict)
        or not isinstance(payload[0].get("filename"), str)
    ):
        raise BrowserReleaseCandidateError(
            "npm pack --json must describe exactly one tarball"
        )

    filename = payload[0]["filename"]
    if Path(filename).name != filename or not filename.endswith(".tgz"):
        raise BrowserReleaseCandidateError(
            "npm pack returned an unsafe tarball filename"
        )

    archive_path = archive_root / filename
    _require_regular_file(archive_path, label="browser-runtime npm tarball")
    return archive_path


def _validate_tarball(
    *,
    archive_path: Path,
    package_root: Path,
    artifact_version: str,
) -> None:
    expected_files = [
        f"package/{relative}"
        for relative in _package_regular_files(package_root)
    ]

    try:
        with tarfile.open(archive_path, mode="r:gz") as archive:
            members = archive.getmembers()
            names = [member.name for member in members]
            if len(names) != len(set(names)):
                raise BrowserReleaseCandidateError(
                    "browser-runtime npm tarball must not contain duplicate paths"
                )

            regular_files: list[str] = []
            for member in members:
                path = Path(member.name)
                if (
                    not member.name.startswith("package/")
                    or member.name.startswith("/")
                    or ".." in path.parts
                    or member.issym()
                    or member.islnk()
                    or member.isdev()
                ):
                    raise BrowserReleaseCandidateError(
                        "browser-runtime npm tarball contains an unsafe entry"
                    )
                if member.isfile():
                    regular_files.append(member.name)

            if sorted(regular_files) != expected_files:
                raise BrowserReleaseCandidateError(
                    "browser-runtime npm tarball file list does not match staged package"
                )

            try:
                manifest_member = archive.extractfile("package/package.json")
                if manifest_member is None:
                    raise KeyError("package/package.json")
                manifest_payload = json.loads(
                    manifest_member.read().decode("utf-8")
                )
            except (KeyError, UnicodeError, json.JSONDecodeError) as exc:
                raise BrowserReleaseCandidateError(
                    "browser-runtime npm tarball package.json must be valid UTF-8 JSON"
                ) from exc
    except tarfile.TarError as exc:
        raise BrowserReleaseCandidateError(
            "browser-runtime npm artifact must be a valid gzip tarball"
        ) from exc

    if not isinstance(manifest_payload, dict):
        raise BrowserReleaseCandidateError(
            "browser-runtime npm tarball package.json must contain a JSON object"
        )
    if manifest_payload.get("name") != PACKAGE_NAME:
        raise BrowserReleaseCandidateError(
            f"browser-runtime npm tarball package name must be {PACKAGE_NAME}"
        )
    if manifest_payload.get("version") != artifact_version:
        raise BrowserReleaseCandidateError(
            "browser-runtime npm tarball version does not match candidate version"
        )
    if manifest_payload.get("private") is not True:
        raise BrowserReleaseCandidateError(
            "browser-runtime npm tarball must retain private: true"
        )
    if manifest_payload.get("exports") != _ROOT_EXPORTS:
        raise BrowserReleaseCandidateError(
            "browser-runtime npm tarball must expose only the reviewed root export"
        )
    if manifest_payload.get("types") != "./dist/index.d.ts":
        raise BrowserReleaseCandidateError(
            "browser-runtime npm tarball declarations entry is invalid"
        )
    if "main" in manifest_payload:
        raise BrowserReleaseCandidateError(
            "browser-runtime npm tarball must not define a CommonJS/main entry"
        )


_CONSUMER_DEV_DEPENDENCIES = {
    "typescript": "5.9.3",
    "vite": "7.3.6",
}


def build_clean_consumer_npm_manifest() -> dict[str, object]:
    return {
        "name": "surfacerelay-browser-clean-consumer",
        "private": True,
        "type": "module",
        "devDependencies": dict(_CONSUMER_DEV_DEPENDENCIES),
    }


def validate_clean_consumer_manifest(manifest: object) -> dict[str, object]:
    if not isinstance(manifest, dict):
        raise BrowserReleaseCandidateError(
            "browser clean-consumer package manifest must be a JSON object"
        )

    dependencies = manifest.get("dependencies", {})
    dev_dependencies = manifest.get("devDependencies", {})

    for label, values in (
        ("dependencies", dependencies),
        ("devDependencies", dev_dependencies),
    ):
        if not isinstance(values, dict):
            raise BrowserReleaseCandidateError(
                f"browser clean-consumer {label} must be a JSON object"
            )

        surface_spec = values.get(PACKAGE_NAME)
        if surface_spec is None:
            continue
        if not isinstance(surface_spec, str):
            raise BrowserReleaseCandidateError(
                "browser clean-consumer SurfaceRelay dependency must be a string"
            )

        lowered = surface_spec.lower()
        if (
            lowered.startswith("file:")
            or lowered.startswith("workspace:")
            or lowered.startswith("link:")
            or "packages/browser-runtime" in lowered
            or "../packages" in lowered
        ):
            raise BrowserReleaseCandidateError(
                "browser clean-consumer manifest must not couple to SurfaceRelay source"
            )

        raise BrowserReleaseCandidateError(
            "browser clean-consumer manifest must not persist SurfaceRelay as a dependency"
        )

    return manifest


def validate_clean_consumer_source(source: str) -> str:
    if not isinstance(source, str):
        raise BrowserReleaseCandidateError(
            "browser clean-consumer source must be text"
        )

    forbidden_tokens = (
        f"{PACKAGE_NAME}/",
        "packages/browser-runtime",
        "../packages",
        "workspace:",
        "link:",
    )
    lowered = source.lower()
    if any(token.lower() in lowered for token in forbidden_tokens):
        raise BrowserReleaseCandidateError(
            "browser clean-consumer source must import SurfaceRelay only from the package root"
        )

    return source


def validate_clean_consumer_isolation(
    *,
    consumer_root: Path | str,
    package_source_root: Path | str,
) -> Path:
    consumer = Path(consumer_root)
    package_source = Path(package_source_root)

    if consumer.is_symlink() or package_source.is_symlink():
        raise BrowserReleaseCandidateError(
            "consumer and package source roots must be real directories"
        )

    try:
        consumer_resolved = consumer.resolve(strict=True)
        package_resolved = package_source.resolve(strict=True)
    except OSError as exc:
        raise BrowserReleaseCandidateError(
            "consumer and package source roots must exist"
        ) from exc

    if not consumer_resolved.is_dir() or not package_resolved.is_dir():
        raise BrowserReleaseCandidateError(
            "consumer and package source roots must be directories"
        )

    if (
        consumer_resolved == package_resolved
        or package_resolved in consumer_resolved.parents
        or consumer_resolved in package_resolved.parents
    ):
        raise BrowserReleaseCandidateError(
            "browser clean-consumer directory must be isolated from package source"
        )

    for path in consumer.rglob("*"):
        if not path.is_symlink():
            continue

        try:
            target = path.resolve(strict=True)
        except OSError as exc:
            raise BrowserReleaseCandidateError(
                "browser clean-consumer must not contain broken symlinks"
            ) from exc

        if target == package_resolved or package_resolved in target.parents:
            raise BrowserReleaseCandidateError(
                "browser clean-consumer symlink must not target package source"
            )

    return consumer_resolved


def _read_tarball_manifest(
    *,
    archive_path: Path,
) -> tuple[dict[str, object], set[str]]:
    try:
        _require_regular_file(
            archive_path,
            label="browser-runtime npm artifact",
        )
    except BrowserReleaseCandidateError:
        raise

    try:
        with tarfile.open(archive_path, mode="r:gz") as archive:
            members = archive.getmembers()
            names = [member.name for member in members]

            if len(names) != len(set(names)):
                raise BrowserReleaseCandidateError(
                    "browser-runtime npm artifact must not contain duplicate paths"
                )

            regular_files: set[str] = set()
            for member in members:
                path = Path(member.name)
                if (
                    not member.name.startswith("package/")
                    or member.name.startswith("/")
                    or ".." in path.parts
                    or member.issym()
                    or member.islnk()
                    or member.isdev()
                ):
                    raise BrowserReleaseCandidateError(
                        "browser-runtime npm artifact contains an unsafe entry"
                    )
                if member.isfile():
                    regular_files.add(member.name)

            try:
                manifest_member = archive.extractfile("package/package.json")
                if manifest_member is None:
                    raise KeyError("package/package.json")
                manifest = json.loads(
                    manifest_member.read().decode("utf-8")
                )
            except (KeyError, UnicodeError, json.JSONDecodeError) as exc:
                raise BrowserReleaseCandidateError(
                    "browser-runtime npm artifact package.json must be valid UTF-8 JSON"
                ) from exc
    except (tarfile.TarError, OSError) as exc:
        raise BrowserReleaseCandidateError(
            "browser-runtime npm artifact must be a valid gzip tarball"
        ) from exc

    if not isinstance(manifest, dict):
        raise BrowserReleaseCandidateError(
            "browser-runtime npm artifact package.json must contain a JSON object"
        )

    return manifest, regular_files


def _validate_installed_manifest(
    *,
    manifest: object,
    artifact_version: str,
    existing_files: set[str] | None = None,
) -> dict[str, object]:
    version = validate_artifact_version(artifact_version)

    if not isinstance(manifest, dict):
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package manifest must be a JSON object"
        )
    if manifest.get("name") != PACKAGE_NAME:
        raise BrowserReleaseCandidateError(
            f"installed browser-runtime package name must be {PACKAGE_NAME}"
        )
    if manifest.get("version") != version:
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package version does not match candidate"
        )
    if manifest.get("private") is not True:
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package must retain private: true"
        )
    if manifest.get("type") != "module":
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package must remain ESM"
        )
    if manifest.get("types") != "./dist/index.d.ts":
        raise BrowserReleaseCandidateError(
            "installed browser-runtime root declaration entry is invalid"
        )
    if manifest.get("exports") != _ROOT_EXPORTS:
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package must expose only the reviewed root export"
        )
    if "main" in manifest:
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package must not define a CommonJS/main entry"
        )

    if existing_files is not None:
        if "dist/index.js" not in existing_files:
            raise BrowserReleaseCandidateError(
                "installed browser-runtime package is missing dist/index.js"
            )
        if "dist/index.d.ts" not in existing_files:
            raise BrowserReleaseCandidateError(
                "installed browser-runtime package is missing dist/index.d.ts"
            )

    return manifest


def validate_browser_artifact_archive(
    *,
    archive_path: Path | str,
    artifact_version: str,
) -> dict[str, object]:
    archive = Path(archive_path)
    manifest, tar_files = _read_tarball_manifest(archive_path=archive)

    package_files = {
        name.removeprefix("package/")
        for name in tar_files
        if name.startswith("package/")
    }
    return _validate_installed_manifest(
        manifest=manifest,
        artifact_version=artifact_version,
        existing_files=package_files,
    )


def verify_clean_consumer_install(
    *,
    consumer_root: Path | str,
    artifact_version: str,
    package_source_root: Path | str,
) -> Path:
    consumer = validate_clean_consumer_isolation(
        consumer_root=consumer_root,
        package_source_root=package_source_root,
    )
    package_source = Path(package_source_root).resolve(strict=True)

    installed = (
        consumer
        / "node_modules"
        / "@surfacerelay"
        / "browser-runtime"
    )
    if installed.is_symlink() or not installed.is_dir():
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package must be a real node_modules directory"
        )

    installed_resolved = installed.resolve(strict=True)
    if installed_resolved == package_source or package_source in installed_resolved.parents:
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package must not resolve into package source"
        )

    manifest_path = installed / "package.json"
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise BrowserReleaseCandidateError(
            "installed browser-runtime package.json must be valid UTF-8 JSON"
        ) from exc

    existing_files = {
        path.relative_to(installed).as_posix()
        for path in installed.rglob("*")
        if path.is_file() and not path.is_symlink()
    }
    for path in installed.rglob("*"):
        if path.is_symlink():
            raise BrowserReleaseCandidateError(
                "installed browser-runtime package must not contain symlinks"
            )

    _validate_installed_manifest(
        manifest=manifest,
        artifact_version=artifact_version,
        existing_files=existing_files,
    )

    return installed_resolved



def create_clean_consumer_workspace(
    *,
    consumer_root: Path | str,
    fixture_root: Path | str,
    package_source_root: Path | str,
) -> dict[str, str]:
    consumer = Path(consumer_root)
    fixtures = Path(fixture_root)
    package_source = Path(package_source_root)

    if fixtures.is_symlink() or not fixtures.is_dir():
        raise BrowserReleaseCandidateError(
            "browser clean-consumer fixture root must be a real directory"
        )

    try:
        ensure_empty_target(consumer)
    except ReleaseCandidateContractError as exc:
        raise BrowserReleaseCandidateError(str(exc)) from exc

    consumer.mkdir(parents=True, exist_ok=True)
    validate_clean_consumer_isolation(
        consumer_root=consumer,
        package_source_root=package_source,
    )

    manifest = build_clean_consumer_npm_manifest()
    validate_clean_consumer_manifest(manifest)

    package_json_path = consumer / "package.json"
    package_json_path.write_text(
        json.dumps(
            manifest,
            ensure_ascii=False,
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )

    source_paths: dict[str, Path] = {}
    for name in ("main.ts", "smoke.mjs"):
        source = fixtures / name
        _require_regular_file(
            source,
            label=f"browser clean-consumer fixture {name}",
        )
        text = source.read_text(encoding="utf-8")
        validate_clean_consumer_source(text)
        target = consumer / name
        target.write_text(text, encoding="utf-8")
        source_paths[name] = target

    tsconfig = {
        "compilerOptions": {
            "target": "ES2022",
            "module": "ESNext",
            "moduleResolution": "Bundler",
            "strict": True,
            "noEmit": True,
            "lib": ["ES2022", "DOM"],
            "skipLibCheck": True,
        },
        "include": ["main.ts"],
    }
    tsconfig_path = consumer / "tsconfig.json"
    tsconfig_path.write_text(
        json.dumps(tsconfig, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    index_path = consumer / "index.html"
    index_path.write_text(
        "<!doctype html>\n"
        "<html><head><meta charset=\"UTF-8\"></head><body>\n"
        '<script type="module" src="/main.ts"></script>\n'
        "</body></html>\n",
        encoding="utf-8",
    )

    validate_clean_consumer_manifest(
        json.loads(package_json_path.read_text(encoding="utf-8"))
    )
    validate_clean_consumer_source(
        source_paths["main.ts"].read_text(encoding="utf-8")
    )
    validate_clean_consumer_source(
        source_paths["smoke.mjs"].read_text(encoding="utf-8")
    )

    return {
        "consumerRoot": str(consumer),
        "packageJsonPath": str(package_json_path),
        "tsconfigPath": str(tsconfig_path),
        "indexPath": str(index_path),
        "mainPath": str(source_paths["main.ts"]),
        "smokePath": str(source_paths["smoke.mjs"]),
    }


def _consumer_environment() -> dict[str, str]:
    environment = dict(os.environ)
    for key in ("NPM_TOKEN", "NODE_AUTH_TOKEN", "PACKAGIST_TOKEN"):
        environment.pop(key, None)

    environment["npm_config_audit"] = "false"
    environment["npm_config_fund"] = "false"
    environment["npm_config_ignore_scripts"] = "true"
    environment["npm_config_package_lock"] = "false"
    return environment


def _run_consumer_command(
    command: list[str],
    *,
    cwd: Path,
    allow_failure: bool = False,
) -> subprocess.CompletedProcess[str]:
    try:
        completed = subprocess.run(
            command,
            cwd=cwd,
            env=_consumer_environment(),
            check=False,
            capture_output=True,
            text=True,
        )
    except OSError as exc:
        raise BrowserReleaseCandidateError(
            f"browser clean-consumer command could not start: {command[0]}"
        ) from exc

    if not allow_failure and completed.returncode != 0:
        raise BrowserReleaseCandidateError(
            "browser clean-consumer command failed: "
            + " ".join(command[:3])
        )

    return completed


def execute_clean_consumer_proof(
    *,
    consumer_root: Path | str,
    archive_path: Path | str,
    artifact_version: str,
    package_source_root: Path | str,
) -> dict[str, object]:
    version = validate_artifact_version(artifact_version)
    consumer = validate_clean_consumer_isolation(
        consumer_root=consumer_root,
        package_source_root=package_source_root,
    )
    archive = Path(archive_path).resolve(strict=False)
    validate_browser_artifact_archive(
        archive_path=archive,
        artifact_version=version,
    )

    package_json_path = consumer / "package.json"
    try:
        original_manifest_bytes = package_json_path.read_bytes()
        manifest = json.loads(original_manifest_bytes.decode("utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise BrowserReleaseCandidateError(
            "browser clean-consumer package.json must be valid UTF-8 JSON"
        ) from exc

    if manifest != build_clean_consumer_npm_manifest():
        raise BrowserReleaseCandidateError(
            "browser clean-consumer manifest must match the pinned host-tool contract"
        )
    validate_clean_consumer_manifest(manifest)

    main_source = (consumer / "main.ts").read_text(encoding="utf-8")
    smoke_source = (consumer / "smoke.mjs").read_text(encoding="utf-8")
    validate_clean_consumer_source(main_source)
    validate_clean_consumer_source(smoke_source)

    _run_consumer_command(
        [
            "npm",
            "install",
            "--ignore-scripts",
            "--no-audit",
            "--no-fund",
            "--package-lock=false",
        ],
        cwd=consumer,
    )
    _run_consumer_command(
        [
            "npm",
            "install",
            "--ignore-scripts",
            "--no-audit",
            "--no-fund",
            "--no-save",
            "--package-lock=false",
            str(archive),
        ],
        cwd=consumer,
    )

    if package_json_path.read_bytes() != original_manifest_bytes:
        raise BrowserReleaseCandidateError(
            "browser clean-consumer install must not persist SurfaceRelay into package.json"
        )

    installed_root = verify_clean_consumer_install(
        consumer_root=consumer,
        artifact_version=version,
        package_source_root=package_source_root,
    )

    root_import = _run_consumer_command(
        [
            "node",
            "--input-type=module",
            "--eval",
            f"await import('{PACKAGE_NAME}');",
        ],
        cwd=consumer,
    )

    typecheck = _run_consumer_command(
        [
            "npm",
            "exec",
            "--offline",
            "--",
            "tsc",
            "-p",
            "tsconfig.json",
        ],
        cwd=consumer,
    )

    bundle = _run_consumer_command(
        [
            "npm",
            "exec",
            "--offline",
            "--",
            "vite",
            "build",
        ],
        cwd=consumer,
    )

    smoke = _run_consumer_command(
        ["node", "smoke.mjs"],
        cwd=consumer,
    )
    smoke_output = smoke.stdout.strip()
    expected_smoke = "SurfaceRelay browser-runtime clean-consumer smoke: PASS"
    if smoke_output != expected_smoke:
        raise BrowserReleaseCandidateError(
            "browser clean-consumer smoke output is not the expected PASS marker"
        )

    deep_import = _run_consumer_command(
        [
            "node",
            "--input-type=module",
            "--eval",
            (
                "await import("
                f"'{PACKAGE_NAME}/dist/driver-registry.js'"
                ");"
            ),
        ],
        cwd=consumer,
        allow_failure=True,
    )
    deep_output = deep_import.stdout + deep_import.stderr
    if (
        deep_import.returncode == 0
        or "ERR_PACKAGE_PATH_NOT_EXPORTED" not in deep_output
    ):
        raise BrowserReleaseCandidateError(
            "browser clean-consumer deep import was not rejected by the exports map"
        )

    validate_clean_consumer_isolation(
        consumer_root=consumer,
        package_source_root=package_source_root,
    )

    return {
        "installedRoot": str(installed_root),
        "rootImport": root_import.returncode == 0,
        "typecheck": typecheck.returncode == 0,
        "bundle": bundle.returncode == 0,
        "smokeOutput": smoke_output,
        "deepImportRejected": True,
    }


def build_browser_release_candidate(
    *,
    repo: Path | str,
    stage_root: Path | str,
    artifact_version: str,
    source_revision: str,
) -> dict[str, str]:
    repository = Path(repo)
    stage = Path(stage_root)
    version = validate_artifact_version(artifact_version)
    revision = validate_source_revision(source_revision)

    if stage.is_symlink():
        raise BrowserReleaseCandidateError(
            "browser release-candidate stage root must not be a symlink"
        )

    try:
        ensure_empty_target(stage)
    except ReleaseCandidateContractError as exc:
        raise BrowserReleaseCandidateError(str(exc)) from exc

    package_source = repository / _PACKAGE_SOURCE
    if package_source.is_symlink() or not package_source.is_dir():
        raise BrowserReleaseCandidateError(
            "browser-runtime source package root must be a real directory"
        )

    source_manifest = package_source / "package.json"
    source_license = repository / "LICENSE"
    source_dist = package_source / "dist"

    package_root = resolve_staging_path(stage, "package")
    manifest_path = resolve_staging_path(stage, "content-manifest.json")
    evidence_path = resolve_staging_path(stage, "artifact-evidence.json")

    stage.mkdir(parents=True, exist_ok=True)
    package_root.mkdir(parents=True, exist_ok=False)

    (package_root / "package.json").write_bytes(
        _staged_manifest_bytes(source_manifest, version)
    )
    _copy_distribution_tree(source_dist, package_root / "dist")
    _copy_regular_file(source_license, package_root / "LICENSE")
    (package_root / "README.md").write_bytes(
        _candidate_readme(version, revision)
    )

    manifest = build_content_manifest(
        package_root,
        PACKAGE_NAME,
        version,
        revision,
    )
    manifest_path.write_bytes(serialize_evidence_json(manifest))

    archive_path = _run_npm_pack(package_root, stage)
    _validate_tarball(
        archive_path=archive_path,
        package_root=package_root,
        artifact_version=version,
    )

    evidence = build_artifact_evidence(
        stage_root=stage,
        content_manifest_path=manifest_path,
        archive_path=archive_path,
        package_name=PACKAGE_NAME,
        artifact_version=version,
        source_revision=revision,
    )
    evidence_path.write_bytes(serialize_evidence_json(evidence))

    return {
        "packageName": PACKAGE_NAME,
        "artifactVersion": version,
        "sourceRevision": revision,
        "packageRoot": str(package_root),
        "contentManifestPath": str(manifest_path),
        "archivePath": str(archive_path),
        "artifactEvidencePath": str(evidence_path),
    }
