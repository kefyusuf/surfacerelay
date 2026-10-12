#!/usr/bin/env python3
"""Install only A2 overlay files into a disposable registry-installed app.

Refuse existing target files, non-registry SurfaceRelay versions, and symlinks.
Never remove data or operate within the SurfaceRelay source checkout.
"""
from __future__ import annotations
import json
from pathlib import Path
import shutil
import sys

PACKAGE_NAME = "surfacerelay/laravel"
NPM_NAME = "@surfacerelay/browser-runtime"


def assert_fresh_target(source: Path, app: Path) -> list[tuple[Path, Path]]:
    if not app.is_dir() or app.is_symlink():
        raise ValueError("Target must be an existing non-symlink Laravel application")
    if any(part == '..' for part in app.parts):
        raise ValueError("Unsafe target path")
    for parent in (app, *app.parents):
        if parent.is_symlink():
            raise ValueError("Target path traverses a symlink")
    for name in ('composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'bootstrap/providers.php'):
        if not (app / name).is_file():
            raise ValueError(f"Missing installed consumer file: {name}")
    cj = json.loads((app / 'composer.json').read_text())
    repos = cj.get('repositories', [])
    if repos not in ({}, []):
        raise ValueError('Non-default Composer repository is not allowed')
    if cj.get('require', {}).get(PACKAGE_NAME) != '0.1.0-alpha.3':
        raise ValueError('Composer alpha.3 requirement is not exact')
    pj = json.loads((app / 'package.json').read_text())
    if pj.get('dependencies', {}).get(NPM_NAME) != '0.1.0-alpha.3':
        raise ValueError('npm alpha.3 requirement is not exact')
    cl = json.loads((app / 'composer.lock').read_text())
    pkgs = {p['name']: p for p in cl.get('packages', [])}
    for pkg, prefix in [('laravel/framework', 'v13.'), ('filament/filament', 'v5.'), (PACKAGE_NAME, 'v0.1.0-alpha.3')]:
        if not pkgs.get(pkg, {}).get('version', '').startswith(prefix):
            raise ValueError(f"Expected registry consumer package {pkg} at {prefix}*")
    srel = pkgs.get(PACKAGE_NAME, {})
    if srel.get('source', {}).get('reference') != '1d20fcc98f6a525362e30138aff44d23b719dde1':
        raise ValueError('Unexpected alpha.3 Laravel source revision')
    nl = json.loads((app / 'package-lock.json').read_text())
    if nl.get('packages', {}).get('node_modules/' + NPM_NAME, {}).get('version') != '0.1.0-alpha.3':
        raise ValueError('Expected exact npm alpha.3 consumer')
    integrity = nl.get('packages', {}).get('node_modules/' + NPM_NAME, {}).get('integrity')
    if integrity != 'sha512-THbTgfMxz/JisrBiU/X4jAJkMjiKQ5JMEP5YlPaoTJKnr4q/bQw4EF1nYu3+e3yaxdWafxnZqXYYwWXHFkmSVA==':
        raise ValueError('Unexpected alpha.3 npm integrity')
    if not (app / 'vendor/surfacerelay/laravel').is_dir():
        raise ValueError('Missing installed Laravel package')
    if (app / 'vendor/surfacerelay/laravel').is_symlink():
        raise ValueError('Symlinked installed Laravel package')
    if app == source or app in source.parents or source in app.parents:
        raise ValueError('Refusing to install inside the overlay source or vice versa')

    entries = []
    for folder in ('app', 'database', 'tests'):
        for file in (source / folder).rglob('*'):
            if file.is_symlink():
                raise ValueError(f'Symlinked overlay file: {file}')
            if not file.is_file():
                continue
            dest = app / file.relative_to(source)
            if any(parent.is_symlink() for parent in (dest, *dest.parents) if parent != app.parent):
                raise ValueError(f'Symlinked destination path: {dest}')
            if dest.exists() or dest.is_symlink():
                raise ValueError(f"Refusing to replace existing consumer file: {dest}")
            entries.append((file, dest))
    if len(entries) != 8:
        raise ValueError(f"Expected 8 application overlay PHP files, found {len(entries)}")
    if (app / 'bootstrap/providers.php').is_symlink():
        raise ValueError('Symlinked bootstrap provider file')
    providers = (app / 'bootstrap/providers.php').read_text()
    if 'AdminPanelProvider::class' not in providers:
        raise ValueError('Filament AdminPanelProvider not registered in bootstrap/providers.php')
    if 'App\\Providers\\A2OrdersProvider::class' in providers:
        raise ValueError('A2 provider is already registered')
    if providers.count('];') != 1 or 'return [' not in providers:
        raise ValueError('Unexpected Laravel bootstrap/providers.php format')
    return entries


def main() -> int:
    if len(sys.argv) != 2:
        print('Usage: python3 install_overlay.py /absolute/fresh/registry/consumer/app', file=sys.stderr)
        return 2
    target = Path(sys.argv[1]); source = Path(__file__).resolve().parent
    if not target.is_absolute():
        print('Target path must be absolute', file=sys.stderr)
        return 2
    try:
        entries = assert_fresh_target(source, target)
        providers_path = target / 'bootstrap/providers.php'
        updated = providers_path.read_text().replace('];', '    \\App\\Providers\\A2OrdersProvider::class,\n];')
        for file, dest in entries:
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(file, dest)
        providers_path.write_text(updated)
    except (ValueError, OSError, KeyError) as e:
        print(f'A2-02 overlay BLOCKED: {e}', file=sys.stderr)
        return 2
    print(json.dumps({'status': 'INSTALLED_NOT_TESTED', 'files': [str(d.relative_to(target)) for _, d in entries]}, indent=2))
    return 0


if __name__ == '__main__':
    sys.exit(main())
