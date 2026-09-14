from contextlib import redirect_stderr, redirect_stdout
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

from scripts.conformance_model import (
    ERROR,
    FAIL,
    NOT_APPLICABLE,
    PASS,
    SelectedCase,
    aggregate_exit_code,
)
from scripts.run_conformance import (
    CaseResult,
    ConfigurationError,
    execute_case,
    filter_cases,
    filter_targets,
    main,
    render_json,
    render_text,
    run_harness,
)


FAKE = Path(__file__).parent / "fixtures" / "fake_conformance_harness.py"


def request():
    return {
        "protocolVersion": "0.1",
        "requestId": "browser/livewire::BIND-EXACT-TARGET-EXECUTES",
        "scenarioId": "BIND-EXACT-TARGET-EXECUTES",
        "targetId": "browser/livewire",
        "profile": "runtime-binding/driver",
        "now": "2026-09-14T00:00:00.000Z",
    }


def command(mode):
    return [sys.executable, str(FAKE), mode]


class RunConformanceTest(unittest.TestCase):
    def test_valid_child_returns_observation(self):
        result = run_harness(command("valid"), request())

        self.assertEqual(PASS, result.status)
        self.assertEqual(
            {
                "termination": "returned",
                "frameworkDispatchCount": 1,
                "replacementDispatchCount": 0,
            },
            result.observation,
        )
        self.assertIsNone(result.infrastructure_error)

    def test_nonzero_child_is_error(self):
        result = run_harness(command("nonzero"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("7", result.infrastructure_error or "")

    def test_invalid_json_is_error(self):
        result = run_harness(command("invalid-json"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("JSON", result.infrastructure_error or "")

    def test_extra_stdout_is_error(self):
        result = run_harness(command("extra-stdout"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("JSON", result.infrastructure_error or "")

    def test_wrong_protocol_version_is_error(self):
        result = run_harness(command("wrong-version"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("protocolVersion", result.infrastructure_error or "")

    def test_wrong_request_id_is_error(self):
        result = run_harness(command("wrong-request"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("requestId", result.infrastructure_error or "")

    def test_wrong_scenario_id_is_error(self):
        result = run_harness(command("wrong-scenario"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("scenarioId", result.infrastructure_error or "")

    def test_wrong_target_id_is_error(self):
        result = run_harness(command("wrong-target"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("targetId", result.infrastructure_error or "")

    def test_wrong_profile_is_error(self):
        result = run_harness(command("wrong-profile"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("profile", result.infrastructure_error or "")

    def test_invalid_observation_is_error(self):
        result = run_harness(command("invalid-observation"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("observation", (result.infrastructure_error or "").lower())

    def test_empty_stdout_is_error(self):
        result = run_harness(command("empty-stdout"), request())

        self.assertEqual(ERROR, result.status)
        self.assertIn("stdout", (result.infrastructure_error or "").lower())

    def test_timeout_is_error(self):
        result = run_harness(
            command("hang"),
            request(),
            timeout_seconds=0.05,
        )

        self.assertEqual(ERROR, result.status)
        self.assertIn("timeout", (result.infrastructure_error or "").lower())

    def test_error_status_has_exit_code_precedence(self):
        self.assertEqual(2, aggregate_exit_code([PASS, FAIL, ERROR]))

    def test_target_filter_rejects_unknown_target(self):
        targets = [
            {"targetId": "browser/livewire"},
            {"targetId": "browser/htmx"},
        ]

        with self.assertRaises(ConfigurationError):
            filter_targets(targets, "browser/unknown")

    def test_scenario_filter_rejects_unknown_scenario(self):
        cases = [
            SelectedCase(
                target_id="browser/livewire",
                scenario_id="BIND-EXACT-TARGET-EXECUTES",
                profile="runtime-binding/driver",
                applicable=True,
                expectation={"termination": "returned", "frameworkDispatchCount": 1},
                recommended_code=None,
            )
        ]

        with self.assertRaises(ConfigurationError):
            filter_cases(cases, "BIND-UNKNOWN")

    def test_json_output_is_deterministically_sorted(self):
        results = [
            CaseResult(
                target_id="browser/livewire",
                scenario_id="BIND-Z",
                profile="runtime-binding/driver",
                status=FAIL,
                observation={"termination": "returned", "frameworkDispatchCount": 0},
                diagnostics="",
                infrastructure_error=None,
                mismatches=("frameworkDispatchCount mismatch",),
            ),
            CaseResult(
                target_id="browser/htmx",
                scenario_id="BIND-A",
                profile="runtime-binding/driver",
                status=PASS,
                observation={"termination": "returned", "frameworkDispatchCount": 1},
                diagostics="",
                infrastructure_error=None,
                mismatches=(),
            ),
        ]

        forward = render_json(results)
        reverse = render_json(list(reversed(results)))
        payload = json.loads(forward)

        self.assertEqual(forward, reverse)
        self.assertEqual("browser/htmx", payload["results"][0]["targetId"])
        self.assertEqual("BIND-A", payload["results"][0]["scenarioId"])
        self.assertEqual({"ERROR": 0, "FAIL": 1, "NOT_APPLICABLE": 0, "PASS": 1}, payload["summary"])

    def test_execute_case_marks_normative_mismatch_fail(self):
        case = SelectedCase(
            target_id="browser/livewire",
            scenario_id="BIND-EXACT-TARGET-EXECUTES",
            profile="runtime-binding/driver",
            applicable=True,
            expectation={"termination": "threw", "frameworkDispatchCount": 0},
            recommended_code=None,
        )
        target = {"targetId": "browser/livewire", "command": command("valid")}

        result = execute_case(case, target)

        self.assertEqual(FAIL, result.status)
        self.assertTrue(result.mismatches)

    def test_execute_case_marks_matching_observation_pass(self):
        case = SelectedCase(
            target_id="browser/livewire",
            scenario_id="BIND-EXACT-TARGET-EXECUTES",
            profile="runtime-binding/driver",
            applicable=True,
            expectation={
                "termination": "returned",
                "frameworkDispatchCount": 1,
                "replacementDispatchCount": 0,
            },
            recommended_code=None,
        )
        target = {"targetId": "browser/livewire", "command": command("valid")}

        result = execute_case(case, target)

        self.assertEqual(PASS, presult.status)
        self.assertEqual((), result.mismatches)

    def test_execute_case_does_not_spawn_not_applicable_case(self):
        case = SelectedCase(
            target_id="browser/htmx",
            scenario_id="BIND-COMPONENT-STALE",
            profile="runtime-binding/driver",
            applicable=False,
            expectation={"termination": "threw", "frameworkDispatchCount": 0},
            recommended_code="binding_stale",
        )
        target = {"targetId": "browser/htmx", "command": ["definitely-not-an-executable"]}

        result = execute_case(case, target)

        self.assertEqual(NOT_APPLICABLE, result.status)
        self.assertIsNone(result.observation)

    def test_text_output_uses_stable_status_labels_and_summary(self):
        results = [
            CaseResult(
                target_id="browser/htmx",
                scenario_id="BIND-COMPONENT-STALE",
                profile="runtime-binding/driver",
                status=NOT_APPLICABLE,
                observation=None,
                diagnostics="",
                infrastructure_error=None,
                mismatches=(),
            ),
            CaseResult(
                target_id="browser/htmx",
                scenario_id="BIND-EXACT-TARGET-EXECUTES",
                profile="runtime-binding/driver",
                status=PASS,
                observation={"termination": "returned", "frameworkDispatchCount": 1},
                diagnostics="",
                infrastructure_error=None,
                mismatches=(),
            ),
        ]

        text = render_text(results)

        self.assertEqual(
            "N/A  browser/htmx BIND-COMPONENT-STALE\n"
            "PASS browser/htmx BIND-EXACT-TARGET-EXECUTES\n"
            "Summary: 1 PASS, 0 FAIL, 0 ERROR, 1 NOT_APPLICABLE",
            text,
        )

    def _write_cli_fixture(self, root: Path):
        registry_path = root / "conformance-scenarios.json"
        targets_dir = root / "targets"
        targets_dir.mkdir()
        registry_path.write_text(
            json.dumps(
                {
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
                        }
                    ]
                }
            ),
            encoding="utf-8",
        )
        (targets_dir / "browser-livewire.json").write_text(
            json.dumps(
                {
                    "targetId": "browser/livewire",
                    "protocolVersion": "0.1",
                    "profiles": ["runtime-binding/driver"],
                    "capabilities": [],
                    "command": command("valid"),
                }
            ),
            encoding="utf-8",
        )
        return registry_path, targets_dir

    def test_main_json_executes_fake_target_and_returns_zero(self):
        with tempfile.TemporaryDirectory() as tmp:
            registry_path, targets_dir = self._write_cli_fixture(Path(tmp))
            stdout = io.StringIO()
            with patch("scripts.run_conformance.REGISTRY_PATH", registry_path), patch(
                "scripts.run_conformance.TARGETS_DIR", targets_dir
            ), redirect_stdout(stdout):
                exit_code = main(["--json"])

        self.assertEqual(0, exit_code)
        payload = json.loads(stdout.getvalue())
        self.assertEqual(PASS, payload["results"][0]["status"])

    def test_main_unknown_target_filter_is_configuration_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            registry_path, targets_dir = self._write_cli_fixture(Path(tmp))
            stderr = io.StringIO()
            with patch("scripts.run_conformance.REGISTRY_PATH", registry_path), patch(
                "scripts.run_conformance.TARGETS_DIR", targets_dir
            ), redirect_stderr(stderr):
                exit_code = main(["--target", "browser/unknown"])

        self.assertEqual(2, exit_code)
        self.assertIn("unknown target", stderr.getvalue().lower())

    def test_main_unknown_scenario_filter_is_configuration_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            registry_path, targets_dir = self._write_cli_fixture(Path(tmp))
            stderr = io.StringIO()
            with patch("scripts.run_conformance.REGISTRY_PATH", registry_path), patch(
                "scripts.run_conformance.TARGETS_DIR", targets_dir
            ), redirect_stderr(stderr):
                exit_code = main(["--scenario", "BIND-UNKNOWN"])

        self.assertEqual(2, exit_code)
        self.assertIn("unknown scenario", stderr.getvalue().lower())


if __name__ == "__main__":
    unittest.main()
