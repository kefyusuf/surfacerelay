#!/usr/bin/env python3
"""Check relative markdown links across the repository's documentation.

Candidates will click every link in the consumer documents before the first
outreach send; this script verifies that the relative targets exist. Anchors
are not validated. HTTP(S), mailto and bare anchors are skipped.

Usage: python scripts/check_docs_links.py [--quiet]
Exit 0 when every relative link resolves; exit 1 with a report otherwise.
"""
from __future__ import annotations
import os
import re
import sys
from pathlib import Path
from urllib.parse import unquote

SKIP_DIRS = {'.git', '.tmp', 'node_modules', 'vendor', 'dist', '.mimosa', '.codex'}
LINK_PATTERN = re.compile(r'\[[^\]]*\]\(([^)\s]+)\)')
SKIP_PREFIXES = ('http://', 'https://', 'mailto:', '#')


def markdown_files(root: Path) -> list[Path]:
    """Prune skipped directories during traversal (rglob would still walk them)."""
    files = []
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS and not os.path.islink(os.path.join(dirpath, d))]
        for filename in filenames:
            if filename.endswith('.md'):
                files.append(Path(dirpath) / filename)
    return sorted(files)


def main() -> int:
    quiet = '--quiet' in sys.argv
    root = Path(__file__).resolve().parent.parent
    broken: list[tuple[Path, str, str]] = []
    checked = 0

    for md in markdown_files(root):
        try:
            text = md.read_text(encoding='utf-8')
        except (OSError, UnicodeDecodeError) as exc:
            broken.append((md, '<file unreadable>', str(exc)))
            continue
        for match in LINK_PATTERN.finditer(text):
            target = match.group(1).strip()
            if target.startswith(SKIP_PREFIXES):
                continue
            checked += 1
            path_part = unquote(target.split('#', 1)[0])
            if not path_part:
                continue  # bare in-page anchor
            resolved = (md.parent / path_part).resolve()
            if not resolved.exists():
                broken.append((md, target, 'target does not exist'))

    if broken:
        print(f'BROKEN relative links: {len(broken)} (of {checked} checked)')
        for md, target, reason in broken:
            print(f'  {md.relative_to(root)} -> {target}  [{reason}]')
        return 1

    print(f'All {checked} relative markdown links resolve.')
    if not quiet:
        print('Docs link check: PASS')
    return 0


if __name__ == '__main__':
    sys.exit(main())
