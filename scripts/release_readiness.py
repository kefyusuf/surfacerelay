"""T-805 integrated release-readiness verification.

Builds both release candidates from one clean source revision and one internal
prerelease version, independently re-verifies each candidate's evidence, and
records an aggregate readiness evidence file with a publication go/no-go
handoff. It never publishes, tags, or selects a public version (D-073), so its
publication decision is always NO-GO with the remaining blockers listed.
"""

from __future__ import annotations

from collections.abc import Callable
import hashlib
import json
from pathlib import Path, PurePosixPath
import shutil
import subprocess
import tarfile
import tempfile

from scripts.browser_release_candidate import (
    PACKAGE_NAME as BROWSER_PACKAGE_NAME,
    build_browser_release_candidate,
    _run_consumer_command,
)
from scripts.laravel_release_candidate import (
    PACKAGE_NAME as LARAVEL_PACKAGE_NAME,
    build_laravel_release_candidate,
)
from scripts.release_candidate import (
    ReleaseCandidateContractError,
    ensure_empty_target,
    preflight_repository,
    resolve_staging_path,
    serialize_evidence_json,
    validate_artifact_version,
    validate_source_revision,
)


Builder = Callable[..., dict[str, str]]

_CANDIDATES = (
    ("laravel", LARAVEL_PACKAGE_NAME),
    ("browser-runtime", BROWSER_PACKAGE_NAME),
)


class ReleaseReadinessError(ReleaseCandidateContractError):
    pass


def _sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def _extract_source_archive(archive: Path, source: Path) -> None:
    """Extract regular Git archive entries without accepting links or escapes."""
    with tarfile.open(archive, "r:") as handle:
        for member in handle.getmembers():
            relative = PurePosixPath(member.name)
            if (
                relative.is_absolute()
                or ".." in relative.parts
                or "\\" in member.name
                or ":" in member.name
                or not (member.isfile() or member.isdir())
            ):
                raise ReleaseReadinessError("source archive contains an unsafe entry")
            target = resolve_staging_path(source, member.name)
            if member.isdir():
                target.mkdir(parents=True, exist_ok=True)
            else:
                target.parent.mkdir(parents=True, exist_ok=True)
                content = handle.extractfile(member)
                if content is None:
                    raise ReleaseReadinessError("source archive file has no content")
                with content, target.open("xb") as output:
                    shutil.copyfileobj(content, output)
                target.chmod(member.mode & 0o777)


def _archive_source(repo: Path | str, revision: str, temporary: Path) -> Path:
    archive = temporary / "source.tar"
    source = temporary / "source"
    source.mkdir()
    try:
        subprocess.run(
            ["git", "-C", str(repo), "archive", "--format=tar", f"--output={archive}", revision],
            check=True,
            capture_output=True,
        )
        _extract_source_archive(archive, source)
    except (OSError, subprocess.CalledProcessError, tarfile.TarError) as exc:
        raise ReleaseReadinessError("could not create an isolated source revision archive") from exc
    return source


def _rebuild_browser_distribution(source: Path) -> None:
    package = source / "packages" / "browser-runtime"
    distribution = package / "dist"
    if distribution.exists():
        shutil.rmtree(distribution)
    _run_consumer_command(["npm", "ci", "--ignore-scripts"], cwd=package)
    _run_consumer_command(["npm", "run", "build"], cwd=package)


def _regular_file_under(stage: Path, candidate: str, *, label: str) -> Path:
    path = Path(candidate)
    resolved = path.resolve(strict=False)
    root = stage.resolve(strict=False)
    if root not in resolved.parents or path.is_symlink() or not path.is_file():
        raise ReleaseReadinessError(f"{label} must be a regular file inside its stage")
    return resolved


