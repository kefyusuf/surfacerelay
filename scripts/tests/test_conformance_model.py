import unittest

from scripts.conformance_model import (
    ERROR,
    FAIL,
    NOT_APPLICABLE,
    PASS,
    aggregate_exit_code,
    evaluate_observation,
    select_cases,
    validate_conformance_config,
    validate_observation,
)


class ConformanceModelTest(unittest.TestCase):
    def setUp(self):
        self.registry = {
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
                    "id": "BIND-COMPONENT-STALE",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/driver",
                    "requiresCapabilities": ["lifecycle.component"],
                    "expectation": {
                        "termination": "threw",
                        "frameworkDispatchCount": 0,
                    },
                    "recommendedCode": "binding_stale",
                },
            ]
        }
        self.livewire = {
            "targetId": "browser/livewire",
            "protocolVersion": "0.1",
            "profiles": ["runtime-binding/driver"],
            "capabilities": ["lifecycle.component"],
            "command": ["node", "livewire.js"],
        }
        self.htmx = {
            **self.livewire,
            "targetId": "browser/htmx",
            "capabilities": [],
            "command": ["node", "htmx.js"],
        }

    def test_capability_gated_case_is_applicable_only_to_declaring_target(self):
        livewire = {case.scenario_id: case for case in select_cases(self.registry, self.livewire)}
        htmx = {case.scenario_id: case for case in select_cases(self.registry, self.htmx)}

        self.assertTrue(livewire["BIND-COMPONENT-STALE"].applicable)
        self.assertFalse(htmx["BIND-COMPONENT-STALE"].applicable)
        self.assertEqual("binding_stale", livewire["BIND-COMPONENT-STALE"].recommended_code)

    def test_selection_is_sorted_and_ignores_non_executable_or_unclaimed_scenarios(self):
        registry = {
            "scenarios": [
                self.registry["scenarios"][1],
                {
                    "id": "SCHEMA-CASE",
                    "kind": "schema",
                    "status": "executable",
                    "fixture": "fixture.json",
                },
                {
                    "id": "DOCUMENTED-RUNTIME",
                    "kind": "runtime",
                    "status": "documented",
                    "profile": "runtime-binding/driver",
                },
                self.registry["scenarios"][0],
                {
                    "id": "OTHER-PROFILE",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/other",
                    "requiresCapabilities": [],
                    "expectation": {
                        "termination": "returned",
                        "frameworkDispatchCount": 1,
                    },
                },
            ]
        }

        cases = select_cases(registry, self.livewire)
        self.assertEqual(
            ["BIND-COMPONENT-STALE", "BIND-EXACT-TARGET-EXECUTES"],
            [case.scenario_id for case in cases],
        )

    def test_claimed_profile_requires_mandatory_positive_control(self):
        registry = {
            "scenarios": [
                {
                    "id": "NEGATIVE-ONLY",
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

        errors = validate_conformance_config(registry, [self.livewire])

        self.assertTrue(any("positive" in error.lower() for error in errors))

    def test_duplicate_scenario_ids_are_rejected(self):
        registry = {"scenarios": [self.registry["scenarios"][0], self.registry["scenarios"][0]]}

        errors = validate_conformance_config(registry, [self.livewire])

        self.assertTrue(any("duplicate scenario id" in error.lower() for error in errors))

    def test_unknown_profile_claim_is_rejected(self):
        target = {**self.livewire, "profiles": ["runtime-binding/unknown"]}

        errors = validate_conformance_config(self.registry, [target])

        self.assertTrue(any("unknown profile" in error.lower() for error in errors))

    def test_unknown_capability_claim_is_rejected(self):
        target = {**self.livewire, "capabilities": ["lifecycle.component", "lifecycle.unknown"]}

        errors = validate_conformance_config(self.registry, [target])

        self.assertTrue(any("unknown capability" in error.lower() for error in errors))

    def test_malformed_command_argv_is_rejected(self):
        invalid_commands = [
            "node livewire.js",
            [],
            ["node", 7],
            ["node", ""],
        ]

        for command in invalid_commands:
            with self.subTest(command=command):
                target = {**self.livewire, "command": command}
                errors = validate_conformance_config(self.registry, [target])
                self.assertTrue(any("command" in error.lower() for error in errors), errors)

    def test_executable_runtime_scenario_requires_profile_and_expectation(self):
        missing_profile = {
            **self.registry["scenarios"][0],
        }
        missing_profile.pop("profile")
        missing_expectation = {
            **self.registry["scenarios"][0],
        }
        missing_expectation.pop("expectation")

        errors = validate_conformance_config(
            {"scenarios": [missing_profile, missing_expectation]},
            [],
        )

        self.assertTrue(any("profile" in error.lower() for error in errors), errors)
        self.assertTrue(any("expectation" in error.lower() for error in errors), errors)

    def test_invalid_expectation_keys_are_rejected(self):
        scenario = {
            **self.registry["scenarios"][0],
            "expectation": {
                "termination": "returned",
                "frameworkDispatchCount": 1,
                "passed": True,
            },
        }

        errors = validate_conformance_config({"scenarios": [scenario]}, [self.livewire])

        self.assertTrue(any("expectation" in error.lower() and "passed" in error for error in errors), errors)

    def test_invalid_expectation_values_are_rejected(self):
        scenarios = []
        for index, expectation in enumerate(
            [
                {"termination": "completed", "frameworkDispatchCount": 1},
                {"termination": "returned", "frameworkDispatchCount": -1},
                {"termination": "returned", "frameworkDispatchCount": True},
                {
                    "termination": "returned",
                    "frameworkDispatchCount": 1,
                    "replacementDispatchCount": -1,
                },
            ]
        ):
            scenarios.append(
                {
                    "id": f"INVALID-{index}",
                    "kind": "runtime",
                    "status": "executable",
                    "profile": "runtime-binding/driver",
                    "requiresCapabilities": [],
                    "expectation": expectation,
                }
            )

        errors = validate_conformance_config({"scenarios": scenarios}, [])

        self.assertGreaterEqual(len(errors), len(scenarios))
        self.assertTrue(any("termination" in error.lower() for error in errors), errors)
        self.assertTrue(any("frameworkdispatchcount" in error.lower() for error in errors), errors)
        self.assertTrue(any("replacementdispatchcount" in error.lower() for error in errors), errors)

    def test_invalid_registry_and_target_shapes_are_rejected_without_raising(self):
        errors = validate_conformance_config(
            {"scenarios": "not-a-list"},
            [
                {
                    "targetId": "",
                    "protocolVersion": "9.9",
                    "profiles": "runtime-binding/driver",
                    "capabilities": ["lifecycle.component", "lifecycle.component"],
                    "command": ["node"],
                }
            ],
        )

        self.assertTrue(any("scenarios" in error.lower() for error in errors), errors)
        self.assertTrue(any("targetid" in error.lower() for error in errors), errors)
        self.assertTrue(any("protocolversion" in error.lower() for error in errors), errors)
        self.assertTrue(any("profiles" in error.lower() for error in errors), errors)
        self.assertTrue(any("duplicate capability" in error.lower() for error in errors), errors)

    def test_duplicate_target_ids_are_rejected(self):
        errors = validate_conformance_config(self.registry, [self.livewire, self.livewire])

        self.assertTrue(any("duplicate target id" in error.lower() for error in errors), errors)

    def test_requires_capabilities_must_be_unique_non_empty_strings(self):
        scenario = {
            **self.registry["scenarios"][1],
            "requiresCapabilities": ["lifecycle.component", "lifecycle.component", ""],
        }

        errors = validate_conformance_config({"scenarios": [self.registry["scenarios"][0], scenario]}, [self.livewire])

        self.assertTrue(any("requirescapabilities" in error.lower() for error in errors), errors)

    def test_validate_observation_accepts_raw_observation(self):
        observation = {
            "termination": "threw",
            "errorCode": "binding_expired",
            "frameworkDispatchCount": 0,
            "replacementDispatchCount": 0,
        }

        self.assertEqual([], validate_observation(observation))

    def test_validate_observation_rejects_missing_required_and_unknown_fields(self):
        errors = validate_observation({"termination": "returned", "passed": True})

        self.assertTrue(any("frameworkdispatchcount" in error.lower() for error in errors), errors)
        self.assertTrue(any("unknown observation field" in error.lower() for error in errors), errors)

    def test_validate_observation_rejects_invalid_values(self):
        invalid = {
            "termination": "completed",
            "frameworkDispatchCount": True,
            "replacementDispatchCount": -1,
            "errorCode": 7,
        }

        errors = validate_observation(invalid)

        self.assertTrue(any("termination" in error.lower() for error in errors), errors)
        self.assertTrue(any("frameworkdispatchcount" in error.lower() for error in errors), errors)
        self.assertTrue(any("replacementdispatchcount" in error.lower() for error in errors), errors)
        self.assertTrue(any("errorcode" in error.lower() for error in errors), errors)

    def test_validate_observation_rejects_non_mapping(self):
        errors = validate_observation(["not", "a", "mapping"])

        self.assertTrue(any("mapping" in error.lower() for error in errors), errors)

    def test_evaluate_observation_compares_only_expected_fields(self):
        expectation = {
            "termination": "threw",
            "frameworkDispatchCount": 0,
        }
        observation = {
            "termination": "threw",
            "frameworkDispatchCount": 0,
            "replacementDispatchCount": 99,
            "errorCode": "different_advisory_code",
        }

        self.assertEqual([], evaluate_observation(expectation, observation))

    def test_evaluate_observation_reports_each_mismatch(self):
        expectation = {
            "termination": "returned",
            "frameworkDispatchCount": 1,
            "replacementDispatchCount": 0,
        }
        observation = {
            "termination": "threw",
            "frameworkDispatchCount": 0,
        }

        errors = evaluate_observation(expectation, observation)

        self.assertEqual(3, len(errors), errors)
        self.assertTrue(any("termination" in error for error in errors), errors)
        self.assertTrue(any("frameworkDispatchCount" in error for error in errors), errors)
        self.assertTrue(any("replacementDispatchCount" in error for error in errors), errors)

    def test_aggregate_exit_code_uses_error_then_fail_precedence(self):
        self.assertEqual(0, aggregate_exit_code([]))
        self.assertEqual(0, aggregate_exit_code([PASS, NOT_APPLICABLE]))
        self.assertEqual(1, aggregate_exit_code([PASS, FAIL, NOT_APPLICABLE]))
        self.assertEqual(2, aggregate_exit_code([FAIL, ERROR]))


if __name__ == "__main__":
    unittest.main()
