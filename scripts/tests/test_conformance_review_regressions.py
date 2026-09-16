import os
from pathlib import Path
import sys
import tempfile
import unittest

import scripts.conformance_model as conformance_model
from scripts.conformance_model import PASS
from scripts.run_conformance import run_harness


ROOT = Path(__file__).resolve().parents[2]
FAKE_RELATIVE = Path("scripts/tests/fixtures/fake_conformance_harness.py")


def request():
    return {
        "protocolVersion": "0.1",
        "requestId": "browser/livewire::BIND-EXACT-TARGET-EXECUTES",
        "scenarioId": "BIND-EXACT-TARGET-EXECUTES",
        "targetId": "browser/livewire",
        "profile": "runtime-binding/driver",
        "now": "2026-09-14T00:00:00.000Z",
    }


def canonical_registry():
    return {
        "scenarios": [
            {
                "id": "BIND-EXACT-TARGET-EXECUTES",
                "kind": "runtime",
                "status": "executable",
                "profile": "runtime-binding/driver",
                "requiresCapabilities": [],
                "expectation": {
                    "termination": "returned",
                    "frameworkDispatchCount": 1,
                    "replacementDispatchCount": 0,
                },
            },
            {
                "id": "BIND-EXPIRED-NOT-EXECUTABLE",
                "kind": "runtime",
                "status": "executable",
                "profile": "runtime-binding/driver",
                "requiresCapabilities": [],
                "expectation": {
                    "termination": "threw",
                    "frameworkDispatchCount": 0,
                },
            },
            {
                "id": "BIND-COMPONENT-STALE",
                "kind": "runtime",
                "status": "executable",
                "profile": "runtime-binding/driver",
                "requiresCapabilities": ["lifecycle.component"],
                "expectation": {
                    "termination": "threw",
                    "frameworkDispatchCount": 0,
                },
            },
            {
                "id": "BIND-NO-SILENT-RETARGET",
                "kind": "runtime",
                "status": "executable",
                "profile": "runtime-binding/driver",
                "requiresCapabilities": [],
                "expectation": {
                    "termination": "threw",
                    "frameworkDispatchCount": 0,
                    "replacementDispatchCount": 0,
                },
            },
        ]
    }


def canonical_targets():
    livewire = {
        "targetId": "browser/livewire",
        "protocolVersion": "0.1",
        "profiles": ["runtime-binding/driver"],
        "capabilities": ["lifecycle.component"],
        "command": ["node", "livewire.js"],
    }
    htmx = {
        "targetId": "browser/htmx",
        "protocolVersion": "0.1",
        "profiles": ["runtime-binding/driver"],
        "capabilities": [],
        "command": ["node", "htmx.js"],
    }
    return [livewire, htmx]


class ExternalReviewRegressionTest(unittest.TestCase):
    def test_v1_completeness_rejects_missing_and_unexpected_entries(self):
        validator = getattr(conformance_model, "validate_v1_conformance_config", None)
        self.assertIsNotNone(validator, "v1 completeness validator must exist")

        registry = canonical_registry()
        targets = canonical_targets()
        self.assertEqual([], validator(registry, targets))

        missing_scenario = {"scenarios": registry["scenarios"][:-1]}
        errors = validator(missing_scenario, targets)
        self.assertTrue(any("missing executable runtime scenario" in error for error in errors), errors)

        extra_scenario = {
            "scenarios": registry["scenarios"]
            + [
                {
                    "id": "BIND-UNEXPECTED",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/driver",
                    "requiresCapabilities": [],
                    "expectation": {
                        "termination": "threw",
                        "frameworkDispatchCount": 0,
                    },
                }
            ]
        }
        errors = validator(extra_scenario, targets)
        self.assertTrue(any("unexpected executable runtime scenario" in error for error in errors), errors)

        errors = validator(registry, targets[:1])
        self.assertTrue(any("missing target" in error for error in errors), errors)

        unexpected_target = {
            **targets[0],
            "targetId": "browser/unexpected",
            "capabilities": [],
            "command": ["node", "unexpected.js"],
        }
        errors = validator(registry, targets + [unexpected_target])
        self.assertTrue(any("unexpected target" in error for error in errors), errors)

    def test_repo_relative_harness_command_runs_from_repository_root(self):
        original_cwd = Path.cwd()
        try:
            with tempfile.TemporaryDirectory() as tmp:
                os.chdir(tmp)
                result = run_harness(
                    [sys.executable, str(FAKE_RELATIVE), "valid"],
                    request(),
                )
        finally:
            os.chdir(original_cwd)

        self.assertEqual(PASS, result.status, result.infrastructure_error)


if __name__ == "__main__":
    unittest.main()