def _verify_candidate(
    *,
    stage: Path,
    result: dict[str, str],
    package_name: str,
    version: str,
    revision: str,
) -> dict[str, object]:
    if (
        result.get("packageName") != package_name
        or result.get("artifactVersion") != version
        or result.get("sourceRevision") != revision
    ):
        raise ReleaseReadinessError(
            f"{package_name} candidate identity does not match the readiness inputs"
        )

    evidence_path = _regular_file_under(stage, result["artifactEvidencePath"], label="artifact evidence")
    manifest_path = _regular_file_under(stage, result["contentManifestPath"], label="content manifest")
    archive_path = _regular_file_under(stage, result["archivePath"], label="archive")

    try:
        evidence = json.loads(evidence_path.read_text(encoding="utf-8"))
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise ReleaseReadinessError(f"{package_name} evidence must be valid UTF-8 JSON") from exc

    for document in (evidence, manifest):
        if (
            not isinstance(document, dict)
            or document.get("packageName") != package_name
            or document.get("artifactVersion") != version
            or document.get("sourceRevision") != revision
        ):
            raise ReleaseReadinessError(
                f"{package_name} evidence identity does not match the readiness inputs"
            )

    if (
        evidence.get("archiveFilename") != archive_path.name
        or evidence.get("archiveSize") != archive_path.stat().st_size
        or evidence.get("archiveSha256") != _sha256_file(archive_path)
        or evidence.get("contentManifestSha256") != _sha256_file(manifest_path)
    ):
        raise ReleaseReadinessError(
            f"{package_name} archive or content manifest does not match its evidence"
        )

    root = stage.parent.resolve(strict=False)
    return {
        "packageName": package_name,
        "archiveFilename": archive_path.name,
        "archivePath": archive_path.relative_to(root).as_posix(),
        "archiveSize": evidence["archiveSize"],
        "archiveSha256": evidence["archiveSha256"],
        "contentManifestSha256": evidence["contentManifestSha256"],
    }


def _publication_handoff(*, private_vulnerability_reporting_verified: bool) -> dict[str, object]:
    blockers = []
    if not private_vulnerability_reporting_verified:
        blockers.append("private-vulnerability-reporting-unverified")
    blockers += [
        "public-version-not-approved",
        "registry-namespace-and-credentials-unverified",
        "publication-not-authorized",
    ]
    return {"decision": "NO-GO", "blockers": blockers}


def build_release_readiness(
    *,
    repo: Path | str,
    stage_root: Path | str,
    artifact_version: str,
    source_revision: str,
    private_vulnerability_reporting_verified: bool = False,
    laravel_builder: Builder = build_laravel_release_candidate,
    browser_builder: Builder = build_browser_release_candidate,
) -> dict[str, object]:
    try:
        version = validate_artifact_version(artifact_version)
        revision = validate_source_revision(source_revision)
        preflight_repository(repo, revision)
        stage = Path(stage_root)
        if stage.is_symlink():
            raise ReleaseReadinessError("readiness stage root must not be a symlink")
        ensure_empty_target(stage)
    except ReleaseReadinessError:
        raise
    except ReleaseCandidateContractError as exc:
        raise ReleaseReadinessError(str(exc)) from exc

    builders = {"laravel": laravel_builder, "browser-runtime": browser_builder}
    packages = []
    with tempfile.TemporaryDirectory(prefix="surfacerelay-readiness-") as directory:
        source = _archive_source(repo, revision, Path(directory))
        for stage_name, package_name in _CANDIDATES:
            candidate_stage = resolve_staging_path(stage, stage_name)
            try:
                if stage_name == "browser-runtime" and browser_builder is build_browser_release_candidate:
                    _rebuild_browser_distribution(source)
                result = builders[stage_name](
                    repo=source,
                    stage_root=candidate_stage,
                    artifact_version=version,
                    source_revision=revision,
                )
            except ReleaseCandidateContractError as exc:
                raise ReleaseReadinessError(f"{package_name} candidate build failed: {exc}") from exc

            packages.append(_verify_candidate(
                stage=candidate_stage,
                result=result,
                package_name=package_name,
                version=version,
                revision=revision,
            ))

    readiness = {
        "schemaVersion": 1,
        "artifactVersion": version,
        "sourceRevision": revision,
        "packages": packages,
        "publication": _publication_handoff(
            private_vulnerability_reporting_verified=private_vulnerability_reporting_verified,
        ),
    }
    resolve_staging_path(stage, "readiness-evidence.json").write_bytes(
        serialize_evidence_json(readiness)
    )
    return readiness
