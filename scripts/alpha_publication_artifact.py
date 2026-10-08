"""Offline final alpha packaging, never registry publication or release approval.

CLI: --candidate-root DIR --output-root EMPTY --version SEMVER --revision SHA.
The result has laravel/ (versionless VCS root), browser-runtime/ (public npm
tree), artifacts/*.tgz and publication-artifact-evidence.json. Consumer callers
pass evidence['content']['browser-runtime'] and evidence['browserArchive']['sha256']
to validate_public_browser_archive; verify_public_browser_install checks installed
bytes against that same manifest before running existing root-import/type/bundle
and DriverRegistry fixtures. Neither verifier executes the package.
"""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import shutil
import sys
import tarfile
import tempfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.browser_release_candidate import validate_clean_consumer_isolation
from scripts.release_candidate import (
    build_content_manifest, ensure_empty_target, serialize_evidence_json,
    validate_artifact_version, validate_source_revision,
)
from scripts.release_publication_preview import create_publication_preview, _run_preview_command


PACKAGE_NAME = "@surfacerelay/browser-runtime"
ROOT_EXPORTS = {".": {"types": "./dist/index.d.ts", "import": "./dist/index.js"}}
PUBLISH_CONFIG = {"registry": "https://registry.npmjs.org/", "access": "public", "tag": "alpha"}


def _real_path(path: Path) -> Path:
    for item in (path.absolute(), *path.absolute().parents):
        if item.is_symlink():
            raise ValueError("Publication paths must not contain symlinks.")
    return path.resolve()


def _file_rows(files: dict[str, bytes]) -> list[dict]:
    return [{"path": path, "size": len(data), "sha256": hashlib.sha256(data).hexdigest()}
            for path, data in sorted(files.items())]


def _expected_manifest(expected: dict, version: str, revision: str) -> None:
    if (not isinstance(expected, dict) or expected.get("schemaVersion") != 1
            or expected.get("packageName") != PACKAGE_NAME
            or expected.get("artifactVersion") != version
            or expected.get("sourceRevision") != revision
            or not isinstance(expected.get("files"), list)):
        raise ValueError("Public browser content manifest identity/source mismatch.")


def _check_browser_files(files: dict[str, bytes], version: str, revision: str) -> None:
    try:
        metadata = json.loads(files["package.json"].decode("utf-8"))
        readme = files["README.md"].decode("utf-8")
    except (KeyError, UnicodeError, json.JSONDecodeError) as exc:
        raise ValueError("Public browser metadata/README is invalid.") from exc
    if (not isinstance(metadata, dict) or metadata.get("name") != PACKAGE_NAME
            or metadata.get("version") != version or metadata.get("private") is not False
            or metadata.get("type") != "module" or metadata.get("exports") != ROOT_EXPORTS
            or metadata.get("types") != "./dist/index.d.ts"
            or metadata.get("license") != "Apache-2.0"
            or metadata.get("files") != ["dist", "README.md", "LICENSE"]
            or metadata.get("publishConfig") != PUBLISH_CONFIG
            or any(key in metadata for key in ("main", "scripts", "devDependencies"))):
        raise ValueError("Public browser metadata does not match the reviewed alpha contract.")
    required = {"package.json", "README.md", "LICENSE", "dist/index.js", "dist/index.d.ts"}
    if not required.issubset(files):
        raise ValueError("Public browser package is missing required files.")
    if (version not in readme or revision not in readme or "Experimental" not in readme
            or any(text in readme.lower() for text in ("preview", "not a registry release", "not a public release"))):
        raise ValueError("Public browser README is not final alpha documentation.")
    for path in files:
        parts = PurePosixPath(path).parts
        if (not parts or any(part in (".", "..") for part in parts)
                or "\\" in path or PurePosixPath(path).is_absolute()
                or PurePosixPath(path).as_posix() != path
                or (path not in {"package.json", "README.md", "LICENSE"}
                    and not (path.startswith("dist/") and path.endswith((".js", ".js.map", ".d.ts", ".d.ts.map"))))):
            raise ValueError("Public browser package contains an unsupported file path.")


