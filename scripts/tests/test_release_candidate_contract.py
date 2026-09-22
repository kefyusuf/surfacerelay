import importlib
from pathlib import Path
import subprocess
import tempfile
import unittest


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


if __name__ == "__main__":
    unittest.main()
