import hashlib
import importlib
import json
from pathlib import Path
import subprocess
import tempfile
import unittest


VERSION = "0.0.0-alpha1"
LARAVEL = "surfacerelay/laravel"
BROWSER = "@surfacerelay/browser-runtime"


def readiness_module():
    return importlib.import_module("scripts.release_readiness")


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
    repo = Path(temporary.name) / "repo"
    repo.mkdir()
    git(repo, "init", "-q")
    git(repo, "config", "user.email", "surfacerelay-tests@example.invalid")
    git(repo, "config", "user.name", "SurfaceRelay Tests")
    (repo / "tracked.txt").write_text("baseline\n", encoding="utf-8")
    git(repo, "add", "tracked.txt")
    git(repo, "commit", "-q", "-m", "baseline")
    return temporary, repo, git(repo, "rev-parse", "HEAD")


class FakeBuilder:
    """Stands in for a real builder; produces evidence with the real helpers."""

    def __init__(self, package_name: str, *, report_name=None, report_revision=None, tamper=False):
        self.package_name = package_name
        self.report_name = report_name or package_name
        self.report_revision = report_revision
        self.tamper = tamper
        self.calls: list[dict[str, str]] = []

    def __call__(self, *, repo, stage_root, artifact_version, source_revision):
        rc = release_candidate_module()
        self.calls.append({
            "repo": str(repo),
            "stage_root": str(stage_root),
            "artifact_version": artifact_version,
            "source_revision": source_revision,
        })
        revision = self.report_revision or source_revision
        stage = Path(stage_root)
        package = stage / "package"
        package.mkdir(parents=True)
        (package / "README.md").write_text(self.package_name, encoding="utf-8")
        manifest = stage / "content-manifest.json"
        manifest.write_bytes(rc.serialize_evidence_json(
            rc.build_content_manifest(package, self.report_name, artifact_version, revision)
        ))
        archive = stage / "candidate.archive"
        archive.write_bytes(b"archive:" + self.package_name.encode())
        evidence = rc.build_artifact_evidence(
            stage_root=stage,
            content_manifest_path=manifest,
            archive_path=archive,
            package_name=self.report_name,
            artifact_version=artifact_version,
            source_revision=revision,
        )
        evidence_path = stage / "artifact-evidence.json"
        evidence_path.write_bytes(rc.serialize_evidence_json(evidence))
        if self.tamper:
            archive.write_bytes(b"tampered after evidence")
        return {
            "packageName": self.report_name,
            "artifactVersion": artifact_version,
            "sourceRevision": revision,
            "packageRoot": str(package),
            "contentManifestPath": str(manifest),
            "archivePath": str(archive),
            "artifactEvidencePath": str(evidence_path),
        }