def validate_public_browser_archive(*, archive_path: Path, version: str, revision: str,
                                    expected_manifest: dict, expected_sha256: str) -> dict[str, bytes]:
    """Verify final tarball hash, exact identity, safe entries and every file byte."""
    version, revision = validate_artifact_version(version), validate_source_revision(revision)
    _expected_manifest(expected_manifest, version, revision)
    archive = _real_path(Path(archive_path))
    if not archive.is_file() or hashlib.sha256(archive.read_bytes()).hexdigest() != expected_sha256:
        raise ValueError("Final browser archive hash mismatch.")
    files, seen = {}, set()
    allowed_directories = {str(parent) for row in expected_manifest["files"]
                           for parent in (PurePosixPath("package") / row["path"]).parents
                           if str(parent) != "."}
    try:
        with tarfile.open(archive, "r:gz") as handle:
            for item in handle:
                path = PurePosixPath(item.name)
                if (not item.name.startswith("package/") or "\\" in item.name
                        or any(part in (".", "..") for part in path.parts)
                        or path.as_posix() != item.name.rstrip("/") or item.name in seen):
                    raise ValueError("Final browser archive has an unsafe or duplicate entry.")
                seen.add(item.name)
                if item.isdir():
                    if str(path) not in allowed_directories:
                        raise ValueError("Final browser archive contains an unexpected directory.")
                    continue
                if not item.isfile():
                    raise ValueError("Final browser archive contains a link or special entry.")
                files[item.name.removeprefix("package/")] = handle.extractfile(item).read()
    except (tarfile.TarError, OSError) as exc:
        raise ValueError("Final browser archive is invalid.") from exc
    _check_browser_files(files, version, revision)
    if _file_rows(files) != expected_manifest["files"]:
        raise ValueError("Final browser archive bytes differ from the approved content manifest.")
    return files


def verify_public_browser_install(*, consumer_root: Path, version: str, revision: str,
                                  expected_manifest: dict, package_source_root: Path) -> Path:
    """Require a real isolated node_modules package matching reviewed final bytes."""
    version, revision = validate_artifact_version(version), validate_source_revision(revision)
    _expected_manifest(expected_manifest, version, revision)
    consumer = _real_path(Path(consumer_root))
    source = _real_path(Path(package_source_root))
    validate_clean_consumer_isolation(consumer_root=consumer, package_source_root=source)
    installed = _real_path(consumer / "node_modules/@surfacerelay/browser-runtime")
    if not installed.is_dir() or installed == source or source in installed.parents:
        raise ValueError("Public browser install must be isolated from source.")
    files = {}
    for item in installed.rglob("*"):
        if item.is_symlink():
            raise ValueError("Public browser install must not contain symlinks.")
        if item.is_file():
            files[item.relative_to(installed).as_posix()] = item.read_bytes()
        elif not item.is_dir():
            raise ValueError("Public browser install contains a special entry.")
    _check_browser_files(files, version, revision)
    if _file_rows(files) != expected_manifest["files"]:
        raise ValueError("Installed public browser bytes differ from the final artifact.")
    return installed


def _readme(kind: str, version: str, revision: str) -> bytes:
    integration = (
        "Opt-in Filament integration uses FilamentBrowserDriver, FilamentSelectionCoordinator and "
        "GlobalFilamentSelectionRuntime from the package root. Helpers for a component must use the same coordinator "
        "and preserve exact binding objects from the explicitly exposed tools. Ordinary UI calls are outside helper "
        "exclusion; deferred selection writes are not transactional. Default Livewire behavior is unchanged.\n"
        "Native Completed is not business success. Inspect the execution envelope and nested application result. "
        "Unknown outcomes require application-state inspection before an explicit retry; no automatic retry is performed.\n"
        "Migration guidance: https://github.com/kefyusuf/surfacerelay/blob/main/docs/consumers/browser-runtime.md\n"
        if kind == "browser-runtime" else ""
    )
    return (f"# SurfaceRelay {kind}\n\nExperimental alpha {version}; unofficial reference runtime.\n\n"
            f"Source revision: {revision}. Package versions are independent of Action versions.\n\n"
            "This alpha release contains only surfacerelay/laravel and @surfacerelay/browser-runtime.\n"
            "APIs may change. No production-security guarantee, support SLA or general native WebMCP certification is provided.\n"
            "Server authorization, tenant context, confirmation, idempotency and output policy remain authoritative.\n"
            + integration +
            "See https://github.com/kefyusuf/surfacerelay for integration, security and migration guidance.\n"
            "Licensed under Apache-2.0; see LICENSE.\n").encode("utf-8")


