import importlib
import json
from pathlib import Path
import tempfile
import unittest


def guardrail_module():
    return importlib.import_module("scripts.check_release_guardrails")


class ReleaseGuardrailContractTest(unittest.TestCase):
    def write(self, root: Path, relative: str, content: str) -> Path:
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding="utf-8")
        return path

    def scan(self, module, root: Path):
        return module.scan_release_guardrails(root)

    def assert_rule(self, violations, *, path: str, rule_id: str):
        matches = [
            violation
            for violation in violations
            if violation.path == path and violation.rule_id == rule_id
        ]
        self.assertEqual(
            1,
            len(matches),
            f"expected one {rule_id!r} violation for {path!r}, got {violations!r}",
        )
        return matches[0]

    def test_npm_publish_is_rejected_in_package_script(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps({"scripts": {"release": "npm publish"}}),
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path="package.json",
            rule_id="publication-command/npm-publish",
        )

    def test_pnpm_publish_is_rejected_in_package_script(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "packages/example/package.json",
                json.dumps({"scripts": {"release": "pnpm publish --access public"}}),
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path="packages/example/package.json",
            rule_id="publication-command/pnpm-publish",
        )

    def test_yarn_publish_variants_are_rejected(self):
        module = guardrail_module()
        cases = {
            "yarn publish --new-version 1.2.3": "publication-command/yarn-publish",
            "yarn npm publish": "publication-command/yarn-npm-publish",
        }

        for command, rule_id in cases.items():
            with self.subTest(command=command):
                with tempfile.TemporaryDirectory() as directory:
                    root = Path(directory)
                    self.write(
                        root,
                        "package.json",
                        json.dumps({"scripts": {"release": command}}),
                    )

                    violations = self.scan(module, root)

                self.assert_rule(
                    violations,
                    path="package.json",
                    rule_id=rule_id,
                )

    def test_publish_with_interposed_package_manager_arguments_is_rejected(self):
        module = guardrail_module()
        cases = {
            "pnpm -r publish": "publication-command/pnpm-publish",
            "npm --workspace pkg publish": "publication-command/npm-publish",
        }

        for command, rule_id in cases.items():
            with self.subTest(command=command):
                with tempfile.TemporaryDirectory() as directory:
                    root = Path(directory)
                    self.write(
                        root,
                        "package.json",
                        json.dumps({"scripts": {"release": command}}),
                    )

                    violations = self.scan(module, root)

                self.assert_rule(
                    violations,
                    path="package.json",
                    rule_id=rule_id,
                )

    def test_publish_matching_respects_command_segments(self):
        module = guardrail_module()

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps({"scripts": {"check": "npm pack && echo publish"}}),
            )
            self.assertEqual([], self.scan(module, root))

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps({"scripts": {"release": "npm pack; npm publish"}}),
            )
            violations = self.scan(module, root)
            self.assert_rule(
                violations,
                path="package.json",
                rule_id="publication-command/npm-publish",
            )

        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps({"scripts": {"release": "yarn workspace x npm publish"}}),
            )
            violations = self.scan(module, root)
            self.assert_rule(
                violations,
                path="package.json",
                rule_id="publication-command/npm-publish",
            )

    def test_folded_workflow_run_value_is_rejected(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    steps:\n"
                    "      - run: >\n"
                    "          npm\n"
                    "          publish\n"
                ),
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path=".github/workflows/release.yml",
            rule_id="publication-command/npm-publish",
        )

    def test_multiline_plain_workflow_run_value_is_rejected(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    steps:\n"
                    "      - run: npm\n"
                    "          publish\n"
                ),
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path=".github/workflows/release.yml",
            rule_id="publication-command/npm-publish",
        )

    def test_explicit_indent_folded_workflow_run_value_is_rejected(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    steps:\n"
                    "      - run: >2\n"
                    "          npm\n"
                    "          publish\n"
                ),
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path=".github/workflows/release.yml",
            rule_id="publication-command/npm-publish",
        )

    def test_folded_workflow_chomping_variants_are_rejected(self):
        module = guardrail_module()

        for indicator in (">-", ">+"):
            with self.subTest(indicator=indicator):
                with tempfile.TemporaryDirectory() as directory:
                    root = Path(directory)
                    self.write(
                        root,
                        ".github/workflows/release.yml",
                        (
                            "name: release\n"
                            "jobs:\n"
                            "  release:\n"
                            "    steps:\n"
                            f"      - run: {indicator}\n"
                            "          npm\n"
                            "          publish\n"
                        ),
                    )

                    violations = self.scan(module, root)

                self.assert_rule(
                    violations,
                    path=".github/workflows/release.yml",
                    rule_id="publication-command/npm-publish",
                )

    def test_duplicate_workflow_mapping_keys_fail_closed(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    steps:\n"
                    "      - run: echo first\n"
                    "        run: echo second\n"
                ),
            )

            with self.assertRaises(module.GuardrailScanError):
                self.scan(module, root)

    def test_malformed_workflow_yaml_fails_closed(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                "name: release\njobs: [\n",
            )

            with self.assertRaises(module.GuardrailScanError):
                self.scan(module, root)

    def test_non_string_workflow_run_value_fails_closed(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    steps:\n"
                    "      - run: 123\n"
                ),
            )

            with self.assertRaises(module.GuardrailScanError):
                self.scan(module, root)

    def test_workflow_comments_are_not_executable_release_surfaces(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    steps:\n"
                    "      # npm publish must remain prohibited; do not wire NPM_TOKEN here.\n"
                    "      - run: echo artifact-only\n"
                ),
            )

            violations = self.scan(module, root)

        self.assertEqual([], violations)

    def test_github_release_create_is_rejected_in_workflow(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                "name: release\njobs:\n  release:\n    steps:\n      - run: gh release create v1.2.3\n",
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path=".github/workflows/release.yml",
            rule_id="publication-command/gh-release-create",
        )

    def test_git_tag_is_rejected_in_makefile(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(root, "Makefile", "release:\n\tgit tag v1.2.3\n")

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path="Makefile",
            rule_id="publication-command/git-tag",
        )

    def test_git_push_tags_is_rejected_in_release_script(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "scripts/release_publish.py",
                'COMMAND = ["git", "push", "origin", "--tags"]\n',
            )

            violations = self.scan(module, root)

        self.assert_rule(
            violations,
            path="scripts/release_publish.py",
            rule_id="publication-command/git-push-tags",
        )

    def test_registry_publication_credentials_are_rejected_in_executable_surfaces(self):
        module = guardrail_module()
        cases = {
            "NPM_TOKEN": "publication-credential/NPM_TOKEN",
            "NODE_AUTH_TOKEN": "publication-credential/NODE_AUTH_TOKEN",
            "PACKAGIST_TOKEN": "publication-credential/PACKAGIST_TOKEN",
        }

        for credential, rule_id in cases.items():
            with self.subTest(credential=credential):
                with tempfile.TemporaryDirectory() as directory:
                    root = Path(directory)
                    self.write(
                        root,
                        ".github/workflows/release.yaml",
                        (
                            "name: release\n"
                            "jobs:\n"
                            "  release:\n"
                            "    env:\n"
                            f"      {credential}: super-secret-value\n"
                            "    steps:\n"
                            "      - run: echo artifact-only\n"
                        ),
                    )

                    violations = self.scan(module, root)

                violation = self.assert_rule(
                    violations,
                    path=".github/workflows/release.yaml",
                    rule_id=rule_id,
                )
                self.assertNotIn("super-secret-value", violation.message)

    def test_npm_pack_is_allowed(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps({"scripts": {"pack:rc": "npm pack --json"}}),
            )

            violations = self.scan(module, root)

        self.assertEqual([], violations)

    def test_markdown_with_prohibited_commands_is_not_scanned(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "docs/release-plan.md",
                (
                    "# Prohibited examples\n\n"
                    "Never run npm publish, gh release create, or git tag.\n"
                    "Do not configure NPM_TOKEN.\n"
                ),
            )

            violations = self.scan(module, root)

        self.assertEqual([], violations)

    def test_package_json_non_script_prose_is_not_scanned(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps(
                    {
                        "description": "Documentation says npm publish is prohibited.",
                        "scripts": {"pack:rc": "npm pack"},
                    }
                ),
            )

            violations = self.scan(module, root)

        self.assertEqual([], violations)

    def test_unrelated_executable_commands_are_allowed(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/validate.yml",
                (
                    "name: validate\n"
                    "jobs:\n"
                    "  test:\n"
                    "    steps:\n"
                    "      - run: python scripts/validate.py\n"
                ),
            )
            self.write(
                root,
                "Makefile",
                "validate:\n\tpython scripts/validate.py\n",
            )
            self.write(
                root,
                "scripts/release_candidate.py",
                'COMMAND = ["npm", "pack"]\n',
            )

            violations = self.scan(module, root)

        self.assertEqual([], violations)

    def test_violation_reports_relative_path_and_rule_without_secret_value(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                ".github/workflows/release.yml",
                (
                    "name: release\n"
                    "jobs:\n"
                    "  release:\n"
                    "    env:\n"
                    "      NPM_TOKEN: ultra-private-secret\n"
                    "    steps:\n"
                    "      - run: npm publish\n"
                ),
            )

            violations = self.scan(module, root)

        credential = self.assert_rule(
            violations,
            path=".github/workflows/release.yml",
            rule_id="publication-credential/NPM_TOKEN",
        )
        command = self.assert_rule(
            violations,
            path=".github/workflows/release.yml",
            rule_id="publication-command/npm-publish",
        )
        self.assertNotIn("ultra-private-secret", credential.message)
        self.assertNotIn("ultra-private-secret", command.message)
        self.assertFalse(Path(credential.path).is_absolute())
        self.assertFalse(Path(command.path).is_absolute())

    def test_similar_but_non_publication_tokens_do_not_false_positive(self):
        module = guardrail_module()
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            self.write(
                root,
                "package.json",
                json.dumps(
                    {
                        "scripts": {
                            "check": "echo npm-publisher && echo gh release checklist"
                        }
                    }
                ),
            )

            violations = self.scan(module, root)

        self.assertEqual([], violations)


if __name__ == "__main__":
    unittest.main()
