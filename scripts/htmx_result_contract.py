"""Validate only the adapter-specific HTMX result declaration fixtures."""

import json

from jsonschema import Draft202012Validator


def validate_htmx_result_contract(root):
    directory = root / 'packages' / 'browser-runtime' / 'conformance'
    schema = json.loads((directory / 'htmx-result-envelope.schema.json').read_text(encoding='utf-8'))
    cases = json.loads((directory / 'htmx-result-envelope.fixtures.json').read_text(encoding='utf-8'))
    Draft202012Validator.check_schema(schema)
    validator = Draft202012Validator(schema)
    failures = 0
    seen = set()
    for case in cases:
        name = case['name']
        errors = list(validator.iter_errors(case['header']))
        valid_expectation = case['expect'] in ('valid', 'invalid')
        matches = case['expect'] == 'valid' and not errors
        if case['expect'] == 'invalid':
            matches = any(
                error.validator == case['keyword']
                and ('/' + '/'.join(str(part) for part in error.absolute_path)) == case['instancePath']
                for error in errors
            )
        if name in seen or not valid_expectation or not matches:
            print(f'FAIL HTMX result declaration fixture: {name}')
            failures += 1
        seen.add(name)
    print(f'HTMX result declaration fixtures: {len(cases) - failures}/{len(cases)} passed.')
    return failures
