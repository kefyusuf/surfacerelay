import importlib
import json
from pathlib import Path
import unittest
from unittest.mock import patch

from scripts.tests import test_release_publication_preview as fixtures
from scripts.release_candidate import build_content_manifest

REVISION, VERSION = fixtures.REVISION, fixtures.VERSION


class LaravelDistributionPreviewTest(unittest.TestCase):
    def setUp(self):
        # Reuse the real two-archive fixture, without inheriting unrelated tests.
        fixtures.PublicationPreviewTest.setUp(self)

    def prepare(self, output=None, revision=REVISION):
        module = importlib.import_module("scripts.laravel_distribution_preview")
        return module.prepare_distribution_tree(candidate_root=self.root,
            output_root=output or self.root / "mirror", version=VERSION, revision=revision)

    def test_reproducible_root_manifest_and_exact_source_mapping(self):
        first = self.prepare()
        self.assertIn("contentManifest", first)
        second = self.prepare(output=self.root / "another-mirror")
        self.assertEqual(first, second)
        tree = self.root / "mirror/package"
        manifest = json.loads((tree / "composer.json").read_text())
        self.assertEqual(manifest["name"], "surfacerelay/laravel")
        self.assertNotIn("version", manifest)
        self.assertNotIn("require-dev", manifest)
        self.assertEqual((tree / "src/Example.php").read_bytes(), b"fixture")
        self.assertEqual((tree / "LICENSE").read_bytes(), b"Apache-2.0")
        self.assertFalse((tree / ".git").exists())
        self.assertEqual(first["sourceRevision"], REVISION)
        self.assertEqual(first["publication"]["decision"], "NO-GO")
        self.assertIsNone(first["mirrorCommit"])
        self.assertIsNone(first["remoteRepository"])
        self.assertEqual(first["contentManifest"]["packageName"], manifest["name"])
        self.assertEqual(first["contentManifest"], build_content_manifest(tree,
            "surfacerelay/laravel", VERSION, REVISION))
        self.assertEqual(json.loads((self.root / "mirror/mapping-evidence.json").read_text()), first)

    def test_transformed_files_do_not_depend_on_host_text_newlines(self):
        original = Path.write_text

        def windows_text_write(path, data, *args, **kwargs):
            return original(path, data.replace("\n", "\r\n"), *args, **kwargs)

        with patch.object(Path, "write_text", windows_text_write):
            self.prepare()
        for name in ("composer.json", "README.md"):
            self.assertNotIn(b"\r\n", (self.root / "mirror/package" / name).read_bytes())

    def test_rejects_tampered_archive_before_output(self):
        with (self.root / "laravel/candidate.zip").open("ab") as handle:
            handle.write(b"tampered")
        with self.assertRaises(ValueError):
            self.prepare()
        self.assertFalse((self.root / "mirror").exists())

    def test_rejects_wrong_revision_before_output(self):
        with self.assertRaises(ValueError):
            self.prepare(revision="b" * 40)
        self.assertFalse((self.root / "mirror").exists())

    def test_preserves_existing_destination(self):
        target = self.root / "mirror"
        target.mkdir()
        (target / "sentinel").write_text("keep")
        with self.assertRaises(ValueError):
            self.prepare()
        self.assertEqual((target / "sentinel").read_text(), "keep")

    def test_rejects_output_overlapping_either_candidate(self):
        for kind in ("laravel", "browser-runtime"):
            with self.subTest(kind=kind), self.assertRaises(ValueError):
                self.prepare(output=self.root / kind / "mirror")


if __name__ == "__main__":
    unittest.main()
