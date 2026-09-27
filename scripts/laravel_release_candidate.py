from __future__ import annotations

import json
from pathlib import Path
import stat
import zipfile

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


PACKAGE_NAME = "surfacerelay/laravel"
_PACKAGE_SOURCE = Path("packages") / "laravel"
_FIXED_ZIP_TIMESTAMP = (1980, 1, 1, 0, 0, 0)
_REGULAR_FILE_MODE = 0o100644


class LaravelReleaseCandidateError(ReleaseCandidateContractError):
    """Raised when the Laravel release-candidate package cannot be built safely."""


def _require_regular_file(path: Path, *, label: str) -> Path:
    if path.is_symlink():
        raise LaravelReleaseCandidateError(f"{label} must not be a symlink")

    try:
        mode = path.lstat().st_mode
    except OSError as exc:
        raise LaravelReleaseCandidateError(f"{label} must exist") from exc

    if not stat.S_ISREG(mode):
        raise LaravelReleaseCandidateError(f"{label} must be a regular file")

    return path


def _copy_regular_file(source: Path, target: Path) -> None:
    _require_regular_file(source, label=f"source file {source.name}")
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_bytes(source.read_bytes())


def _copy_regular_tree(source: Path, target: Path, *, label: str) -> None:
    if source.is_symlink():
        raise LaravelReleaseCandidateError(f"{label} must not be a symlink")

    try:
        source_mode = source.lstat().st_mode
    except OSError as exc:
        raise LaravelReleaseCandidateError(f"{label} must exist") from exc

    if not stat.S_ISDIR(source_mode):
        raise LaravelReleaseCandidateError(f"{label} must be a directory")

    pending: list[tuple[Path, Path]] = [(source, target)]

    while pending:
        source_dir, target_dir = pending.pop()
        target_dir.mkdir(parents=True, exist_ok=True)

        for entry in sorted(source_dir.iterdir(), key=lambda item: item.name):
            if entry.is_symlink():
                raise LaravelReleaseCandidateError(
                    f"symlink is not allowed in {label}: {entry.name}"
                )

            mode = entry.lstat().st_mode
            destination = target_dir / entry.name

            if stat.S_ISDIR(mode):
                pending.append((entry, destination))
                continue

            if stat.S_ISREG(mode):
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.write_bytes(entry.read_bytes())
                continue

            raise LaravelReleaseCandidateError(
                f"only regular files and directories are allowed in {label}: {entry.name}"
            )


def _staged_composer_bytes(source_manifest: Path, version: str) -> bytes:
    _require_regular_file(source_manifest, label="Laravel source composer.json")

    try:
        payload = json.loads(source_manifest.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise LaravelReleaseCandidateError(
            "Laravel source composer.json must be valid UTF-8 JSON"
        ) from exc

    if not isinstance(payload, dict):
        raise LaravelReleaseCandidateError(
            "Laravel source composer.json must contain a JSON object"
        )
    if payload.get("name") != PACKAGE_NAME:
        raise LaravelReleaseCandidateError(
            f"Laravel source composer.json name must be {PACKAGE_NAME}"
        )
    if "version" in payload:
        raise LaravelReleaseCandidateError(
            "Laravel source composer.json must not contain a committed version"
        )

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
        "# SurfaceRelay Laravel release-candidate artifact\n\n"
        f"Package: {PACKAGE_NAME}\n"
        f"Artifact version: {version}\n"
        f"Source revision: {revision}\n\n"
        "This is not a public release. It exists only for bounded release-readiness "
        "and clean-consumer verification.\n"
    ).encode("utf-8")


def _package_files(package_root: Path) -> list[Path]:
    files: list[Path] = []

    for path in package_root.rglob("*"):
        if path.is_symlink():
            raise LaravelReleaseCandidateError(
                f"symlink is not allowed in staged package: {path.name}"
            )
        if path.is_file():
            files.append(path)

    return sorted(files, key=lambda path: path.relative_to(package_root).as_posix())


def _write_deterministic_zip(package_root: Path, archive_path: Path) -> None:
    files = _package_files(package_root)

    with zipfile.ZipFile(
        archive_path,
        mode="w",
        compression=zipfile.ZIP_DEFLATED,
        compresslevel=9,
    ) as archive:
        for path in files:
            relative = path.relative_to(package_root).as_posix()
            if relative.startswith("/") or ".." in Path(relative).parts:
                raise LaravelReleaseCandidateError(
                    "staged package contains an unsafe archive path"
                )

            info = zipfile.ZipInfo(relative, date_time=_FIXED_ZIP_TIMESTAMP)
            info.create_system = 3
            info.external_attr = _REGULAR_FILE_MODE << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED)


def build_laravel_release_candidate(
    *,
    repo: Path | str,
    stage_root: Path | str,
    artifact_version: str,
    source_revision: str,
) -> dict[str, str]:
    repository = Path(repo)
    version = validate_artifact_version(artifact_version)
    revision = validate_source_revision(source_revision)
    stage = Path(stage_root)

    if stage.is_symlink():
        raise LaravelReleaseCandidateError(
            "Laravel release-candidate stage root must not be a symlink"
        )

    try:
        ensure_empty_target(stage)
    except ReleaseCandidateContractError as exc:
        raise LaravelReleaseCandidateError(str(exc)) from exc

    package_source = repository / _PACKAGE_SOURCE
    if package_source.is_symlink() or not package_source.is_dir():
        raise LaravelReleaseCandidateError(
            "Laravel source package root must be a real directory"
        )

    source_manifest = package_source / "composer.json"
    source_license = repository / "LICENSE"

    package_root = resolve_staging_path(stage, "package")
    manifest_path = resolve_staging_path(stage, "content-manifest.json")
    archive_path = resolve_staging_path(
        stage,
        f"surfacerelay-laravel-{version}.zip",
    )
    evidence_path = resolve_staging_path(stage, "artifact-evidence.json")

    stage.mkdir(parents=True, exist_ok=True)
    package_root.mkdir(parents=True, exist_ok=False)

    (package_root / "composer.json").write_bytes(
        _staged_composer_bytes(source_manifest, version)
    )
    _copy_regular_tree(
        package_source / "src",
        package_root / "src",
        label="Laravel source src tree",
    )
    _copy_regular_tree(
        package_source / "database",
        package_root / "database",
        label="Laravel source database tree",
    )
    _copy_regular_file(source_license, package_root / "LICENSE")
    (package_root / "README.md").write_bytes(_candidate_readme(version, revision))

    manifest = build_content_manifest(
        package_root,
        PACKAGE_NAME,
        version,
        revision,
    )
    manifest_path.write_bytes(serialize_evidence_json(manifest))

    _write_deterministic_zip(package_root, archive_path)

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
