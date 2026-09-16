#!/usr/bin/env python3
"""Validate SurfaceRelay 0.1 schemas, fixtures, and repo-local conformance config."""
from pathlib import Path
import json, sys

from conformance_model import validate_v1_conformance_config

ROOT = Path(__file__).resolve().parents[1]

try:
    from jsonschema import Draft202012Validator, FormatChecker, ValidationError
except ImportError:
    print('ERROR: jsonschema is required. Install with: python -m pip install jsonschema', file=sys.stderr)
    raise SystemExit(2)

FORMAT_CHECKER = FormatChecker()

schema_dir = ROOT / 'spec' / '0.1'
schema_files = {
    'action-definition': schema_dir / 'action-definition.schema.json',
    'runtime-binding': schema_dir / 'runtime-binding.schema.json',
    'invocation': schema_dir / 'invocation.schema.json',
    'action-result': schema_dir / 'action-result.schema.json',
}
schemas = {k: json.loads(p.read_text(encoding='utf-8')) for k, p in schema_files.items()}

for name, schema in schemas.items():
    Draft202012Validator.check_schema(schema)
    print(f'OK schema: {name}')

failures = 0

canonical = schemas['action-definition']['properties']
references = {
    'runtime-binding.action': schemas['runtime-binding']['properties']['action']['properties'],
    'invocation.action': schemas['invocation']['properties']['action']['properties'],
}
identity_constraints = {
    'id': ('type', 'pattern', 'maxLength'),
    'version': ('type', 'minimum'),
}
for prop, keys in identity_constraints.items():
    for key in keys:
        expected = canonical[prop].get(key)
        print(f'OK canonical Action Identity: {prop}.{key}={expected!r}')
        for ref_name, ref_props in references.items():
            actual = ref_props[prop].get(key)
            if actual != expected:
                print(f'FAIL Action Identity grammar drift: '
                      f'{ref_name}.{prop}.{key}={actual!r} != canonical {key}={expected!r}')
                failures += 1

binding_binding_id = schemas['runtime-binding']['properties']['bindingId']
invocation_binding_id = schemas['invocation']['properties']['bindingId']
if binding_binding_id.get('type') != 'string':
    print(f"FAIL Binding ID drift: runtime-binding.bindingId.type={binding_binding_id.get('type')!r} != 'string'")
    failures += 1
if invocation_binding_id.get('type') != ['string', 'null']:
    print(f"FAIL Binding ID drift: invocation.bindingId.type={invocation_binding_id.get('type')!r} != ['string', 'null']")
    failures += 1
for key in ('minLength', 'maxLength'):
    expected = binding_binding_id.get(key)
    actual = invocation_binding_id.get(key)
    if expected != actual:
        print(f'FAIL Binding ID grammar drift: invocation.bindingId.{key}={actual!r} '
              f'!= canonical runtime-binding.bindingId.{key}={expected!r}')
        failures += 1
    else:
        print(f'OK Binding ID grammar match: {key}={expected!r}')


def instance_path_str(error):
    parts = [str(p) for p in error.absolute_path]
    return '/' + '/'.join(parts) if parts else '/'

manifest_path = ROOT / 'spec' / '0.1' / 'fixtures' / 'manifest.json'
manifest = json.loads(manifest_path.read_text(encoding='utf-8'))
entries = manifest['entries']

listed = {e['path'] for e in entries}
fixture_root = ROOT / 'spec' / '0.1' / 'fixtures'
manifest_rel = str(manifest_path.relative_to(ROOT)).replace('\\', '/')
scenarios_rel = 'spec/0.1/fixtures/conformance-scenarios.json'
orphans = sorted(
    str(p.relative_to(ROOT)).replace('\\', '/')
    for p in fixture_root.rglob('*.json') if p != manifest_path
    and str(p.relative_to(ROOT)).replace('\\', '/') not in listed | {manifest_rel, scenarios_rel}
)
for orphan in orphans:
    print(f'FAIL orphan fixture (not in manifest): {orphan}')
    failures += 1