def build_alpha_publication_artifacts(*, candidate_root: Path, output_root: Path,
                                    version: str, revision: str) -> dict:
    """Reverify both private candidates; stage final trees and pack npm offline."""
    version, revision = validate_artifact_version(version), validate_source_revision(revision)
    candidate = _real_path(Path(candidate_root))
    output = _real_path(Path(output_root))
    ensure_empty_target(output)
    for kind in ("laravel", "browser-runtime"):
        package_candidate = _real_path(candidate / kind)
        if package_candidate == output or package_candidate in output.parents or output in package_candidate.parents:
            raise ValueError("Final output must be separate from candidate inputs.")
    with tempfile.TemporaryDirectory(prefix="surfacerelay-final-alpha-") as directory:
        temporary = Path(directory) / "metadata"
        # Existing private candidate/preview guardrails are unchanged.
        preview = create_publication_preview(candidate_root=candidate, preview_root=temporary,
                                             version=version, revision=revision)
        for kind in ("laravel", "browser-runtime"):
            (temporary / kind / "README.md").write_bytes(_readme(kind, version, revision))
        content = {kind: build_content_manifest(temporary / kind,
                     proof["packageName"], version, revision)
                   for kind, proof in preview["candidates"].items()}
        artifact_root = Path(directory) / "artifacts"
        artifact_root.mkdir()
        packed = _run_preview_command(["npm", "pack", "--json", "--ignore-scripts", "--offline",
                                      "--pack-destination", str(artifact_root)],
                                      cwd=temporary / "browser-runtime")
        try:
            inventory = json.loads(packed.stdout)
            expected = content["browser-runtime"]
            filename = inventory[0]["filename"]
            if (len(inventory) != 1 or Path(filename).name != filename or not filename.endswith(".tgz")
                    or inventory[0].get("name") != PACKAGE_NAME or inventory[0].get("version") != version
                    or sorted((item["path"], item["size"]) for item in inventory[0]["files"])
                    != [(item["path"], item["size"]) for item in expected["files"]]):
                raise ValueError("Final npm inventory differs from reviewed files.")
        except (KeyError, IndexError, TypeError, json.JSONDecodeError) as exc:
            raise ValueError("Final npm inventory is invalid.") from exc
        archive = artifact_root / filename
        digest = hashlib.sha256(archive.read_bytes()).hexdigest()
        validate_public_browser_archive(archive_path=archive, version=version, revision=revision,
                                        expected_manifest=expected, expected_sha256=digest)
        record = {"schemaVersion": 1, "purpose": "alpha-publication-artifacts",
                  "version": version, "sourceRevision": revision,
                  "candidates": preview["candidates"], "content": content,
                  "browserArchive": {"filename": "artifacts/" + filename,
                                     "size": archive.stat().st_size, "sha256": digest},
                  "registryWrites": False, "publicationAuthorizedByTool": False}
        # No success output is written until candidate, pack and archive checks pass.
        output.mkdir(parents=True, exist_ok=True)
        for kind in ("laravel", "browser-runtime"):
            shutil.copytree(temporary / kind, output / kind)
        shutil.copytree(artifact_root, output / "artifacts")
        (output / "publication-artifact-evidence.json").write_bytes(serialize_evidence_json(record))
    return record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate-root", type=Path, required=True)
    parser.add_argument("--output-root", type=Path, required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--revision", required=True)
    args = parser.parse_args()
    build_alpha_publication_artifacts(candidate_root=args.candidate_root, output_root=args.output_root,
                                    version=args.version, revision=args.revision)
    print("Final alpha trees/tarball verified locally; no registry writes or release approval performed.")


if __name__ == "__main__":
    main()
