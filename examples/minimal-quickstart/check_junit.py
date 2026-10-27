#!/usr/bin/env python3
"""Bounded receipt for T-913 quickstart. Never promotes beyond the document's read-only scope."""
from __future__ import annotations
import json
import os
from pathlib import Path
import sys
from xml.etree import ElementTree

EXPECTED = frozenset({
    'test_member_dispatch_inspects_own_order_with_audit',
    'test_foreign_tenant_order_is_denied',
    'test_forged_extra_field_does_not_become_authority',
    'test_page_issues_the_browser_descriptor_document',
    'test_guest_cannot_mount_the_quickstart_page',
})


def summarize(xml: Path) -> dict:
    if not xml.is_file() or xml.stat().st_size > 256_000:
        raise ValueError('Missing or oversized PHPUnit XML')
    root = ElementTree.parse(xml).getroot()
    cases = root.findall('.//testcase')
    names = [case.get('name') for case in cases]
    if len(cases) != len(EXPECTED) or set(names) != EXPECTED or len(names) != len(set(names)):
        raise ValueError(f'Unexpected PHPUnit test identity or count: {names!r}')
    for case in cases:
        if any(case.find(tag) is not None for tag in ('error', 'failure', 'skipped')):
            raise ValueError('Failed or skipped quickstart test')
    return {
        'gate': 'T-913-quickstart-consumer',
        'result': 'PASS_T913_QUICKSTART_READ_ONLY',
        'tests': len(cases),
        'failures': 0,
        'errors': 0,
        'skipped': 0,
        'headSha': os.environ.get('GITHUB_SHA', 'local'),
        'scope': "The documented quickstart code on a fresh registry consumer. Read-only; browser registration semantics are proven by the Node suite; real Chrome interop is the A2-08 native proof.",
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
        print(f'T-913 quickstart acceptance NOT PASSED: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
