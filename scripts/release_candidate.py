from __future__ import annotations

from pathlib import Path
import re
import subprocess


class ReleaseCandidateContractError(ValueError):
    """Raised when release-candidate identity or repository preflight fails."""


_PRERELEASE_IDENTIFIER = r"(?:0|[1-9][0-9]*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)"
_PRERELEASE_SEMVER = re.compile(
    rf"^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)"
    rf"-({_PRERELEASE_IDENTIFIER}(?:\\.{_PRERELEASE_IDENTIFIER})*)$"
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
