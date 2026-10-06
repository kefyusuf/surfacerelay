"""Local distribution-tree preparation; never creates a remote or publishes."""
from __future__ import annotations

import argparse
from pathlib import Path
import shutil
import sys
import tempfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.release_candidate import ensure_empty_target, serialize_evidence_json
from scripts.release_publication_preview import create_publication_preview


def prepare_distribution_tree(*, candidate_root: Path, output_root: Path,
                              version: str, revision: str):
    candidate_root, output_root = Path(candidate_root), Path(output_root)
    ensure_empty_target(output_root)
    if output_root.is_symlink():
        raise ValueError("Distribution output must be a real directory.")
    output = output_root.resolve()
    for kind in ("laravel", "browser-runtime"):
        candidate = (candidate_root / kind).resolve()
        if candidate == output or candidate in output.parents or output in candidate.parents:
            raise ValueError("Distribution output must be separate from candidate inputs.")
    # Reuse the two-package identity/integrity and metadata transformation rules.
    # Complete all candidate checks before writing the requested destination.
    with tempfile.TemporaryDirectory(prefix="surfacerelay-distribution-") as directory:
        preview_root = Path(directory) / "preview"
        preview = create_publication_preview(candidate_root=candidate_root,
            preview_root=preview_root, version=version, revision=revision)
        record = {"schemaVersion": 1, "purpose": "local-distribution-tree-review",
            "sourceRevision": preview["sourceRevision"], "version": preview["version"],
            "upstreamRepository": "https://github.com/kefyusuf/surfacerelay",
            "candidates": preview["candidates"],
            "contentManifest": preview["previewContent"]["laravel"],
            "remoteRepository": None, "mirrorCommit": None,
            "publication": {"decision": "NO-GO", "blockers": [
                "mirror-identity-and-remote-work-not-authorized",
                "mirror-commit-mapping-unverified", "registry-authority-unverified",
                "final-publication-not-authorized"]}}
        shutil.copytree(preview_root / "laravel", output_root / "package")
        (output_root / "mapping-evidence.json").write_bytes(serialize_evidence_json(record))
    return record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate-root", type=Path, required=True)
    parser.add_argument("--output-root", type=Path, required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--source-revision", required=True)
    args = parser.parse_args()
    prepare_distribution_tree(candidate_root=args.candidate_root, output_root=args.output_root,
        version=args.version, revision=args.source_revision)
    print("Local Laravel distribution tree prepared; remote/commit mapping pending; publication NO-GO.")


if __name__ == "__main__":
    main()
