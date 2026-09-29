import hashlib
import importlib
import json
from pathlib import Path
import tarfile
import tempfile
import unittest


PACKAGE_NAME = "@surfacerelay/browser-runtime"
VERSION = "0.0.0-t803.1"
REVISION = "b" * 40


def browser_candidate_module():
    return importlib.import_module("scripts.browser_release_candidate")


class BrowserReleaseCandidateArtifactContractTest(unittest.TestCase):
    def create_source_tree(self, root: Path) -> Path:
        repo = root / "repo"
        package = repo / "packages" / "browser-runtime"
        dist = package / "dist"

        dist.mkdir(parents=True)
        (package / "src").mkdir()
        (package / "tests").mkdir()
        (package / "conformance").mkdir()
        (package / "node_modules").mkdir()

        manifest = {
            "name": PACKAGE_NAME,
            "version": "0.0.0-dev",
            "private": True,
            "type": "module",
            "license": "Apache-2.0",
            "types": "./dist/index.d.ts",
            "exports": {
                ".": {
                    "types": "./dist/index.d.ts",
                    "import": "./dist/index.js",
                }
            },
            "files": ["dist", "README.md", "LICENSE"],
            "scripts": {
                "typecheck": "tsc --noEmit",
                "test": "vitest run",
                "build": "tsc -p tsconfig.build.json",
            },
        }
        (package / "package.json").write_text(
            json.dumps(manifest, indent=2) + "\n",
            encoding="utf-8",
        )

        (dist / "index.js").write_text(
            "export { DriverRegistry } from './driver-registry.js';\n",
            encoding="utf-8",
        )
        (dist / "index.d.ts").write_text(
            "export { DriverRegistry } from './driver-registry.js';\n",
            encoding="utf-8",
        )
        (dist / "driver-registry.js").write_text(
            "export class DriverRegistry {}\n",
            encoding="utf-8",
        )
        (dist / "driver-registry.d.ts").write_text(
            "export declare class DriverRegistry {}\n",
            encoding="utf-8",
        )
        (dist / "index.js.map").write_text(
            '{"version":3}\n',
            encoding="utf-8",
        )

        (package / "src" / "index.ts").write_text(
            "export const sourceOnly = true;\n",
            encoding="utf-8",
        )
        (package / "tests" / "example.test.ts").write_text(
            "export {};\n",
            encoding="utf-8",
        )
        (package / "conformance" / "fixture.ts").write_text(
            "export {};\n",
            encoding="utf-8",
        )
        (package / "node_modules" / "marker.txt").write_text(
            "repository-only dependency tree\n",
            encoding="utf-8",
        )
        (package / "package-lock.json").write_text(
            '{"name":"@surfacerelay/browser-runtime"}\n',
            encoding="utf-8",
        )
        (package / "tsconfig.json").write_text(
            '{"compilerOptions":{"target":"ES2022"}}\n',
            encoding="utf-8",
        )
        (package / "tsconfig.build.json").write_text(
            '{"extends":"./tsconfig.json"}\n',
            encoding="utf-8",
        )

        (repo / "LICENSE").write_text(
            "Apache License 2.0 test fixture\n",
            encoding="utf-8",
        )

        return repo

    def build_candidate(self, module, repo: Path, stage_root: Path):
        return module.build_browser_release_candidate(
            repo=repo,
            stage_root=stage_root,
            artifact_version=VERSION,
            source_revision=REVISION,
        )

    def test_candidate_identity_is_exact_package_version_and_revision(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")

        self.assertEqual(PACKAGE_NAME, candidate["packageName"])
        self.assertEqual(VERSION, candidate["artifactVersion"])
        self.assertEqual(REVISION, candidate["sourceRevision"])

    def test_staged_manifest_gets_exact_candidate_version_without_source_mutation(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)
            source_manifest = repo / "packages" / "browser-runtime" / "package.json"
            before = source_manifest.read_bytes()

            candidate = self.build_candidate(module, repo, root / "stage")
            staged = json.loads(
                Path(candidate["packageRoot"], "package.json").read_text(encoding="utf-8")
            )

            after = source_manifest.read_bytes()

        self.assertEqual(PACKAGE_NAME, staged["name"])
        self.assertEqual(VERSION, staged["version"])
        self.assertEqual(before, after)
        self.assertEqual("0.0.0-dev", json.loads(after)["version"])

    def test_source_and_staged_manifests_retain_private_publication_blocker(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            source = json.loads(
                (repo / "packages" / "browser-runtime" / "package.json").read_text(
                    encoding="utf-8"
                )
            )
            staged = json.loads(
                Path(candidate["packageRoot"], "package.json").read_text(encoding="utf-8")
            )

        self.assertIs(True, source["private"])
        self.assertIs(True, staged["private"])

    def test_staged_package_contains_only_release_allowlist(self):
        module = browser_candidate_module()
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
                "dist/driver-registry.d.ts",
                "dist/driver-registry.js",
                "dist/index.d.ts",
                "dist/index.js",
                "package.json",
            ],
            files,
        )

    def test_repository_sources_tests_configs_lockfiles_dependencies_and_maps_are_excluded(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            package_root = Path(candidate["packageRoot"])

        for forbidden in (
            "src",
            "tests",
            "conformance",
            "node_modules",
            "package-lock.json",
            "tsconfig.json",
            "tsconfig.build.json",
            "dist/index.js.map",
        ):
            with self.subTest(forbidden=forbidden):
                self.assertFalse((package_root / forbidden).exists())

    def test_symlink_inside_built_distribution_is_rejected(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)
            dist = repo / "packages" / "browser-runtime" / "dist"
            outside = root / "outside.js"
            outside.write_text("export {};\n", encoding="utf-8")
            link = dist / "linked.js"
            try:
                link.symlink_to(outside)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.BrowserReleaseCandidateError):
                self.build_candidate(module, repo, root / "stage")

    def test_staged_manifest_keeps_exact_root_only_exports_contract(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            staged = json.loads(
                Path(candidate["packageRoot"], "package.json").read_text(encoding="utf-8")
            )

        self.assertEqual("./dist/index.d.ts", staged["types"])
        self.assertEqual(
            {
                ".": {
                    "types": "./dist/index.d.ts",
                    "import": "./dist/index.js",
                }
            },
            staged["exports"],
        )
        self.assertNotIn("main", staged)

    def test_npm_tarball_entries_are_path_and_type_safe(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            archive_path = Path(candidate["archivePath"])

            with tarfile.open(archive_path, mode="r:gz") as archive:
                members = archive.getmembers()

        self.assertTrue(members)
        for member in members:
            with self.subTest(member=member.name):
                self.assertTrue(member.name.startswith("package/"))
                self.assertFalse(member.name.startswith("/"))
                self.assertNotIn("..", Path(member.name).parts)
                self.assertFalse(member.issym())
                self.assertFalse(member.islnk())
                self.assertFalse(member.isdev())

    def test_npm_pack_file_list_is_exact(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            archive_path = Path(candidate["archivePath"])

            with tarfile.open(archive_path, mode="r:gz") as archive:
                files = sorted(
                    member.name
                    for member in archive.getmembers()
                    if member.isfile()
                )

        self.assertEqual(
            [
                "package/LICENSE",
                "package/README.md",
                "package/dist/driver-registry.d.ts",
                "package/dist/driver-registry.js",
                "package/dist/index.d.ts",
                "package/dist/index.js",
                "package/package.json",
            ],
            files,
        )

    def test_t801_manifest_and_evidence_match_tarball_identity_and_hashes(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            repo = self.create_source_tree(root)

            candidate = self.build_candidate(module, repo, root / "stage")
            manifest_path = Path(candidate["contentManifestPath"])
            evidence_path = Path(candidate["artifactEvidencePath"])
            archive_path = Path(candidate["archivePath"])

            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            evidence = json.loads(evidence_path.read_text(encoding="utf-8"))
            manifest_sha256 = hashlib.sha256(manifest_path.read_bytes()).hexdigest()
            archive_sha256 = hashlib.sha256(archive_path.read_bytes()).hexdigest()
            archive_name = archive_path.name
            archive_size = archive_path.stat().st_size

        for payload in (manifest, evidence):
            self.assertEqual(PACKAGE_NAME, payload["packageName"])
            self.assertEqual(VERSION, payload["artifactVersion"])
            self.assertEqual(REVISION, payload["sourceRevision"])

        self.assertEqual(manifest_sha256, evidence["contentManifestSha256"])
        self.assertEqual(archive_sha256, evidence["archiveSha256"])
        self.assertEqual(archive_name, evidence["archiveFilename"])
        self.assertEqual(archive_size, evidence["archiveSize"])

    def test_generated_readme_is_candidate_only_and_not_public_release_guidance(self):
        module = browser_candidate_module()
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
        self.assertNotIn("npm install", readme.lower())
        self.assertNotIn("npmjs", readme.lower())


if __name__ == "__main__":
    unittest.main()
