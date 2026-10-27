#!/usr/bin/env python3
"""Bounded receipt for A2-08 page wiring. Never promotes to the native proof."""
from __future__ import annotations
import json
import os
from pathlib import Path
import sys
from xml.etree import ElementTree

EXPECTED = frozenset({
    'test_panel_pages_serve_the_a2_registration_module_wiring',
    'test_mounted_page_embeds_the_descriptor_in_the_client_snapshot',
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
            raise ValueError('Failed or skipped A2-08 page-wiring test')
    return {
        'gate': 'A2-08-page-wiring',
        'result': 'PASS_A2_08_PAGE_WIRING_ONLY',
        'tests': len(cases),
        'failures': 0,
        'errors': 0,
        'skipped': 0,
        'headSha': os.environ.get('GITHUB_SHA', 'local'),
        'scope': 'Rendered page serves the registration module and embeds the descriptor. Real Chrome discovery/invocation is the separate local native proof.',
    }


def main() -> int:
    if len(sys.argv) != 3:
        print('Usage: python3 check_wiring_junit.py junit.xml result.json', file=sys.stderr)
        return 2
    try:
        report = summarize(Path(sys.argv[1]))
        Path(sys.argv[2]).write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
        print(json.dumps(report, indent=2))
        return 0
    except (ValueError, OSError, ElementTree.ParseError) as exc:
        print(f'A2-08 page-wiring acceptance NOT PASSED: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
