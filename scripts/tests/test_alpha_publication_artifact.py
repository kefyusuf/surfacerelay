import hashlib
import importlib
import io
import json
from pathlib import Path
import shutil
import tarfile
import unittest
from unittest.mock import patch

from scripts.tests import test_release_publication_preview as fixtures

REVISION, VERSION = fixtures.REVISION, fixtures.VERSION


class AlphaPublicationArtifactTest(unittest.TestCase):
    def setUp(self):
        fixtures.PublicationPreviewTest.setUp(self)

    def module(self):
        return importlib.import_module("scripts.alpha_publication_artifact")

    def test_alpha3_installed_documentation_explains_filament_opt_in_and_retry_limits(self):
        version = "0.1.0-alpha.3"
        with patch.object(fixtures, "VERSION", version):
            fixtures.PublicationPreviewTest.setUp(self)
        record = self.build(version=version)
        files = self.module().validate_public_browser_archive(
            archive_path=self.root / "final" / record["browserArchive"]["filename"],
            version=version, revision=REVISION,
            expected_manifest=record["content"]["browser-runtime"],
            expected_sha256=record["browserArchive"]["sha256"])
        readme = files["README.md"].decode()
        for text in ("FilamentBrowserDriver", "FilamentSelectionCoordinator",
                     "GlobalFilamentSelectionRuntime", "same coordinator",
                     "exact binding objects", "Unknown outcomes", "automatic retry"):
            self.assertIn(text, readme)
        self.assertFalse(record["registryWrites"])

    def test_followup_alpha_preserves_version_and_does_not_claim_first_release(self):
        version = "0.1.0-alpha.2"
        with patch.object(fixtures, "VERSION", version):
            fixtures.PublicationPreviewTest.setUp(self)
        record = self.build(version=version)
        files = self.module().validate_public_browser_archive(
            archive_path=self.root / "final" / record["browserArchive"]["filename"],
            version=version, revision=REVISION,
            expected_manifest=record["content"]["browser-runtime"],
            expected_sha256=record["browserArchive"]["sha256"])
        self.assertEqual(json.loads(files["package.json"])["version"], version)
        self.assertFalse(record["registryWrites"])
        self.assertFalse(record["publicationAuthorizedByTool"])
        for kind in ("laravel", "browser-runtime"):
            readme = (self.root / "final" / kind / "README.md").read_text()
            self.assertIn(version, readme)
            self.assertNotIn("first alpha", readme.lower())

    def build(self, **overrides):
        arguments = dict(candidate_root=self.root, output_root=self.root / "final",
                         version=VERSION, revision=REVISION)
        arguments.update(overrides)
        return self.module().build_alpha_publication_artifacts(**arguments)

    def validate(self, record):
        return self.module().validate_public_browser_archive(
            archive_path=self.root / "final" / record["browserArchive"]["filename"],
            version=VERSION, revision=REVISION,
            expected_manifest=record["content"]["browser-runtime"],
            expected_sha256=record["browserArchive"]["sha256"])

    def rewrite_tar(self, record, path, data, *, duplicate=False):
        archive = self.root / "final" / record["browserArchive"]["filename"]
        with tarfile.open(archive, "r:gz") as handle:
            entries = [(item.name, handle.extractfile(item).read()) for item in handle if item.isfile()]
        with tarfile.open(archive, "w:gz") as handle:
            for name, original in entries:
                payload = data if name == "package/" + path else original
                item = tarfile.TarInfo(name)
                item.size = len(payload)
                handle.addfile(item, io.BytesIO(payload))
            if duplicate:
                item = tarfile.TarInfo("package/" + path)
                item.size = len(data)
                handle.addfile(item, io.BytesIO(data))
        record["browserArchive"]["sha256"] = hashlib.sha256(archive.read_bytes()).hexdigest()

    def test_builds_public_alpha_and_versionless_vcs_tree_without_changing_candidates(self):
        before = {path: path.read_bytes() for stage in self.stages.values()
                  for path in stage.rglob("*") if path.is_file()}
        record = self.build()
        npm = json.loads((self.root / "final/browser-runtime/package.json").read_text())
        laravel = json.loads((self.root / "final/laravel/composer.json").read_text())
        self.assertIs(npm["private"], False)
        self.assertEqual(npm["version"], VERSION)
        self.assertEqual(npm["publishConfig"], {"registry": "https://registry.npmjs.org/",
                                              "access": "public", "tag": "alpha"})
        self.assertNotIn("version", laravel)
        self.assertNotIn("scripts", laravel)
        self.assertNotIn("devDependencies", npm)
        for kind in ("laravel", "browser-runtime"):
            text = (self.root / "final" / kind / "README.md").read_text()
            self.assertIn("Experimental", text)
            self.assertIn(REVISION, text)
            self.assertNotIn("preview", text.lower())
        self.assertEqual(record["purpose"], "alpha-publication-artifacts")
        self.assertFalse(record["registryWrites"])
        self.assertEqual(self.validate(record)["dist/index.js"], b"fixture")
        self.assertEqual(before, {path: path.read_bytes() for path in before})
        evidence = json.loads((self.root / "final/publication-artifact-evidence.json").read_text())
        self.assertEqual(evidence, record)

    def test_rejects_candidate_tampering_before_output(self):
        with (self.stages["laravel"] / "candidate.zip").open("ab") as handle:
            handle.write(b"tamper")
        with self.assertRaises(ValueError):
            self.build()
        self.assertFalse((self.root / "final").exists())

    def test_rejects_wrong_source_and_version(self):
        for changes in ({"revision": "b" * 40}, {"version": "0.1.0-alpha.2"}):
            with self.subTest(changes=changes), self.assertRaises(ValueError):
                self.build(**changes)
        self.assertFalse((self.root / "final").exists())

    def test_preserves_existing_output_and_rejects_candidate_overlap(self):
        output = self.root / "final"
        output.mkdir()
        marker = output / "owner.txt"
        marker.write_text("preserve")
        with self.assertRaises(ValueError):
            self.build()
        self.assertEqual(marker.read_text(), "preserve")
        for target in (self.root, self.stages["laravel"] / "unused", self.root.parent):
            with self.subTest(target=target), self.assertRaises(ValueError):
                self.build(output_root=target)

    def test_rejects_wrong_public_metadata_and_preview_content(self):
        record = self.build()
        archive = self.root / "final" / record["browserArchive"]["filename"]
        original = archive.read_bytes()
        manifest = json.loads((self.root / "final/browser-runtime/package.json").read_text())
        for changes in ({"private": True}, {"version": "0.1.0-alpha.2"},
                        {"exports": {".": "./dist/index.js", "./internal": "./dist/index.js"}},
                        {"publishConfig": {"access": "restricted"}}, {"scripts": {"install": "execute"}}):
            with self.subTest(changes=changes):
                archive.write_bytes(original)
                self.rewrite_tar(record, "package.json", json.dumps({**manifest, **changes}).encode())
                with self.assertRaises(ValueError):
                    self.validate(record)
        archive.write_bytes(original)
        self.rewrite_tar(record, "README.md", b"Metadata preview; not a registry release.")
        with self.assertRaises(ValueError):
            self.validate(record)

    def test_rejects_archive_hash_content_and_duplicate_tampering(self):
        record = self.build()
        archive = self.root / "final" / record["browserArchive"]["filename"]
        original = archive.read_bytes()
        archive.write_bytes(original + b"tamper")
        with self.assertRaises(ValueError):
            self.validate(record)
        archive.write_bytes(original)
        self.rewrite_tar(record, "dist/index.js", b"changed runtime")
        with self.assertRaises(ValueError):
            self.validate(record)
        archive.write_bytes(original)
        self.rewrite_tar(record, "dist/index.js", b"fixture", duplicate=True)
        with self.assertRaises(ValueError):
            self.validate(record)

    def test_rejects_expected_manifest_with_wrong_source(self):
        record = self.build()
        record["content"]["browser-runtime"]["sourceRevision"] = "b" * 40
        with self.assertRaises(ValueError):
            self.validate(record)

    def test_pack_failure_does_not_write_final_output(self):
        with patch.object(self.module(), "_run_preview_command", side_effect=ValueError("pack failed")):
            with self.assertRaises(ValueError):
                self.build()
        self.assertFalse((self.root / "final").exists())

    def test_rejects_link_and_traversal_archive_entries(self):
        record = self.build()
        archive = self.root / "final" / record["browserArchive"]["filename"]
        original = archive.read_bytes()
        for unsafe_name, link in (("package/../outside.js", False), ("package/dist/link.js", True)):
            with self.subTest(name=unsafe_name):
                with tarfile.open(fileobj=io.BytesIO(original), mode="r:gz") as handle:
                    files = [(item.name, handle.extractfile(item).read()) for item in handle if item.isfile()]
                with tarfile.open(archive, "w:gz") as handle:
                    for name, data in files:
                        item = tarfile.TarInfo(name)
                        item.size = len(data)
                        handle.addfile(item, io.BytesIO(data))
                    unsafe = tarfile.TarInfo(unsafe_name)
                    if link:
                        unsafe.type, unsafe.linkname = tarfile.SYMTYPE, "/outside.js"
                    handle.addfile(unsafe)
                record["browserArchive"]["sha256"] = hashlib.sha256(archive.read_bytes()).hexdigest()
                with self.assertRaises(ValueError):
                    self.validate(record)

    def test_rejects_symlink_output_without_following_it(self):
        target = self.root / "owner-output"
        target.mkdir()
        marker = target / "owner.txt"
        marker.write_text("preserve")
        output = self.root / "final"
        try:
            output.symlink_to(target, target_is_directory=True)
        except OSError:
            self.skipTest("Host does not permit symlink creation")
        with self.assertRaises(ValueError):
            self.build()
        self.assertEqual(marker.read_text(), "preserve")

    def test_installed_public_package_must_match_archive_bytes_and_not_source(self):
        record = self.build()
        consumer = self.root / "consumer"
        installed = consumer / "node_modules/@surfacerelay/browser-runtime"
        installed.parent.mkdir(parents=True)
        shutil.copytree(self.root / "final/browser-runtime", installed)
        options = dict(consumer_root=consumer, version=VERSION, revision=REVISION,
                       expected_manifest=record["content"]["browser-runtime"],
                       package_source_root=self.stages["browser-runtime"] / "package")
        self.assertEqual(self.module().verify_public_browser_install(**options), installed.resolve())
        (installed / "dist/index.js").write_text("tampered")
        with self.assertRaises(ValueError):
            self.module().verify_public_browser_install(**options)
        with self.assertRaises(ValueError):
            self.module().verify_public_browser_install(**{**options, "package_source_root": installed})


if __name__ == "__main__":
    unittest.main()
