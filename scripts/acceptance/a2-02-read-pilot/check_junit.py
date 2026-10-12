#!/usr/bin/env python3
"""Bounded receipt for A2-02a. Never promotes server-only tests to full A2-02."""
from __future__ import annotations
import json
import os
from pathlib import Path
import sys
from xml.etree import ElementTree

EXPECTED = frozenset({
    'test_authenticated_owner_inspects_own_order_with_audit',
    'test_foreign_tenant_order_is_denied',
    'test_different_actor_can_inspect_only_its_own_tenant',
    'test_missing_membership_is_denied',
    'test_forged_tenant_field_does_not_grant_cross_tenant_access',
    'test_invalid_order_id_is_rejected_before_execution',
    'test_revoked_membership_fails_closed',
    'test_multiple_eligible_tenants_fail_closed_without_implicit_choice',
    'test_untrusted_tenant_field_is_not_forwarded_to_authorization',
    'test_authenticated_owner_can_mount_filament_orders_page',
    'test_guest_cannot_mount_filament_orders_page',
})


def summarize(xml: Path) -> dict:
    if not xml.is_file() or xml.stat().st_size > 256_000:
        raise ValueError('Missing or oversized PHPUnit XML')
    root = ElementTree.parse(xml).getroot()
    cases = root.findall('.//testcase')
    names = [case.get('name') for case in cases]
    if len(cases) != len(EXPECTED) or set(names) != EXPECTED or len(names) != len(set(names)):
        raise ValueError('Unexpected PHPUnit test identity or count')
    for case in cases:
        if any(case.find(tag) is not None for tag in ('error', 'failure', 'skipped')):
            raise ValueError('Failed or skipped A2-02a test')
    return {
        'gate': 'A2-02a-authenticated-Filament-server-read',
        'result': 'PASS_SERVER_READ_ONLY',
        'tests': len(cases),
        'failures': 0,
        'errors': 0,
        'skipped': 0,
        'headSha': os.environ.get('GITHUB_SHA', 'local'),
        'scope': 'Real Laravel mounted read + Gate/tenant/audit tests only. A2-02 WebMCP binding is NOT RUN.',
    }


def main() -> int:
    if len(sys.argv) != 3:
        print('Usage: python3 check_junit.py junit.xml result.json', file=sys.stderr)
        return 2
    try:
        report = summarize(Path(sys.argv[1]))
        Path(sys.argv[2]).write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        print(json.dumps(report, indent=2))
        return 0
    except (ValueError, OSError, ElementTree.ParseError) as exc:
        print(f'A2-02a acceptance NOT PASSED: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
