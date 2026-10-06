"""T-903 disposable metadata proposals; no registry write or release approval."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.browser_release_candidate import validate_browser_artifact_archive, _npm_command
from scripts.laravel_release_candidate import validate_laravel_artifact_archive
from scripts.release_candidate import build_content_manifest, ensure_empty_target, resolve_staging_path, serialize_evidence_json, validate_artifact_version, validate_source_revision
from scripts.release_readiness import _verify_candidate


def _verified_files(stage: Path, name: str, version: str, revision: str):
    evidence = json.loads((stage / "artifact-evidence.json").read_text())
    archive = stage / evidence["archiveFilename"]
    if not archive.is_file() and name == "@surfacerelay/browser-runtime":
        archive = stage / "artifacts" / evidence["archiveFilename"]
    verified = _verify_candidate(stage=stage, result={"packageName": name,
        "artifactVersion": version, "sourceRevision": revision,
        "artifactEvidencePath": str(stage / "artifact-evidence.json"),
        "contentManifestPath": str(stage / "content-manifest.json"), "archivePath": str(archive)},
        package_name=name, version=version, revision=revision)
    if name == "surfacerelay/laravel":
        validate_laravel_artifact_archive(archive_path=archive, artifact_version=version)
        with zipfile.ZipFile(archive) as handle:
            files = {entry.filename: handle.read(entry) for entry in handle.infolist() if not entry.is_dir()}
    else:
        validate_browser_artifact_archive(archive_path=archive, artifact_version=version)
        with tarfile.open(archive, "r:gz") as handle:
            files = {entry.name.removeprefix("package/"): handle.extractfile(entry).read()
                     for entry in handle.getmembers() if entry.isfile()}
    manifest = json.loads((stage / "content-manifest.json").read_text())
    actual = [{"path": path, "size": len(data), "sha256": hashlib.sha256(data).hexdigest()}
              for path, data in sorted(files.items())]
    if actual != manifest.get("files"):
        raise ValueError("Archive bytes do not match the candidate content manifest.")
    return files, verified


def create_publication_preview(*, candidate_root: Path, preview_root: Path, version: str, revision: str):
    version = validate_artifact_version(version)
    revision = validate_source_revision(revision)
    candidate_root, preview_root = Path(candidate_root), Path(preview_root)
    ensure_empty_target(preview_root)
    for kind in ("laravel", "browser-runtime"):
        candidate = (candidate_root / kind).resolve()
        output = preview_root.resolve()
        if candidate == output or candidate in output.parents or output in candidate.parents:
            raise ValueError("Preview must be separate from candidate inputs.")
    packages = {kind: _verified_files(candidate_root / kind, name, version, revision)
        for kind, name in (("laravel", "surfacerelay/laravel"), ("browser-runtime", "@surfacerelay/browser-runtime"))}
    # All identity/integrity checks finish before any proposal is written.
    preview_root.mkdir(parents=True, exist_ok=True)
    for kind, (files, _) in packages.items():
        for path, data in files.items():
            target = resolve_staging_path(preview_root / kind, path)
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
        manifest_name = "composer.json" if kind == "laravel" else "package.json"
        path = preview_root / kind / manifest_name
        metadata = json.loads(path.read_text())
        if kind == "laravel":
            # Proposed VCS-channel metadata only. The channel/repository is undecided.
            metadata.pop("version", None)
            metadata.pop("require-dev", None)
            metadata.pop("autoload-dev", None)
            metadata.pop("scripts", None)
        else:
            metadata["private"] = False
            metadata.pop("scripts", None)
            metadata.pop("devDependencies", None)
            metadata["publishConfig"] = {"registry": "https://registry.npmjs.org/", "access": "public", "tag": "alpha"}
            metadata["repository"] = {"type": "git", "url": "https://github.com/kefyusuf/surfacerelay.git",
                                      "directory": "packages/browser-runtime"}
        path.write_text(json.dumps(metadata, indent=2) + "\n")
        (preview_root / kind / "README.md").write_text(
            f"# SurfaceRelay {kind}\n\nExperimental, unofficial runtime.\n\n"
            f"Publication metadata preview for {version}, source {revision}; not a registry release.\n"
            "Local packing and manifest validation do not prove registry authority or security support.\n"
            "See https://github.com/kefyusuf/surfacerelay for implemented behavior and release status.\n")
    record = {"schemaVersion": 1, "version": version, "sourceRevision": revision,
        "purpose": "metadata-review-only", "candidates": {kind: proof for kind, (_, proof) in packages.items()},
        "previewContent": {kind: build_content_manifest(preview_root / kind, proof["packageName"], version, revision)
                           for kind, (_, proof) in packages.items()},
        "composerChannel": "undecided; VCS-root manifest proposal only",
        "publication": {"decision": "NO-GO", "blockers": ["composer-channel-undecided",
            "private-security-intake-unverified", "registry-authority-and-credentials-unverified",
            "formal-decision-dispositions-pending", "publication-not-authorized"]}}
    (preview_root / "preview-evidence.json").write_bytes(serialize_evidence_json(record))
    return record


def _run_preview_command(command: list[str], *, cwd: Path):
    if command[0] == "npm":
        command = _npm_command(command[1:])
    with tempfile.TemporaryDirectory(prefix="surfacerelay-preview-home-") as directory:
        home = Path(directory)
        config = home / "empty.npmrc"
        config.write_text("")
        environment = {key: os.environ[key] for key in ("PATH", "SYSTEMROOT", "SystemRoot", "WINDIR", "TEMP", "TMP") if key in os.environ}
        environment.update(HOME=str(home), USERPROFILE=str(home), COMPOSER_HOME=str(home / "composer"),
            COMPOSER_NO_INTERACTION="1", npm_config_userconfig=str(config), npm_config_cache=str(home / "npm"))
        try:
            return subprocess.run(command, cwd=cwd, env=environment, check=True, capture_output=True, text=True)
        except (OSError, subprocess.CalledProcessError) as error:
            raise ValueError("Local preview command failed; no success evidence was written.") from error


def dry_run_preview(preview_root: Path):
    preview_root = Path(preview_root)
    evidence_path = preview_root / "dry-run-evidence.json"
    if evidence_path.exists():
        raise ValueError("Dry-run evidence already exists; preserve prior evidence.")
    preview = json.loads((preview_root / "preview-evidence.json").read_text())
    for kind, expected in preview["previewContent"].items():
        actual = build_content_manifest(preview_root / kind, expected["packageName"],
                                        preview["version"], preview["sourceRevision"])
        if actual != expected:
            raise ValueError("Preview changed after metadata review; create a fresh proposal.")
    packed = _run_preview_command(["npm", "pack", "--dry-run", "--json", "--ignore-scripts", "--offline"],
                                  cwd=preview_root / "browser-runtime")
    inventory = json.loads(packed.stdout)
    expected = preview["previewContent"]["browser-runtime"]
    if (not isinstance(inventory, list) or len(inventory) != 1
        or inventory[0].get("name") != expected["packageName"]
        or inventory[0].get("version") != preview["version"]
        or sorted((item["path"], item["size"]) for item in inventory[0].get("files", []))
            != [(item["path"], item["size"]) for item in expected["files"]]):
        raise ValueError("npm dry-run inventory does not match the reviewed preview.")
    _run_preview_command(["composer", "--no-plugins", "--no-scripts", "validate", "--strict", "--no-check-lock",
                          str((preview_root / "laravel/composer.json").resolve())], cwd=preview_root / "laravel")
    record = {"npmPackDryRun": inventory, "composerManifestValidation": "passed",
              "registryWrites": False, "publication": {"decision": "NO-GO"}}
    evidence_path.write_bytes(serialize_evidence_json(record))
    return record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate-root", type=Path, required=True)
    parser.add_argument("--preview-root", type=Path, required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--source-revision", required=True)
    args = parser.parse_args()
    create_publication_preview(candidate_root=args.candidate_root, preview_root=args.preview_root,
        version=args.version, revision=args.source_revision)
    dry_run_preview(args.preview_root)
    print("Publication metadata preview and local dry-run passed; publication NO-GO.")


if __name__ == "__main__":
    main()
