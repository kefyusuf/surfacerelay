from __future__ import annotations

import json
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
