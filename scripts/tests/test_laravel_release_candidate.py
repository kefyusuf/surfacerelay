import hashlib
import importlib
import json
from pathlib import Path
import tempfile
import unittest
import zipfile


PACKAGE_NAME = "surfacerelay/laravel"
VERSION = "0.0.0-t802.1"
REVISION = "a" * 40


def laravel_candidate_module():
    return importlib.import_module("scripts.laravel_release_candidate")


class LaravelReleaseCandidateArtifactContractTest(unittest.TestCase):
    def create_source_tree(self, root: Path) -> Path:
        repo = root / "repo"
        package = repo / "packages" / "laravel"

        (package / "src" / "Runtime").mkdir(parents=True)
        (package / "database" / "migrations").mkdir(parents=True)
        (package / "tests").mkdir(parents=True)
        (package / "vendor").mkdir(parents=True)

        (package / "composer.json").write_text(
            json.dumps(
                {
                    "name": PACKAGE_NAME,
                    "description": "SurfaceRelay Laravel package.",
                    "type": "library",
                    "license": "Apache-2.0",
                    "require": {
                        "php": "^8.3",
                        "illuminate/contracts": "^12.0|^13.0",
                    },
                    "autoload": {
                        "psr-4": {
                            "SurfaceRelay\\\\Laravel\\\\": "src/"
                        }
                    },
                },
                indent=2,
            )
            + "\n",
            encoding="utf-8",
        )
        (package / "src" / "Runtime" / "Example.php").write_text(
            "<?php\nnamespace SurfaceRelay\\Laravel\\Runtime;\n",
            encoding="utf-8",
        )
        (package / "database" / "migrations" / "0001_example.php").write_text(
            "<?php\nreturn null;\n",
            encoding="utf-8",
        )
        (package / "tests" / "ExampleTest.php").write_text(
            "<?php\n// repository-only test\n",
            encoding="utf-8",
        )
        (package / "phpunit.xml").write_text(
            "<phpunit/>\n",
            encoding="utf-8",
        )
        (package / "vendor" / "autoload.php").write_text(
            "<?php\n// repository-only vendor file\n",
            encoding="utf-8",
        )
        (repo / "LICENSE").write_text(
            "Apache License 2.0 test fixture\n",
            encoding="utf-8",
        )

        return repo

    def build_candidate(self, module, repo: Path, stage_root: Path):
        return module.build_laravel_release_candidate(
            repo=repo,
            stage_root=stage_root,
            artifact_version=VERSION,
            source_revision=REVISION,
        )

    def test_candidate_identity_is_exact_package_version_and_revision(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")

        self.assertEqual(PACKAGE_NAME, candidate["packageName"])
        self.assertEqual(VERSION, candidate["artifactVersion"])
        self.assertEqual(REVISION, candidate["sourceRevision"])

    def test_staged_composer_manifest_gets_exact_candidate_version(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            staged = json.loads(
                Path(candidate["packageRoot"], "composer.json").read_text(encoding="utf-8")
            )

        self.assertEqual(PACKAGE_NAME, staged["name"])
        self.assertEqual(VERSION, staged["version"])

    def test_build_does_not_mutate_source_composer_manifest(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)
            source_manifest = repo / "packages" / "laravel" / "composer.json"
            before = source_manifest.read_bytes()

            self.build_candidate(module, repo, root / "stage")

            after = source_manifest.read_bytes()

        self.assertEqual(before, after)
        self.assertNotIn(b'"version"', after)

    def test_staged_package_contains_only_release_allowlist(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            package_root = Path(candidate["packageRoot"])
            files = sorted(
                path.relative_to(package_root).as_posix()
                for path in package_root.rglob("*")
                if path.is_file()
            )

        self.assertEqual(
            [
                "LICENSE",
                "README.md",
                "composer.json",
                "database/migrations/0001_example.php",
                "src/Runtime/Example.php",
            ],
            files,
        )

    def test_repository_tests_phpunit_and_vendor_are_excluded(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            package_root = Path(candidate["packageRoot"])

        self.assertFalse((package_root / "tests").exists())
        self.assertFalse((package_root / "phpunit.xml").exists())
        self.assertFalse((package_root / "vendor").exists())

    def test_symlink_inside_release_source_is_rejected(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)
            source = repo / "packages" / "laravel" / "src"
            outside = root / "outside.php"
            outside.write_text("<?php\n", encoding="utf-8")
            link = source / "linked.php"
            try:
                link.symlink_to(outside)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.LaravelReleaseCandidateError):
                self.build_candidate(module, repo, root / "stage")

    def test_generated_readme_is_candidate_only_and_not_public_release_guidance(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            readme = Path(candidate["packageRoot"], "README.md").read_text(
                encoding="utf-8"
            )

        self.assertIn(PACKAGE_NAME, readme)
        self.assertIn(VERSION, readme)
        self.assertIn(REVISION, readme)
        self.assertIn("not a public release", readme.lower())
        self.assertNotIn("composer require", readme.lower())
        self.assertNotIn("packagist", readme.lower())

    def test_zip_root_entries_are_safe_and_lexically_ordered(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            archive = Path(candidate["archivePath"])

            with zipfile.ZipFile(archive) as handle:
                names = handle.namelist()

        self.assertEqual(sorted(names), names)
        self.assertIn("composer.json", names)
        self.assertFalse(any(name.startswith("/") for name in names))
        self.assertFalse(any(".." in Path(name).parts for name in names))
        self.assertFalse(any(name.startswith("surfacerelay-laravel/") for name in names))

    def test_repeated_same_input_build_produces_byte_identical_zip(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            first = self.build_candidate(module, repo, root / "stage-a")
            second = self.build_candidate(module, repo, root / "stage-b")

            first_bytes = Path(first["archivePath"]).read_bytes()
            second_bytes = Path(second["archivePath"]).read_bytes()

        self.assertEqual(first_bytes, second_bytes)

    def test_t801_manifest_and_evidence_match_archive_identity_and_hashes(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            manifest_path = Path(candidate["contentManifestPath"])
            evidence_path = Path(candidate["artifactEvidencePath"])
            archive_path = Path(candidate["archivePath"])

            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            evidence = json.loads(evidence_path.read_text(encoding="utf-8"))

        for payload in (manifest, evidence):
            self.assertEqual(PACKAGE_NAME, payload["packageName"])
            self.assertEqual(VERSION, payload["artifactVersion"])
            self.assertEqual(REVISION, payload["sourceRevision"])

        self.assertEqual(
            hashlib.sha256(manifest_path.read_bytes()).hexdigest(),
            evidence["contentManifestSha256"],
        )
        self.assertEqual(
            hashlib.sha256(archive_path.read_bytes()).hexdigest(),
            evidence["archiveSha256"],
        )
        self.assertEqual(archive_path.name, evidence["archiveFilename"])
        self.assertEqual(archive_path.stat().st_size, evidence["archiveSize"])


class LaravelCleanConsumerIsolationContractTest(unittest.TestCase):
    def test_generated_consumer_uses_artifact_repository_only(self):
        module = laravel_candidate_module()
        manifest = module.build_clean_consumer_composer_manifest(
            artifact_directory=Path("/tmp/artifacts"),
            artifact_version=VERSION,
            laravel_constraint="^13.0",
        )

        repositories = manifest["repositories"]
        self.assertEqual(
            [{"type": "artifact", "url": "/tmp/artifacts"}],
            repositories,
        )

    def test_generated_consumer_requires_exact_candidate_version(self):
        module = laravel_candidate_module()
        manifest = module.build_clean_consumer_composer_manifest(
            artifact_directory=Path("/tmp/artifacts"),
            artifact_version=VERSION,
            laravel_constraint="^13.0",
        )

        self.assertEqual(VERSION, manifest["require"][PACKAGE_NAME])

    def test_generated_consumer_requires_selected_laravel_major(self):
        module = laravel_candidate_module()

        laravel12 = module.build_clean_consumer_composer_manifest(
            artifact_directory=Path("/tmp/artifacts"),
            artifact_version=VERSION,
            laravel_constraint="^12.0",
        )
        laravel13 = module.build_clean_consumer_composer_manifest(
            artifact_directory=Path("/tmp/artifacts"),
            artifact_version=VERSION,
            laravel_constraint="^13.0",
        )

        self.assertEqual("^12.0", laravel12["require"]["laravel/framework"])
        self.assertEqual("^13.0", laravel13["require"]["laravel/framework"])

    def test_forbidden_consumer_source_coupling_is_rejected(self):
        module = laravel_candidate_module()
        forbidden_manifests = [
            {
                "repositories": [
                    {"type": "path", "url": "../../packages/laravel"}
                ],
                "require": {PACKAGE_NAME: VERSION},
            },
            {
                "repositories": [
                    {"type": "artifact", "url": "file:../../packages/laravel"}
                ],
                "require": {PACKAGE_NAME: VERSION},
            },
            {
                "repositories": [
                    {"type": "artifact", "url": "workspace:packages/laravel"}
                ],
                "require": {PACKAGE_NAME: VERSION},
            },
            {
                "repositories": [
                    {"type": "artifact", "url": "../packages/laravel"}
                ],
                "require": {PACKAGE_NAME: VERSION},
            },
            {
                "repositories": [
                    {"type": "artifact", "url": "/tmp/artifacts"}
                ],
                "require": {PACKAGE_NAME: "dev-main"},
            },
        ]

        for manifest in forbidden_manifests:
            with self.subTest(manifest=manifest):
                with self.assertRaises(module.LaravelReleaseCandidateError):
                    module.validate_clean_consumer_manifest(manifest)

    def test_consumer_symlink_back_into_package_source_is_rejected(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package_source = root / "repo" / "packages" / "laravel"
            package_source.mkdir(parents=True)
            consumer = root / "consumer"
            consumer.mkdir()
            link = consumer / "linked-source"
            try:
                link.symlink_to(package_source, target_is_directory=True)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.LaravelReleaseCandidateError):
                module.validate_clean_consumer_isolation(
                    consumer_root=consumer,
                    package_source_root=package_source,
                )

    def test_missing_or_wrong_artifact_archive_identity_is_rejected(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            missing = root / "missing.zip"

            with self.assertRaises(module.LaravelReleaseCandidateError):
                module.validate_laravel_artifact_archive(
                    archive_path=missing,
                    artifact_version=VERSION,
                )

            wrong = root / "wrong.zip"
            with zipfile.ZipFile(wrong, "w") as archive:
                archive.writestr(
                    "composer.json",
                    json.dumps(
                        {
                            "name": "other/package",
                            "version": VERSION,
                        }
                    ),
                )

            with self.assertRaises(module.LaravelReleaseCandidateError):
                module.validate_laravel_artifact_archive(
                    archive_path=wrong,
                    artifact_version=VERSION,
                )

    def test_consumer_directory_must_not_be_package_source_tree(self):
        module = laravel_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package_source = Path(directory) / "packages" / "laravel"
            package_source.mkdir(parents=True)

            with self.assertRaises(module.LaravelReleaseCandidateError):
                module.validate_clean_consumer_isolation(
                    consumer_root=package_source,
                    package_source_root=package_source,
                )


if __name__ == "__main__":
    unittest.main()
