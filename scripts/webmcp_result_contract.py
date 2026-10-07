"""Validate the projection-only opt-in WebMCP execution envelope fixtures."""
import json

from jsonschema import Draft202012Validator


def flatten_errors(errors):
    """Retain leaf constraints inside oneOf so negatives prove their reason."""
    for error in errors:
        yield error
        yield from flatten_errors(error.context)


def validate_webmcp_result_contract(root):
    directory = root / 'packages' / 'browser-runtime' / 'conformance'
    schema = json.loads((directory / 'webmcp-execution-result.schema.json').read_text(encoding='utf-8'))
    cases = json.loads((directory / 'webmcp-execution-result.fixtures.json').read_text(encoding='utf-8'))
    Draft202012Validator.check_schema(schema)
    validator = Draft202012Validator(schema)
    failures = 0
    seen = set()
    if not cases:
        print('FAIL WebMCP execution result fixtures: empty fixture set')
        return 1
    for case in cases:
        name = case['name']
        errors = list(validator.iter_errors(case['result']))
        matches = case['expect'] == 'valid' and not errors
        if case['expect'] == 'invalid':
            matches = any(
                error.validator == case['keyword']
                and ('/' + '/'.join(str(part) for part in error.absolute_path)) == case['instancePath']
                for error in flatten_errors(errors)
            )
        if name in seen or case['expect'] not in ('valid', 'invalid') or not matches:
            print(f'FAIL WebMCP execution result fixture: {name}')
            failures += 1
        seen.add(name)
    print(f'WebMCP execution result fixtures: {len(cases) - failures}/{len(cases)} passed.')
    return failures
