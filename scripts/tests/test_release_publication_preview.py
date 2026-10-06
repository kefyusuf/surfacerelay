import hashlib
import importlib
import json
from pathlib import Path
import tarfile
import tempfile
import unittest
import zipfile

from scripts.release_candidate import build_artifact_evidence, build_content_manifest, serialize_evidence_json


VERSION = "0.1.0-alpha.1"
REVISION = "a" * 40


class PublicationPreviewTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.stages = {}
        repo = Path(__file__).resolve().parents[2]
        for kind, manifest_name, name in (("laravel", "composer.json", "surfacerelay/laravel"),
            ("browser-runtime", "package.json", "@surfacerelay/browser-runtime")):
            stage = self.root / kind
            package = stage / "package"
            package.mkdir(parents=True)
            manifest = json.loads((repo / "packages" / kind / manifest_name).read_text())
            manifest["version"] = VERSION
            (package / manifest_name).write_text(json.dumps(manifest))
            (package / "README.md").write_text("Local candidate; not a public release.")
            (package / "LICENSE").write_text("Apache-2.0")
            directory = package / ("src" if kind == "laravel" else "dist")
            directory.mkdir()
            (directory / ("Example.php" if kind == "laravel" else "index.js")).write_text("fixture")
            if kind != "laravel":
                (directory / "index.d.ts").write_text("export {}")
            content = stage / "content-manifest.json"
            content.write_bytes(serialize_evidence_json(build_content_manifest(package, name, VERSION, REVISION)))
            archive = stage / ("candidate.zip" if kind == "laravel" else "candidate.tgz")
            if kind == "laravel":
                with zipfile.ZipFile(archive, "w") as handle:
                    for path in package.rglob("*"):
                        if path.is_file():
                            handle.write(path, path.relative_to(package).as_posix())
            else:
                with tarfile.open(archive, "w:gz") as handle:
                    for path in package.rglob("*"):
                        if path.is_file():
                            handle.add(path, arcname="package/" + path.relative_to(package).as_posix())
            evidence = build_artifact_evidence(stage_root=stage, content_manifest_path=content,
                archive_path=archive, package_name=name, artifact_version=VERSION, source_revision=REVISION)
            (stage / "artifact-evidence.json").write_bytes(serialize_evidence_json(evidence))
            self.stages[kind] = stage

    def module(self):
        return importlib.import_module("scripts.release_publication_preview")

    def preview(self, **extra):
        return self.module().create_publication_preview(candidate_root=self.root,
            preview_root=self.root / "preview", version=VERSION, revision=REVISION, **extra)

    def test_proposed_metadata_is_separate_and_never_grants_publication(self):
        before = {path: path.read_bytes() for stage in self.stages.values() for path in stage.rglob("*") if path.is_file()}
        result = self.preview()
        npm = json.loads((self.root / "preview/browser-runtime/package.json").read_text())
        composer = json.loads((self.root / "preview/laravel/composer.json").read_text())
        self.assertFalse(npm["private"])
        self.assertEqual(npm["publishConfig"], {"registry": "https://registry.npmjs.org/", "access": "public", "tag": "alpha"})
        self.assertEqual(npm["version"], VERSION)
        self.assertNotIn("scripts", npm)
        self.assertNotIn("devDependencies", npm)
        self.assertNotIn("version", composer, "VCS versions must come from approved future tags")
        self.assertNotIn("require-dev", composer)
        self.assertEqual(result["publication"]["decision"], "NO-GO")
        self.assertIn("composer-channel-undecided", result["publication"]["blockers"])
        self.assertIn("publication-not-authorized", result["publication"]["blockers"])
        self.assertEqual(before, {path: path.read_bytes() for path in before})

    def test_preserves_runtime_bytes_and_marks_preview(self):
        self.preview()
        self.assertEqual((self.root / "preview/browser-runtime/dist/index.js").read_bytes(), b"fixture")
        self.assertEqual((self.root / "preview/laravel/src/Example.php").read_bytes(), b"fixture")
        self.assertIn("not a registry release", (self.root / "preview/laravel/README.md").read_text())

    def test_rejects_archive_tampering_before_any_output(self):
        with (self.stages["laravel"] / "candidate.zip").open("ab") as handle:
            handle.write(b"tampered")
        with self.assertRaises(ValueError):
            self.preview()
        self.assertFalse((self.root / "preview").exists())

    def test_rejects_mismatched_source_revision(self):
        evidence_path = self.stages["browser-runtime"] / "artifact-evidence.json"
        payload = json.loads(evidence_path.read_text())
        payload["sourceRevision"] = "b" * 40
        evidence_path.write_text(json.dumps(payload))
        with self.assertRaises(ValueError):
            self.preview()
        self.assertFalse((self.root / "preview").exists())

    def test_rejects_archive_content_not_matching_content_manifest(self):
        stage = self.stages["laravel"]
        archive = stage / "candidate.zip"
        with zipfile.ZipFile(archive, "a") as handle:
            handle.writestr("extra-secret.txt", "excluded")
        evidence_path = stage / "artifact-evidence.json"
        payload = json.loads(evidence_path.read_text())
        payload.update(archiveSha256=hashlib.sha256(archive.read_bytes()).hexdigest(), archiveSize=archive.stat().st_size)
        evidence_path.write_text(json.dumps(payload))
        with self.assertRaises(ValueError):
            self.preview()
        self.assertFalse((self.root / "preview").exists())

    def test_preserves_nonempty_destination(self):
        output = self.root / "preview"
        output.mkdir()
        (output / "sentinel").write_text("keep")
        with self.assertRaises(ValueError):
            self.preview()
        self.assertEqual((output / "sentinel").read_text(), "keep")

    def test_rejects_destination_inside_candidate(self):
        with self.assertRaises(ValueError):
            self.module().create_publication_preview(candidate_root=self.root,
                preview_root=self.stages["laravel"] / "proposal", version=VERSION, revision=REVISION)

    def test_dry_run_failure_does_not_create_success_evidence(self):
        from unittest.mock import patch
        self.preview()
        with patch("scripts.release_publication_preview._run_preview_command", side_effect=RuntimeError("failed")):
            with self.assertRaises(RuntimeError):
                self.module().dry_run_preview(self.root / "preview")
        self.assertFalse((self.root / "preview/dry-run-evidence.json").exists())

    def test_dry_run_rejects_changed_preview(self):
        self.preview()
        (self.root / "preview/browser-runtime/dist/index.js").write_text("changed")
        with self.assertRaises(ValueError):
            self.module().dry_run_preview(self.root / "preview")
        self.assertFalse((self.root / "preview/dry-run-evidence.json").exists())

    def test_dry_run_rejects_wrong_pack_inventory(self):
        from unittest.mock import patch
        import subprocess
        self.preview()
        with patch("scripts.release_publication_preview._run_preview_command",
                   return_value=subprocess.CompletedProcess([], 0, stdout='[]')):
            with self.assertRaises(ValueError):
                self.module().dry_run_preview(self.root / "preview")
        self.assertFalse((self.root / "preview/dry-run-evidence.json").exists())

    def test_preview_commands_exclude_ambient_composer_override_and_credentials(self):
        from unittest.mock import patch
        import os
        import subprocess
        with patch.dict(os.environ, {"COMPOSER": "/wrong/manifest.json", "COMPOSER_AUTH": "fake-sensitive-value"}), \
             patch("scripts.release_publication_preview.subprocess.run",
                   return_value=subprocess.CompletedProcess([], 0, stdout="")) as run:
            self.module()._run_preview_command(["composer", "--version"], cwd=self.root)
        environment = run.call_args.kwargs["env"]
        self.assertNotIn("COMPOSER", environment)
        self.assertNotIn("COMPOSER_AUTH", environment)
        self.assertNotEqual(environment["HOME"], os.environ.get("HOME"))
