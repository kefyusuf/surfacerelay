import hashlib
import importlib
import json
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest
from unittest.mock import patch


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


class BrowserCleanConsumerIsolationContractTest(unittest.TestCase):
    def test_generated_consumer_manifest_contains_only_pinned_host_tools(self):
        module = browser_candidate_module()

        manifest = module.build_clean_consumer_npm_manifest()

        self.assertEqual(
            {
                "name": "surfacerelay-browser-clean-consumer",
                "private": True,
                "type": "module",
                "devDependencies": {
                    "typescript": "5.9.3",
                    "vite": "7.3.6",
                },
            },
            manifest,
        )
        self.assertNotIn(PACKAGE_NAME, manifest.get("dependencies", {}))
        self.assertNotIn(PACKAGE_NAME, manifest.get("devDependencies", {}))

    def test_consumer_manifest_and_source_reject_source_deep_workspace_coupling(self):
        module = browser_candidate_module()

        forbidden_manifests = [
            {
                "name": "consumer",
                "private": True,
                "type": "module",
                "dependencies": {
                    PACKAGE_NAME: "file:../../packages/browser-runtime",
                },
            },
            {
                "name": "consumer",
                "private": True,
                "type": "module",
                "dependencies": {
                    PACKAGE_NAME: "workspace:*",
                },
            },
            {
                "name": "consumer",
                "private": True,
                "type": "module",
                "dependencies": {
                    PACKAGE_NAME: "link:../../packages/browser-runtime",
                },
            },
        ]

        for manifest in forbidden_manifests:
            with self.subTest(manifest=manifest):
                with self.assertRaises(module.BrowserReleaseCandidateError):
                    module.validate_clean_consumer_manifest(manifest)

        forbidden_sources = [
            "import { DriverRegistry } from '@surfacerelay/browser-runtime/dist/driver-registry.js';\n",
            "import { DriverRegistry } from '../../packages/browser-runtime/src/index.js';\n",
        ]

        for source in forbidden_sources:
            with self.subTest(source=source):
                with self.assertRaises(module.BrowserReleaseCandidateError):
                    module.validate_clean_consumer_source(source)

    def test_consumer_symlink_back_into_package_source_is_rejected(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package_source = root / "repo" / "packages" / "browser-runtime"
            package_source.mkdir(parents=True)
            consumer = root / "consumer"
            consumer.mkdir()
            link = consumer / "linked-source"
            try:
                link.symlink_to(package_source, target_is_directory=True)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.BrowserReleaseCandidateError):
                module.validate_clean_consumer_isolation(
                    consumer_root=consumer,
                    package_source_root=package_source,
                )

    def test_consumer_directory_must_be_isolated_from_package_source_tree(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            package_source = Path(directory) / "packages" / "browser-runtime"
            package_source.mkdir(parents=True)

            with self.assertRaises(module.BrowserReleaseCandidateError):
                module.validate_clean_consumer_isolation(
                    consumer_root=package_source,
                    package_source_root=package_source,
                )

    def test_missing_or_wrong_tarball_identity_is_rejected(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            missing = root / "missing.tgz"

            with self.assertRaises(module.BrowserReleaseCandidateError):
                module.validate_browser_artifact_archive(
                    archive_path=missing,
                    artifact_version=VERSION,
                )

            wrong = root / "wrong.tgz"
            manifest_bytes = json.dumps(
                {
                    "name": "@surfacerelay/other",
                    "version": VERSION,
                    "private": True,
                    "type": "module",
                    "types": "./dist/index.d.ts",
                    "exports": {
                        ".": {
                            "types": "./dist/index.d.ts",
                            "import": "./dist/index.js",
                        }
                    },
                }
            ).encode("utf-8")

            with tarfile.open(wrong, mode="w:gz") as archive:
                info = tarfile.TarInfo("package/package.json")
                info.size = len(manifest_bytes)
                import io
                archive.addfile(info, io.BytesIO(manifest_bytes))

            with self.assertRaises(module.BrowserReleaseCandidateError):
                module.validate_browser_artifact_archive(
                    archive_path=wrong,
                    artifact_version=VERSION,
                )

    def test_installed_package_symlink_is_rejected(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            consumer = root / "consumer"
            package_source = root / "repo" / "packages" / "browser-runtime"
            installed_parent = consumer / "node_modules" / "@surfacerelay"
            installed_parent.mkdir(parents=True)
            package_source.mkdir(parents=True)
            installed = installed_parent / "browser-runtime"
            try:
                installed.symlink_to(package_source, target_is_directory=True)
            except OSError as exc:
                self.skipTest(f"symlink creation unavailable: {exc}")

            with self.assertRaises(module.BrowserReleaseCandidateError):
                module.verify_clean_consumer_install(
                    consumer_root=consumer,
                    artifact_version=VERSION,
                    package_source_root=package_source,
                )

    def test_installed_package_requires_root_declarations_and_no_deep_export(self):
        module = browser_candidate_module()

        cases = [
            {
                "label": "missing-root-declaration",
                "manifest": {
                    "name": PACKAGE_NAME,
                    "version": VERSION,
                    "private": True,
                    "type": "module",
                    "types": "./dist/missing.d.ts",
                    "exports": {
                        ".": {
                            "types": "./dist/missing.d.ts",
                            "import": "./dist/index.js",
                        }
                    },
                },
                "files": ["dist/index.js"],
            },
            {
                "label": "deep-export-leakage",
                "manifest": {
                    "name": PACKAGE_NAME,
                    "version": VERSION,
                    "private": True,
                    "type": "module",
                    "types": "./dist/index.d.ts",
                    "exports": {
                        ".": {
                            "types": "./dist/index.d.ts",
                            "import": "./dist/index.js",
                        },
                        "./dist/*": "./dist/*",
                    },
                },
                "files": ["dist/index.js", "dist/index.d.ts"],
            },
        ]

        for case in cases:
            with self.subTest(case=case["label"]):
                with tempfile.TemporaryDirectory() as directory:
                    root = Path(directory)
                    consumer = root / "consumer"
                    package_source = root / "repo" / "packages" / "browser-runtime"
                    installed = (
                        consumer
                        / "node_modules"
                        / "@surfacerelay"
                        / "browser-runtime"
                    )
                    installed.mkdir(parents=True)
                    package_source.mkdir(parents=True)

                    (installed / "package.json").write_text(
                        json.dumps(case["manifest"], indent=2) + "\n",
                        encoding="utf-8",
                    )
                    for relative in case["files"]:
                        target = installed / relative
                        target.parent.mkdir(parents=True, exist_ok=True)
                        target.write_text("export {};\n", encoding="utf-8")

                    with self.assertRaises(module.BrowserReleaseCandidateError):
                        module.verify_clean_consumer_install(
                            consumer_root=consumer,
                            artifact_version=VERSION,
                            package_source_root=package_source,
                        )


class BrowserCleanConsumerExecutionContractTest(unittest.TestCase):
    def test_exact_artifact_executes_isolated_root_only_consumer_journey(self):
        module = browser_candidate_module()
        repository = Path(__file__).resolve().parents[2]

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            source_root = root / "source"
            source_root.mkdir()
            source_tar = root / "source.tar"

            subprocess.run(
                [
                    "git",
                    "-C",
                    str(repository),
                    "archive",
                    "--format=tar",
                    "-o",
                    str(source_tar),
                    "HEAD",
                ],
                check=True,
                capture_output=True,
                text=True,
            )
            with tarfile.open(source_tar, mode="r:") as archive:
                archive.extractall(source_root, filter="data")

            revision = subprocess.run(
                ["git", "-C", str(repository), "rev-parse", "HEAD"],
                check=True,
                capture_output=True,
                text=True,
            ).stdout.strip()

            consumer = root / "consumer"
            fixture_root = source_root / "scripts" / "fixtures" / "browser-clean-consumer"

            module.create_clean_consumer_workspace(
                consumer_root=consumer,
                fixture_root=fixture_root,
                package_source_root=source_root / "packages" / "browser-runtime",
            )

            subprocess.run(
                ["npm", "ci"],
                cwd=source_root / "packages" / "browser-runtime",
                check=True,
                capture_output=True,
                text=True,
            )
            subprocess.run(
                ["npm", "run", "build"],
                cwd=source_root / "packages" / "browser-runtime",
                check=True,
                capture_output=True,
                text=True,
            )

            candidate = module.build_browser_release_candidate(
                repo=source_root,
                stage_root=root / "stage",
                artifact_version=VERSION,
                source_revision=revision,
            )

            proof = module.execute_clean_consumer_proof(
                consumer_root=consumer,
                archive_path=Path(candidate["archivePath"]),
                artifact_version=VERSION,
                package_source_root=source_root / "packages" / "browser-runtime",
            )

        self.assertEqual(
            "SurfaceRelay browser-runtime clean-consumer smoke: PASS",
            proof["smokeOutput"],
        )
        self.assertIs(True, proof["rootImport"])
        self.assertIs(True, proof["typecheck"])
        self.assertIs(True, proof["bundle"])
        self.assertIs(True, proof["deepImportRejected"])



class BrowserNpmLaunchContractTest(unittest.TestCase):
    def test_pack_resolves_npm_and_preserves_destination_argument(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            package = root / "package"
            package.mkdir()
            stage = root / "stage with spaces & metacharacters"
            stage.mkdir()
            archive = stage / "artifacts" / "candidate.tgz"

            def packed(command, **kwargs):
                archive.write_bytes(b"test archive")
                return subprocess.CompletedProcess(command, 0, '[{"filename":"candidate.tgz"}]', '')

            with patch("shutil.which", return_value="/trusted/bin/npm"), patch.object(
                module.subprocess, "run", side_effect=packed
            ) as run:
                self.assertEqual(archive, module._run_npm_pack(package, stage))
            command = run.call_args.args[0]
            self.assertEqual("/trusted/bin/npm", command[0])
            self.assertEqual(str(archive.parent), command[-1])
            self.assertFalse(run.call_args.kwargs.get("shell", False))

    def test_pack_fails_closed_when_npm_is_missing(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            with patch("shutil.which", return_value=None), patch.object(
                module.subprocess, "run",
                side_effect=AssertionError("missing npm must be rejected before process launch"),
            ) as run:
                with self.assertRaisesRegex(module.BrowserReleaseCandidateError, "npm.*(not found|not available|missing)"):
                    module._run_npm_pack(root, root)
                run.assert_not_called()

    def test_consumer_resolves_npm_without_shell_interpretation(self):
        module = browser_candidate_module()
        argument = "archive with spaces & $(untrusted).tgz"
        completed = subprocess.CompletedProcess([], 0, "", "")
        with patch("shutil.which", return_value="/trusted/bin/npm"), patch.object(
            module.subprocess, "run", return_value=completed
        ) as run:
            module._run_consumer_command(["npm", "install", argument], cwd=Path.cwd())
        self.assertEqual(["/trusted/bin/npm", "install", argument], run.call_args.args[0])
        self.assertFalse(run.call_args.kwargs.get("shell", False))

    def test_consumer_fails_closed_when_npm_is_missing(self):
        module = browser_candidate_module()
        with patch("shutil.which", return_value=None), patch.object(
            module.subprocess, "run",
            side_effect=AssertionError("missing npm must be rejected before process launch"),
        ) as run:
            with self.assertRaisesRegex(module.BrowserReleaseCandidateError, "npm.*(not found|not available|missing)"):
                module._run_consumer_command(["npm", "install"], cwd=Path.cwd())
            run.assert_not_called()

    def test_windows_npm_shim_uses_node_cli_with_literal_arguments(self):
        module = browser_candidate_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shim = root / "npm.cmd"
            node = root / "node.exe"
            cli = root / "node_modules" / "npm" / "bin" / "npm-cli.js"
            cli.parent.mkdir(parents=True)
            for file in (shim, node, cli):
                file.write_text("test fixture", encoding="utf-8")
            paths = {"npm": str(shim), "node": str(node)}
            completed = subprocess.CompletedProcess([], 0, "", "")
            argument = "archive & echo injected.tgz"
            with patch("shutil.which", side_effect=lambda name: paths.get(name)), patch.object(
                module.subprocess, "run", return_value=completed
            ) as run:
                module._run_consumer_command(["npm", "install", argument], cwd=root)
            self.assertEqual([str(node), str(cli), "install", argument], run.call_args.args[0])
            self.assertFalse(run.call_args.kwargs.get("shell", False))


if __name__ == "__main__":
    unittest.main()
