"""Conformance evidence rejects unsafe projection fields and fixture drift."""
from contextlib import redirect_stdout
import copy
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest

from jsonschema import Draft202012Validator

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts'))
from webmcp_result_contract import validate_webmcp_result_contract

DIRECTORY = ROOT / 'packages/browser-runtime/conformance'
SCHEMA = json.loads((DIRECTORY / 'webmcp-execution-result.schema.json').read_text(encoding='utf-8'))
CASES = json.loads((DIRECTORY / 'webmcp-execution-result.fixtures.json').read_text(encoding='utf-8'))


class WebMcpResultContract(unittest.TestCase):
    def setUp(self):
        self.validator = Draft202012Validator(SCHEMA)

    def check_cases(self, cases):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            directory = root / 'packages/browser-runtime/conformance'
            directory.mkdir(parents=True)
            (directory / 'webmcp-execution-result.schema.json').write_text(json.dumps(SCHEMA))
            (directory / 'webmcp-execution-result.fixtures.json').write_text(json.dumps(cases))
            with redirect_stdout(io.StringIO()):
                return validate_webmcp_result_contract(root)

    def test_all_committed_fixtures_match_declared_constraint(self):
        self.assertEqual(validate_webmcp_result_contract(ROOT), 0)
        self.assertGreater(len([case for case in CASES if case['expect'] == 'invalid']), 15)

    def test_null_and_undefined_are_distinct_valid_arms(self):
        null = {'kind': 'surfacerelay.webmcp.execution.v1', 'status': 'returned',
                'output': {'kind': 'value', 'value': None}}
        undefined = copy.deepcopy(null)
        undefined['output'] = {'kind': 'undefined'}
        self.assertTrue(self.validator.is_valid(null))
        self.assertTrue(self.validator.is_valid(undefined))
        undefined['output']['value'] = None
        self.assertFalse(self.validator.is_valid(undefined))
        del null['output']['value']
        self.assertFalse(self.validator.is_valid(null))

    def test_business_value_remains_arbitrary_and_is_not_adapter_authority(self):
        business = {'kind': 'surfacerelay.webmcp.execution.v1', 'status': 'execution_failed',
                    'receipt': 'business-owned', 'cause': {'arbitrary': True}, 'correlationId': 'server-owned'}
        result = {'kind': 'surfacerelay.webmcp.execution.v1', 'status': 'returned',
                  'output': {'kind': 'value', 'value': business}}
        self.assertTrue(self.validator.is_valid(result))
        self.assertFalse(self.validator.is_valid(business))

    def test_negative_fixture_cannot_be_accepted_for_wrong_constraint(self):
        case = copy.deepcopy(next(case for case in CASES if case['name'] == 'failure-raw-message'))
        case['keyword'] = 'type'
        self.assertEqual(self.check_cases([case]), 1)
        case['keyword'] = 'const'
        case['instancePath'] = '/error/nonexistent'
        self.assertEqual(self.check_cases([case]), 1)

    def test_fixture_expectation_cannot_hide_validity_regression(self):
        case = copy.deepcopy(CASES[0])
        case['result']['receipt'] = 'forbidden'
        self.assertEqual(self.check_cases([case]), 1)
        case = copy.deepcopy(next(case for case in CASES if case['name'] == 'failure-raw-message'))
        case['expect'] = 'valid'
        self.assertEqual(self.check_cases([case]), 1)

    def test_duplicate_unknown_and_empty_fixture_sets_fail(self):
        self.assertEqual(self.check_cases([CASES[0], CASES[0]]), 1)
        case = copy.deepcopy(CASES[0]); case['expect'] = 'ignored'
        self.assertEqual(self.check_cases([case]), 1)
        self.assertEqual(self.check_cases([]), 1)


if __name__ == '__main__':
    unittest.main(verbosity=2)