# Conformance scenario registry integrity: schema scenarios remain tied to
# manifest fixtures. Documented runtime scenarios keep the historical
# fail-closed note; executable runtime scenarios are validated structurally
# together with the closed T-701 v1 target/scenario matrix.
scenarios_path = ROOT / 'spec' / '0.1' / 'fixtures' / 'conformance-scenarios.json'
scenarios = json.loads(scenarios_path.read_text(encoding='utf-8'))['scenarios']
allowed_kinds = {'schema', 'runtime'}
allowed_status = {'executable', 'documented'}
allowed_codes = {'binding_not_found', 'binding_stale', 'binding_expired', 'driver_unsupported'}
manifest_paths = listed
seen_ids = set()
for scen in scenarios:
    sid = scen.get('id', '<missing>')
    if sid in seen_ids:
        print(f'FAIL conformance scenario id duplicated: {sid}')
        failures += 1
    seen_ids.add(sid)
    if scen.get('kind') not in allowed_kinds or scen.get('status') not in allowed_status:
        print(f'FAIL conformance scenario has unknown kind/status: {sid}')
        failures += 1
        continue
    if scen['kind'] == 'schema':
        if scen.get('fixture') not in manifest_paths:
            print(f'FAIL schema scenario fixture not in manifest: {sid} ({scen.get("fixture")})')
            failures += 1
        continue

    recommended_code = scen.get('recommendedCode')
    if recommended_code is not None and recommended_code not in allowed_codes:
        print(f'FAIL runtime scenario has unknown recommended code: {sid} ({recommended_code})')
        failures += 1
    if scen['status'] == 'documented':
        if scen.get('expect') != 'fail_closed' or recommended_code not in allowed_codes:
            print(f'FAIL documented runtime scenario must be fail_closed with a recommended code: {sid}')
            failures += 1


targets_dir = ROOT / 'conformance' / 'targets'
target_paths = sorted(targets_dir.glob('*.json')) if targets_dir.is_dir() else []
targets = []
if not target_paths:
    print(f'FAIL conformance config: no target manifests found in {targets_dir}')
    failures += 1
else:
    for target_path in target_paths:
        try:
            target = json.loads(target_path.read_text(encoding='utf-8'))
        except json.JSONDecodeError as exc:
            print(f'FAIL conformance config: invalid JSON in {target_path}: {exc.msg}')
            failures += 1
            continue
        targets.append(target)

config_errors = validate_v1_conformance_config({'scenarios': scenarios}, targets)
for error in config_errors:
    print(f'FAIL conformance config: {error}')
    failures += 1

for entry in entries:
    path = ROOT / entry['path']
    value = json.loads(path.read_text(encoding='utf-8'))
    validator = Draft202012Validator(schemas[entry['schema']], format_checker=FORMAT_CHECKER)
    expect = entry['expect']

    if expect == 'valid':
        try:
            validator.validate(value)
        except ValidationError as exc:
            print(f"FAIL fixture marked valid did not validate: {entry['path']} "
                  f"({exc.validator} at {instance_path_str(exc)}: {exc.message[:120]})")
            failures += 1
            continue
        print(f"OK fixture (valid): {entry['path']}")
        continue

    if expect != 'invalid':
        print(f"FAIL manifest entry has unknown expect value {expect!r}: {entry['path']}")
        failures += 1
        continue

    errors = list(validator.iter_errors(value))
    if not errors:
        print(f"FAIL fixture marked invalid validated cleanly: {entry['path']}")
        failures += 1
        continue

    keyword = entry.get('keyword')
    want_path = entry.get('instancePath')
    want_prop = entry.get('property')

    def matches(e):
        if keyword is not None and e.validator != keyword:
            return False
        if want_path is not None and instance_path_str(e) != want_path:
            return False
        if want_prop is not None:
            return f"'{want_prop}'" in e.message
        return True

    reasons = {(e.validator, instance_path_str(e)) for e in errors}
    matched = [e for e in errors if matches(e)]
    if not matched:
        print(f"FAIL fixture failed for the wrong reason: {entry['path']} "
              f"(expected {keyword} at {want_path or '/'}{f' property {want_prop!r}' if want_prop else ''}; got {sorted(reasons)})")
        failures += 1
        continue
    e = matched[0]
    print(f"OK fixture (invalid as intended, {e.validator} at {instance_path_str(e)}): {entry['path']}")

if failures:
    print(f'SurfaceRelay starter validation FAILED with {failures} failure(s).')
    raise SystemExit(1)

print('SurfaceRelay starter validation passed.')
