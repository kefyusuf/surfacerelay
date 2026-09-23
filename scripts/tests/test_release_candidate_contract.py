import hashlib
import importlib
import json
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch


VALID_REVISION = "a" * 40


def release_candidate_module():
    return importlib.import_module("scripts.release_candidate")


def git(repo: Path, *args: str) -> str:
    completed = subprocess.run(
        ["git", "-C", str(repo), *args],
        check=True,
        capture_output=True,
        text=True,
    )
    return completed.stdout.strip()


def create_clean_repository() -> tuple[tempfile.TemporaryDirectory, Path, str]:
    temporary = tempfile.TemporaryDirectory()
    repo = Path(temporary.name)
    git(repo, "init", "-q")
    git(repo, "config", "user.email", "surfacerelay-tests@example.invalid")
    git(repo, "config", "user.name", "SurfaceRelay Tests")
    (repo / "tracked.txt").write_text("baseline\n", encoding="utf-8")
    git(repo, "add", "tracked.txt")
    git(repo, "commit", "-q", "-m", "baseline")
    return temporary, repo, git(repo, "rev-parse", "HEAD")


class ReleaseCandidateIdentityContractTest(unittest.TestCase):
    def test_valid_prerelease_semver_is_accepted(self):
        module = release_candidate_module()

        self.assertEqual("0.0.0-m8.1", module.validate_artifact_version("0.0.0-m8.1"))
        self.assertEqual("0.4.0-rc.1", module.validate_artifact_version("0.4.0-rc.1"))

    def test_stable_semver_is_rejected(self):
        module = release_candidate_module()

        with self.assertRaises(module.ReleaseCandidateContractError):
            module.validate_artifact_version("0.1.0")

    def test_semver_build_metadata_is_rejected(self):
        module = release_candidate_module()

        with self.assertRaises(module.ReleaseCandidateContractError):
            module.validate_artifact_version("0.0.0-m8.1+local")

    def test_malformed_or_leading_zero_semver_is_rejected(self):
        module = release_candidate_module()

        for value in ("01.0.0-m8.1", "0.01.0-m8.1", "0.0.01-m8.1", "0.0.0-"):
            with self.subTest(value=value):
                with self.assertRaises(module.ReleaseCandidateContractError):
                    module.validate_artifact_version(value)

    def test_full_lowercase_revision_is_accepted(self):
        module = release_candidate_module()

        self.assertEqual(VALID_REVISION, module.validate_source_revision(VALID_REVISION))

    def test_short_uppercase_or_non_hex_revision_is_rejected(self):
        module = release_candidate_module()

        for value in ("a" * 39, "A" * 40, "g" * 40, "main"):
            with self.subTest(value=value):
                with self.assertRaises(module.ReleaseCandidateContractError):
                    module.validate_source_revision(value)

    def test_expected_revision_mismatch_with_head_is_rejected(self):
        module = release_candidate_module()
        temporary, repo, head = create_clean_repository()
        self.addCleanup(temporary.cleanup)
        mismatch = ("b" if head[0] != "b" else "c") + head[1:]

        with self.assertRaises(module.ReleaseCandidateContractError):
            module.preflight_repository(repo, mismatch)

    def test_dirty_tracked_file_is_rejected(self):
        module = release_candidate_module()
        temporary, repo, head = create_clean_repository()
        self.addCleanup(temporary.cleanup)
        (repo / "tracked.txt").write_text("changed\n", encoding="utf-8")

        with self.assertRaises(module.ReleaseCandidateContractError):
            module.preflight_repository(repo, head)

    def test_dirty_untracked_file_is_rejected(self):
        module = release_candidate_module()
        temporary, repo, head = create_clean_repository()
        self.addCleanup(temporary.cleanup)
        (repo / "untracked.txt").write_text("dirty\n", encoding="utf-8")

        with self.assertRaises(module.ReleaseCandidateContractError):
            module.preflight_repository(repo, head)

    def test_clean_temporary_repository_is_accepted(self):
        module = release_candidate_module()
        temporary, repo, head = create_clean_repository()
        self.addCleanup(temporary.cleanup)

        self.assertEqual(head, module.preflight_repository(repo, head))

    def test_stage_root_path_escape_is_rejected(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            repo = Path(directory)
            stage_root = module.release_candidate_root(repo, "0.0.0-m8.1", VALID_REVISION)

            with self.assertRaises(module.ReleaseCandidateContractError):
                module.resolve_staging_path(stage_root, "../escape.json")

    def test_existing_non_empty_evidence_target_is_rejected(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory) / "evidence"
            target.mkdir()
            (target / "existing.json").write_text("{}\n", encoding="utf-8")

            with self.assertRaises(module.ReleaseCandidateContractError):
                module.ensure_empty_target(target)


class ReleaseCandidateContentEvidenceContractTest(unittest.TestCase):
    PACKAGE_NAME = "example/package"
    VERSION = "0.0.0-m8.1"
    REVISION = VALID_REVISION

    def create_package_tree(self, root: Path) -> Path:
        package = root / "package"
        (package / "nested").mkdir(parents=True)
        (package / "z-last.txt").write_bytes(b"z-last\n")
        (package / "a-first.txt").write_bytes(b"a-first\n")
        (package / "nested" / "middle.bin").write_bytes(b"middle-bytes")
        return package

    def build_manifest(self, module, package: Path):
        return module.build_content_manifest(
            package,
            self.PACKAGE_NAME,
            self.VERSION,
            self.REVISION,
        )

    def test_regular_nested_files_generate_lexically_sorted_manifest_entries(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package = self.create_package_tree(Path(directory))

            manifest = self.build_manifest(module, package)

        self.assertEqual(
            ["a-first.txt", "nested/middle.bin", "z-last.txt"],
            [entry["path"] for entry in manifest["files"]],
        )

    def test_manifest_records_exact_size_and_sha256(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package = Path(directory) / "package"
            package.mkdir()
            payload = b"exact-bytes\x00\xff"
            (package / "payload.bin").write_bytes(payload)

            manifest = self.build_manifest(module, package)

        self.assertEqual(
            [{
                "path": "payload.bin",
                "size": len(payload),
                "sha256": hashlib.sha256(payload).hexdigest(),
            }],
            manifest["files"],
        )

    def test_repeated_manifest_serialization_is_byte_identical(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package = self.create_package_tree(Path(directory))

            first = module.serialize_evidence_json(self.build_manifest(module, package))
            second = module.serialize_evidence_json(self.build_manifest(module, package))

        self.assertEqual(first, second)

    def test_symlinked_file_is_rejected(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package = root / "package"
            package.mkdir()
            target = root / "outside.txt"
            target.write_text("outside\n", encoding="utf-8")
            try:
                (package / "linked.txt").symlink_to(target)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.ReleaseCandidateContractError):
                self.build_manifest(module, package)

    def test_symlinked_directory_is_rejected(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package = root / "package"
            package.mkdir()
            target = root / "outside"
            target.mkdir()
            (target / "payload.txt").write_text("outside\n", encoding="utf-8")
            try:
                (package / "linked-dir").symlink_to(target, target_is_directory=True)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.ReleaseCandidateContractError):
                self.build_manifest(module, package)

    def test_empty_package_tree_is_rejected(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package = Path(directory) / "empty"
            package.mkdir()

            with self.assertRaises(module.ReleaseCandidateContractError):
                self.build_manifest(module, package)

    def test_manifest_output_excludes_absolute_and_environment_noise(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package = self.create_package_tree(root)
            with patch.dict(
                os.environ,
                {
                    "SURFACERELAY_TEST_HOSTNAME": "machine-specific-host",
                    "SURFACERELAY_TEST_USERNAME": "machine-specific-user",
                },
            ):
                encoded = module.serialize_evidence_json(self.build_manifest(module, package))

        decoded = json.loads(encoded)
        self.assertEqual(
            {
                "schemaVersion",
                "packageName",
                "artifactVersion",
                "sourceRevision",
                "files",
            },
            set(decoded),
        )
        self.assertNotIn(str(root).encode(), encoded)
        self.assertNotIn(b"machine-specific-host", encoded)
        self.assertNotIn(b"machine-specific-user", encoded)
        self.assertNotIn(b"timestamp", encoded.lower())
        self.assertNotIn(b"hostname", encoded.lower())
        self.assertNotIn(b"username", encoded.lower())

    def test_artifact_evidence_records_exact_manifest_and_archive_hashes(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            stage_root = module.release_candidate_root(root, self.VERSION, self.REVISION)
            stage_root.mkdir(parents=True)
            manifest_bytes = b'{"schemaVersion":1}\n'
            archive_bytes = b"archive-bytes\x00"
            manifest_path = stage_root / "content-manifest.json"
            archive_path = stage_root / "package.tgz"
            manifest_path.write_bytes(manifest_bytes)
            archive_path.write_bytes(archive_bytes)

            evidence = module.build_artifact_evidence(
                stage_root=stage_root,
                content_manifest_path=manifest_path,
                archive_path=archive_path,
                package_name=self.PACKAGE_NAME,
                artifact_version=self.VERSION,
                source_revision=self.REVISION,
            )

        self.assertEqual(hashlib.sha256(manifest_bytes).hexdigest(), evidence["contentManifestSha256"])
        self.assertEqual(hashlib.sha256(archive_bytes).hexdigest(), evidence["archiveSha256"])
        self.assertEqual(len(archive_bytes), evidence["archiveSize"])

    def test_artifact_evidence_rejects_missing_or_directory_archive(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            stage_root = module.release_candidate_root(root, self.VERSION, self.REVISION)
            stage_root.mkdir(parents=True)
            manifest_path = stage_root / "content-manifest.json"
            manifest_path.write_text("{}\n", encoding="utf-8")
            missing = stage_root / "missing.tgz"
            archive_directory = stage_root / "archive-dir"
            archive_directory.mkdir()

            for archive_path in (missing, archive_directory):
                with self.subTest(archive_path=archive_path):
                    with self.assertRaises(module.ReleaseCandidateContractError):
                        module.build_artifact_evidence(
                            stage_root=stage_root,
                            content_manifest_path=manifest_path,
                            archive_path=archive_path,
                            package_name=self.PACKAGE_NAME,
                            artifact_version=self.VERSION,
                            source_revision=self.REVISION,
                        )

    def test_artifact_evidence_preserves_exact_identity(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            stage_root = module.release_candidate_root(root, self.VERSION, self.REVISION)
            stage_root.mkdir(parents=True)
            manifest_path = stage_root / "content-manifest.json"
            archive_path = stage_root / "package.tgz"
            manifest_path.write_text("{}\n", encoding="utf-8")
            archive_path.write_bytes(b"archive")

            evidence = module.build_artifact_evidence(
                stage_root=stage_root,
                content_manifest_path=manifest_path,
                archive_path=archive_path,
                package_name=self.PACKAGE_NAME,
                artifact_version=self.VERSION,
                source_revision=self.REVISION,
            )

        self.assertEqual(self.PACKAGE_NAME, evidence["packageName"])
        self.assertEqual(self.VERSION, evidence["artifactVersion"])
        self.assertEqual(self.REVISION, evidence["sourceRevision"])
        self.assertEqual("package.tgz", evidence["archiveFilename"])

    def test_json_serialization_is_sorted_compact_and_newline_terminated(self):
        module = release_candidate_module()
        payload = {
            "z": 1,
            "a": {"z": 2, "a": 1},
        }

        encoded = module.serialize_evidence_json(payload)

        self.assertEqual(b'{"a":{"a":1,"z":2},"z":1}\n', encoded)
        self.assertTrue(encoded.endswith(b"\n"))

    def test_manifest_identity_is_preserved_exactly(self):
        module = release_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package = Path(directory) / "package"
            package.mkdir()
            (package / "payload.txt").write_text("payload\n", encoding="utf-8")

            manifest = self.build_manifest(module, package)

        self.assertEqual(1, manifest["schemaVersion"])
        self.assertEqual(self.PACKAGE_NAME, manifest["packageName"])
        self.assertEqual(self.VERSION, manifest["artifactVersion"])
        self.assertEqual(self.REVISION, manifest["sourceRevision"])


if __name__ == "__main__":
    unittest.main()
