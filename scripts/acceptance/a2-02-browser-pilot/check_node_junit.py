#!/usr/bin/env python3
"""Bounded receipt for A2-02 B2 Node browser-wiring suite. Never promotes to full A2-02."""
from __future__ import annotations
import json
import os
from pathlib import Path
import sys
from xml.etree import ElementTree

EXPECTED = frozenset({
    'test_descriptor_document_matches_published_runtime_contract',
    'test_registers_exactly_one_tool_with_published_projection_contract',
    'test_unsupported_surface_fails_closed_without_registration',
    'test_definition_binding_identity_mismatch_fails_closed_before_registration',
    'test_void_dispatch_returns_envelope_undefined_output',
    'test_execution_failure_envelope_never_discloses_raw_error',
    'test_stale_component_yields_execution_failed_envelope',
    'test_expired_binding_yields_execution_failed_envelope',
    'test_forged_extra_input_is_rejected_before_dispatch',
    'test_missing_required_input_is_rejected_before_dispatch',
    'test_pre_aborted_signal_returns_cancelled_not_dispatched',
    'test_lease_dispose_aborts_registration_signal_for_unmount_cleanup',
    'test_concurrent_invocations_on_one_component_are_excluded_by_shared_coordinator',
    'test_selection_sync_is_never_invoked_for_non_selection_action',
    'test_coordinator_is_reusable_across_registrations_in_one_environment',
})


def summarize(xml: Path) -> dict:
    if not xml.is_file() or xml.stat().st_size > 256_000:
        raise ValueError('Missing or oversized Node JUnit XML')
    root = ElementTree.parse(xml).getroot()
    cases = root.findall('.//testcase')
    names = [case.get('name') for case in cases]
    if len(cases) != len(EXPECTED) or set(names) != EXPECTED or len(names) != len(set(names)):
        raise ValueError(f'Unexpected Node test identity or count: {names!r}')
    for case in cases:
        if any(case.find(tag) is not None for tag in ('error', 'failure', 'skipped')):
            raise ValueError('Failed or skipped A2-02 B2 Node test')
    return {
        'gate': 'A2-02-browser-B2-browser-wiring',
        'result': 'PASS_B2_BROWSER_WIRING_ONLY',
        'tests': len(cases),
        'failures': 0,
        'errors': 0,
        'skipped': 0,
        'headSha': os.environ.get('GITHUB_SHA', 'local'),
        'scope': 'Published alpha.3 browser-runtime against stubbed modelContext/Livewire. Real Chrome document.modelContext proof is NOT RUN (A2-08).',
    }


def main() -> int:
    if len(sys.argv) != 3:
        print('Usage: python3 check_node_junit.py junit.xml result.json', file=sys.stderr)
        return 2
    try:
        report = summarize(Path(sys.argv[1]))
        Path(sys.argv[2]).write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        print(json.dumps(report, indent=2))
        return 0
    except (ValueError, OSError, ElementTree.ParseError) as exc:
        print(f'A2-02 B2 browser-wiring acceptance NOT PASSED: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
