#!/usr/bin/env python3
"""Install only the quickstart overlay files into a disposable registry app.

Refuse existing target files, non-registry SurfaceRelay versions, and symlinks.
Never remove data or operate within the SurfaceRelay source checkout.
"""
from __future__ import annotations
import json
from pathlib import Path
import shutil
import sys

USER_CLASS_MARKER = "class User extends Authenticatable\n{"
USER_METHOD = r"""
    /** Quickstart demo panel access; invocation authorization stays in the Gate. */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return true;
    }
"""


def panel_user_source(original: str) -> str:
    """Conservatively patch only the standard disposable Laravel User skeleton."""
    if (not original.startswith('<?php\n')
            or 'namespace App\\Models;' not in original
            or 'use Illuminate\\Foundation\\Auth\\User as Authenticatable;' not in original
            or original.count(USER_CLASS_MARKER) != 1
            or 'FilamentUser' in original or 'canAccessPanel' in original):
        raise ValueError('Unexpected Laravel User model; refusing panel access patch')
    return original.replace(
        USER_CLASS_MARKER,
        'class User extends Authenticatable implements \\Filament\\Models\\Contracts\\FilamentUser\n{',
        1,
    )[:-2] + USER_METHOD + '}\n'


def main() -> int:
    if len(sys.argv) != 2:
        print('Usage: python3 install_overlay.py /absolute/fresh/registry/consumer/app', file=sys.stderr)
        return 2
    target = Path(sys.argv[1])
    source = Path(__file__).resolve().parent
    if not target.is_absolute():
        print('Target path must be absolute', file=sys.stderr)
        return 2
    try:
        if not target.is_dir() or target.is_symlink():
            raise ValueError('Target must be an existing non-symlink Laravel application')
        for parent in (target, *target.parents):
            if parent.is_symlink():
                raise ValueError('Target path traverses a symlink')
        for name in ('composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'bootstrap/providers.php'):
            if not (target / name).is_file():
                raise ValueError(f'Missing installed consumer file: {name}')
        cj = json.loads((target / 'composer.json').read_text())
        if cj.get('repositories', []) not in ({}, []):
            raise ValueError('Non-default Composer repository is not allowed')
        if cj.get('require', {}).get('surfacerelay/laravel') != '0.1.0-alpha.3':
            raise ValueError('Composer alpha.3 requirement is not exact')
        pj = json.loads((target / 'package.json').read_text())
        if pj.get('dependencies', {}).get('@surfacerelay/browser-runtime') != '0.1.0-alpha.3':
            raise ValueError('npm alpha.3 requirement is not exact')

        entries = []
        for folder in ('app', 'database', 'public', 'tests'):
            for file in (source / folder).rglob('*'):
                if file.is_symlink():
                    raise ValueError(f'Symlinked overlay file: {file}')
                if not file.is_file():
                    continue
                dest = target / file.relative_to(source)
                if dest.exists() or dest.is_symlink():
                    raise ValueError(f'Refusing to replace existing consumer file: {dest}')
                entries.append((file, dest))
        overlay_dirs = ('app', 'database', 'public', 'tests')
        expected = sum(
            1 for file in source.rglob('*')
            if file.is_file() and file.relative_to(source).parts[0] in overlay_dirs
        )
        if len(entries) != expected or expected == 0:
            raise ValueError(f'Unexpected overlay file count: {len(entries)} (expected {expected})')

        user_path = target / 'app/Models/User.php'
        updated_user = panel_user_source(user_path.read_text())
        providers_path = target / 'bootstrap/providers.php'
        providers = providers_path.read_text()
        if 'App\\Providers\\QuickstartProvider::class' in providers:
            raise ValueError('Quickstart provider is already registered')
        updated = providers.replace('];', '    \\App\\Providers\\QuickstartProvider::class,\n];')

        for file, dest in entries:
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(file, dest)
        providers_path.write_text(updated)
        user_path.write_text(updated_user)
    except (ValueError, OSError, KeyError) as e:
        print(f'Quickstart overlay BLOCKED: {e}', file=sys.stderr)
        return 2
    print(json.dumps({'status': 'INSTALLED_NOT_TESTED', 'files': [str(d.relative_to(target)) for _, d in entries]}, indent=2))
    return 0


if __name__ == '__main__':
    sys.exit(main())
