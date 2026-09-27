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



_ALLOWED_LARAVEL_CONSTRAINTS = frozenset({"^12.0", "^13.0"})
_FORBIDDEN_CONSUMER_TOKENS = (
    "packages/laravel",
    "dev-main",
    "file:",
    "workspace:",
    "../packages",
)


def build_clean_consumer_composer_manifest(
    *,
    artifact_directory: Path | str,
    artifact_version: str,
    laravel_constraint: str,
) -> dict[str, object]:
    version = validate_artifact_version(artifact_version)
    if laravel_constraint not in _ALLOWED_LARAVEL_CONSTRAINTS:
        raise LaravelReleaseCandidateError(
            "clean consumer Laravel constraint must be exactly ^12.0 or ^13.0"
        )

    artifact_url = Path(artifact_directory).as_posix()
    if not artifact_url:
        raise LaravelReleaseCandidateError(
            "clean consumer artifact directory must be non-empty"
        )

    manifest: dict[str, object] = {
        "name": "surfacerelay/laravel-clean-consumer",
        "type": "project",
        "repositories": [
            {
                "type": "artifact",
                "url": artifact_url,
            }
        ],
        "require": {
            "php": "^8.3",
            "laravel/framework": laravel_constraint,
            PACKAGE_NAME: version,
        },
    }
    validate_clean_consumer_manifest(manifest)
    return manifest


def validate_clean_consumer_manifest(manifest: object) -> dict[str, object]:
    if not isinstance(manifest, dict):
        raise LaravelReleaseCandidateError(
            "clean consumer composer manifest must be a JSON object"
        )

    repositories = manifest.get("repositories")
    if (
        not isinstance(repositories, list)
        or len(repositories) != 1
        or not isinstance(repositories[0], dict)
    ):
        raise LaravelReleaseCandidateError(
            "clean consumer must define exactly one SurfaceRelay artifact repository"
        )

    repository = repositories[0]
    if repository.get("type") != "artifact":
        raise LaravelReleaseCandidateError(
            "clean consumer SurfaceRelay repository type must be artifact"
        )

    repository_url = repository.get("url")
    if not isinstance(repository_url, str) or repository_url == "":
        raise LaravelReleaseCandidateError(
            "clean consumer artifact repository URL must be a non-empty string"
        )

    requirements = manifest.get("require")
    if not isinstance(requirements, dict):
        raise LaravelReleaseCandidateError(
            "clean consumer composer manifest must define require"
        )

    surface_version = requirements.get(PACKAGE_NAME)
    if not isinstance(surface_version, str):
        raise LaravelReleaseCandidateError(
            "clean consumer must require an exact SurfaceRelay artifact version"
        )
    try:
        validate_artifact_version(surface_version)
    except ReleaseCandidateContractError as exc:
        raise LaravelReleaseCandidateError(
            "clean consumer SurfaceRelay requirement must be an exact prerelease version"
        ) from exc

    laravel_constraint = requirements.get("laravel/framework")
    if laravel_constraint not in _ALLOWED_LARAVEL_CONSTRAINTS:
        raise LaravelReleaseCandidateError(
            "clean consumer Laravel constraint must be exactly ^12.0 or ^13.0"
        )

    encoded = json.dumps(
        manifest,
        ensure_ascii=False,
        separators=(",", ":"),
        sort_keys=True,
    ).lower()
    if any(token in encoded for token in _FORBIDDEN_CONSUMER_TOKENS):
        raise LaravelReleaseCandidateError(
            "clean consumer manifest contains forbidden source/package coupling"
        )

    return manifest


def validate_clean_consumer_isolation(
    *,
    consumer_root: Path | str,
    package_source_root: Path | str,
) -> Path:
    consumer = Path(consumer_root)
    package_source = Path(package_source_root)

    if consumer.is_symlink() or package_source.is_symlink():
        raise LaravelReleaseCandidateError(
            "consumer and package source roots must be real directories"
        )

    try:
        consumer_resolved = consumer.resolve(strict=True)
        package_resolved = package_source.resolve(strict=True)
    except OSError as exc:
        raise LaravelReleaseCandidateError(
            "consumer and package source roots must exist"
        ) from exc

    if not consumer_resolved.is_dir() or not package_resolved.is_dir():
        raise LaravelReleaseCandidateError(
            "consumer and package source roots must be directories"
        )

    if (
        consumer_resolved == package_resolved
        or package_resolved in consumer_resolved.parents
        or consumer_resolved in package_resolved.parents
    ):
        raise LaravelReleaseCandidateError(
            "clean consumer directory must be isolated from the package source tree"
        )

    for path in consumer.rglob("*"):
        if not path.is_symlink():
            continue
        try:
            target = path.resolve(strict=True)
        except OSError as exc:
            raise LaravelReleaseCandidateError(
                "clean consumer must not contain broken symlinks"
            ) from exc

        if target == package_resolved or package_resolved in target.parents:
            raise LaravelReleaseCandidateError(
                "clean consumer symlink must not target the package source tree"
            )

    return consumer_resolved


def validate_laravel_artifact_archive(
    *,
    archive_path: Path | str,
    artifact_version: str,
) -> dict[str, object]:
    version = validate_artifact_version(artifact_version)
    archive = Path(archive_path)

    try:
        _require_regular_file(archive, label="Laravel artifact archive")
    except LaravelReleaseCandidateError:
        raise
    except OSError as exc:
        raise LaravelReleaseCandidateError(
            "Laravel artifact archive must exist"
        ) from exc

    try:
        with zipfile.ZipFile(archive) as handle:
            names = handle.namelist()
            if len(names) != len(set(names)):
                raise LaravelReleaseCandidateError(
                    "Laravel artifact archive must not contain duplicate paths"
                )
            if "composer.json" not in names:
                raise LaravelReleaseCandidateError(
                    "Laravel artifact archive must contain root composer.json"
                )

            for info in handle.infolist():
                path = Path(info.filename)
                if (
                    info.filename.startswith("/")
                    or ".." in path.parts
                    or (info.create_system == 3 and stat.S_ISLNK(info.external_attr >> 16))
                ):
                    raise LaravelReleaseCandidateError(
                        "Laravel artifact archive contains an unsafe entry"
                    )

            try:
                composer_payload = json.loads(
                    handle.read("composer.json").decode("utf-8")
                )
            except (KeyError, UnicodeError, json.JSONDecodeError) as exc:
                raise LaravelReleaseCandidateError(
                    "Laravel artifact composer.json must be valid UTF-8 JSON"
                ) from exc
    except zipfile.BadZipFile as exc:
        raise LaravelReleaseCandidateError(
            "Laravel artifact archive must be a valid ZIP"
        ) from exc

    if not isinstance(composer_payload, dict):
        raise LaravelReleaseCandidateError(
            "Laravel artifact composer.json must contain a JSON object"
        )
    if composer_payload.get("name") != PACKAGE_NAME:
        raise LaravelReleaseCandidateError(
            f"Laravel artifact package name must be {PACKAGE_NAME}"
        )
    if composer_payload.get("version") != version:
        raise LaravelReleaseCandidateError(
            "Laravel artifact version does not match the requested candidate version"
        )

    return composer_payload

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
