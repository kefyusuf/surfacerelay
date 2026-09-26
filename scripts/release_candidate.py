from __future__ import annotations

import hashlib
import json
from pathlib import Path
import re
import stat
import subprocess


class ReleaseCandidateContractError(ValueError):
    """Raised when release-candidate identity or repository preflight fails."""


_PRERELEASE_IDENTIFIER = r"(?:0|[1-9][0-9]*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)"
_PRERELEASE_SEMVER = re.compile(
    rf"^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)"
    rf"-({_PRERELEASE_IDENTIFIER}(?:\.{_PRERELEASE_IDENTIFIER})*)$"
)
_SOURCE_REVISION = re.compile(r"^[0-9a-f]{40}$")


def validate_artifact_version(value: str) -> str:
    if not isinstance(value, str) or _PRERELEASE_SEMVER.fullmatch(value) is None:
        raise ReleaseCandidateContractError(
            "artifact version must be a SemVer prerelease without build metadata"
        )
    return value


def validate_source_revision(value: str) -> str:
    if not isinstance(value, str) or _SOURCE_REVISION.fullmatch(value) is None:
        raise ReleaseCandidateContractError(
            "source revision must be a full 40-character lowercase hexadecimal Git SHA"
        )
    return value


def _run_git(repo: Path, *args: str) -> str:
    try:
        completed = subprocess.run(
            ["git", "-C", str(repo), *args],
            check=True,
            capture_output=True,
            text=True,
        )
    except (OSError, subprocess.CalledProcessError) as exc:
        raise ReleaseCandidateContractError(
            f"Git preflight failed for {repo}"
        ) from exc
    return completed.stdout.strip()


def preflight_repository(repo: Path | str, expected_revision: str) -> str:
    repo_path = Path(repo)
    expected = validate_source_revision(expected_revision)

    actual = _run_git(repo_path, "rev-parse", "HEAD")
    if actual != expected:
        raise ReleaseCandidateContractError(
            f"source revision mismatch: expected {expected}, found {actual}"
        )

    dirty = _run_git(repo_path, "status", "--porcelain", "--untracked-files=all")
    if dirty:
        raise ReleaseCandidateContractError(
            "release-candidate source tree must be clean"
        )

    return actual


def release_candidate_root(
    repo: Path | str,
    artifact_version: str,
    source_revision: str,
) -> Path:
    version = validate_artifact_version(artifact_version)
    revision = validate_source_revision(source_revision)

    return Path(repo) / ".tmp" / "release-candidate" / version / revision


def resolve_staging_path(
    stage_root: Path | str,
    relative_path: Path | str,
) -> Path:
    root = Path(stage_root).resolve(strict=False)
    relative = Path(relative_path)

    if relative.is_absolute():
        raise ReleaseCandidateContractError("staging path must be relative")

    candidate = (root / relative).resolve(strict=False)
    if candidate != root and root not in candidate.parents:
        raise ReleaseCandidateContractError(
            "staging path escapes release-candidate root"
        )

    return candidate


def ensure_empty_target(target: Path | str) -> Path:
    path = Path(target)

    if not path.exists():
        return path

    if not path.is_dir():
        raise ReleaseCandidateContractError(
            "existing evidence target must be an empty directory"
        )

    if any(path.iterdir()):
        raise ReleaseCandidateContractError(
            "existing evidence target is not empty"
        )

    return path


def _sha256_file(path: Path) -> str:
    digest = hashlib.sha256()

    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)

    return digest.hexdigest()


def serialize_evidence_json(payload: object) -> bytes:
    encoded = json.dumps(
        payload,
        ensure_ascii=False,
        separators=(",", ":"),
        sort_keys=True,
    )
    return (encoded + "\n").encode("utf-8")


def build_content_manifest(
    package_root: Path | str,
    package_name: str,
    artifact_version: str,
    source_revision: str,
) -> dict[str, object]:
    root = Path(package_root)
    version = validate_artifact_version(artifact_version)
    revision = validate_source_revision(source_revision)

    if root.is_symlink() or not root.is_dir():
        raise ReleaseCandidateContractError(
            "package content root must be a real directory"
        )

    files: list[Path] = []
    pending = [root]

    while pending:
        directory = pending.pop()

        for entry in directory.iterdir():
            if entry.is_symlink():
                raise ReleaseCandidateContractError(
                    f"symlink is not allowed in package contents: {entry.name}"
                )

            mode = entry.lstat().st_mode
            if stat.S_ISDIR(mode):
                pending.append(entry)
                continue

            if stat.S_ISREG(mode):
                files.append(entry)
                continue

            raise ReleaseCandidateContractError(
                f"only regular files are allowed in package contents: {entry.name}"
            )

    if not files:
        raise ReleaseCandidateContractError(
            "package content tree must contain at least one regular file"
        )

    files.sort(key=lambda path: path.relative_to(root).as_posix())

    entries = [
        {
            "path": path.relative_to(root).as_posix(),
            "size": path.stat().st_size,
            "sha256": _sha256_file(path),
        }
        for path in files
    ]

    return {
        "schemaVersion": 1,
        "packageName": package_name,
        "artifactVersion": version,
        "sourceRevision": revision,
        "files": entries,
    }


def _require_regular_file_under(
    stage_root: Path | str,
    candidate: Path | str,
    *,
    label: str,
) -> Path:
    root = Path(stage_root)
    path = Path(candidate)

    if root.is_symlink() or not root.is_dir():
        raise ReleaseCandidateContractError(
            "release-candidate staging root must be a real directory"
        )

    if path.is_symlink():
        raise ReleaseCandidateContractError(f"{label} must not be a symlink")

    try:
        resolved_root = root.resolve(strict=True)
        resolved_path = path.resolve(strict=True)
    except OSError as exc:
        raise ReleaseCandidateContractError(
            f"{label} must exist under the release-candidate staging root"
        ) from exc

    if resolved_path == resolved_root or resolved_root not in resolved_path.parents:
        raise ReleaseCandidateContractError(
            f"{label} escapes the release-candidate staging root"
        )

    if not stat.S_ISREG(path.lstat().st_mode):
        raise ReleaseCandidateContractError(f"{label} must be a regular file")

    return path


def build_artifact_evidence(
    *,
    stage_root: Path | str,
    content_manifest_path: Path | str,
    archive_path: Path | str,
    package_name: str,
    artifact_version: str,
    source_revision: str,
) -> dict[str, object]:
    version = validate_artifact_version(artifact_version)
    revision = validate_source_revision(source_revision)
    manifest = _require_regular_file_under(
        stage_root,
        content_manifest_path,
        label="content manifest",
    )
    archive = _require_regular_file_under(
        stage_root,
        archive_path,
        label="archive",
    )

    try:
        manifest_payload = json.loads(manifest.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise ReleaseCandidateContractError(
            "content manifest must be valid UTF-8 JSON"
        ) from exc

    if (
        not isinstance(manifest_payload, dict)
        or manifest_payload.get("packageName") != package_name
        or manifest_payload.get("artifactVersion") != version
        or manifest_payload.get("sourceRevision") != revision
    ):
        raise ReleaseCandidateContractError(
            "content manifest identity does not match artifact evidence"
        )

    return {
        "schemaVersion": 1,
        "packageName": package_name,
        "artifactVersion": version,
        "sourceRevision": revision,
        "contentManifestSha256": _sha256_file(manifest),
        "archiveFilename": archive.name,
        "archiveSize": archive.stat().st_size,
        "archiveSha256": _sha256_file(archive),
    }
