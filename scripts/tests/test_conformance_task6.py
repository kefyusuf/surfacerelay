import unittest

from scripts.conformance_model import evaluate_observation, validate_conformance_config


class ConformanceTask6RegressionTest(unittest.TestCase):
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
                }
            ]
        }
        self.target = {
            "targetId": "browser/livewire",
            "protocolVersion": "0.1",
            "profiles": ["runtime-binding/driver"],
            "capabilities": [],
            "command": ["node", "livewire.js"],
        }

    def test_mandatory_positive_control_makes_claimed_profile_valid(self):
        self.assertEqual([], validate_conformance_config(self.registry, [self.target]))

    def test_advisory_error_code_missing_or_different_does_not_flip_pass(self):
        expectation = {"termination": "threw", "frameworkDispatchCount": 0}
        self.assertEqual(
            [],
            evaluate_observation(
                expectation,
                {"termination": "threw", "frameworkDispatchCount": 0},
            ),
        )
        self.assertEqual(
            [],
            evaluate_observation(
                expectation,
                {
                    "termination": "threw",
                    "frameworkDispatchCount": 0,
                    "errorCode": "different_advisory_code",
                },
            ),
        )


if __name__ == "__main__":
    unittest.main()