class ReleaseReadinessTest(unittest.TestCase):
    def setUp(self):
        self.temporary, self.repo, self.revision = create_clean_repository()
        self.addCleanup(self.temporary.cleanup)
        self.stage = Path(self.temporary.name) / "stage"

    def run_readiness(self, laravel=None, browser=None, **kwargs):
        return readiness_module().build_release_readiness(
            repo=self.repo,
            stage_root=self.stage,
            artifact_version=VERSION,
            source_revision=self.revision,
            laravel_builder=laravel or FakeBuilder(LARAVEL),
            browser_builder=browser or FakeBuilder(BROWSER),
            **kwargs,
        )

    def test_builds_both_candidates_from_one_clean_revision_and_version(self):
        laravel, browser = FakeBuilder(LARAVEL), FakeBuilder(BROWSER)

        result = self.run_readiness(laravel, browser)

        self.assertEqual(1, len(laravel.calls))
        self.assertEqual(1, len(browser.calls))
        for call in (laravel.calls[0], browser.calls[0]):
            self.assertEqual(VERSION, call["artifact_version"])
            self.assertEqual(self.revision, call["source_revision"])
        self.assertEqual(str(self.stage / "laravel"), laravel.calls[0]["stage_root"])
        self.assertEqual(str(self.stage / "browser-runtime"), browser.calls[0]["stage_root"])

        evidence = json.loads((self.stage / "readiness-evidence.json").read_text(encoding="utf-8"))
        self.assertEqual(evidence, result)
        self.assertEqual(VERSION, evidence["artifactVersion"])
        self.assertEqual(self.revision, evidence["sourceRevision"])
        self.assertEqual([LARAVEL, BROWSER], [p["packageName"] for p in evidence["packages"]])
        for package, stage_name in zip(evidence["packages"], ("laravel", "browser-runtime")):
            archive = self.stage / stage_name / package["archiveFilename"]
            self.assertEqual(hashlib.sha256(archive.read_bytes()).hexdigest(), package["archiveSha256"])
            self.assertEqual(f"{stage_name}/{package['archiveFilename']}", package["archivePath"])

    def test_dirty_tree_is_rejected_before_any_build(self):
        (self.repo / "untracked.txt").write_text("x", encoding="utf-8")
        laravel, browser = FakeBuilder(LARAVEL), FakeBuilder(BROWSER)

        with self.assertRaises(readiness_module().ReleaseReadinessError):
            self.run_readiness(laravel, browser)

        self.assertEqual([], laravel.calls + browser.calls)
        self.assertFalse(self.stage.exists())

    def test_revision_mismatch_is_rejected_before_any_build(self):
        laravel = FakeBuilder(LARAVEL)

        with self.assertRaises(readiness_module().ReleaseReadinessError):
            readiness_module().build_release_readiness(
                repo=self.repo,
                stage_root=self.stage,
                artifact_version=VERSION,
                source_revision="b" * 40,
                laravel_builder=laravel,
                browser_builder=FakeBuilder(BROWSER),
            )

        self.assertEqual([], laravel.calls)

    def test_non_empty_stage_root_is_rejected(self):
        self.stage.mkdir()
        (self.stage / "previous-evidence.json").write_text("{}", encoding="utf-8")

        with self.assertRaises(readiness_module().ReleaseReadinessError):
            self.run_readiness()

    def test_stable_version_is_rejected(self):
        with self.assertRaises(readiness_module().ReleaseReadinessError):
            readiness_module().build_release_readiness(
                repo=self.repo,
                stage_root=self.stage,
                artifact_version="0.1.0",
                source_revision=self.revision,
                laravel_builder=FakeBuilder(LARAVEL),
                browser_builder=FakeBuilder(BROWSER),
            )

    def test_candidate_from_another_revision_fails_without_readiness_evidence(self):
        with self.assertRaises(readiness_module().ReleaseReadinessError):
            self.run_readiness(browser=FakeBuilder(BROWSER, report_revision="c" * 40))

        self.assertFalse((self.stage / "readiness-evidence.json").exists())

    def test_unexpected_package_identity_fails(self):
        with self.assertRaises(readiness_module().ReleaseReadinessError):
            self.run_readiness(laravel=FakeBuilder(LARAVEL, report_name=BROWSER))

    def test_archive_changed_after_evidence_fails(self):
        with self.assertRaises(readiness_module().ReleaseReadinessError):
            self.run_readiness(laravel=FakeBuilder(LARAVEL, tamper=True))

        self.assertFalse((self.stage / "readiness-evidence.json").exists())

    def test_publication_is_no_go_with_explicit_blockers(self):
        result = self.run_readiness()

        publication = result["publication"]
        self.assertEqual("NO-GO", publication["decision"])
        self.assertIn("private-vulnerability-reporting-unverified", publication["blockers"])
        self.assertIn("publication-not-authorized", publication["blockers"])
        self.assertIn("public-version-not-approved", publication["blockers"])

    def test_publication_stays_no_go_even_when_reporting_is_verified(self):
        result = self.run_readiness(private_vulnerability_reporting_verified=True)

        publication = result["publication"]
        self.assertEqual("NO-GO", publication["decision"])
        self.assertNotIn("private-vulnerability-reporting-unverified", publication["blockers"])
        self.assertIn("publication-not-authorized", publication["blockers"])


if __name__ == "__main__":
    unittest.main()
