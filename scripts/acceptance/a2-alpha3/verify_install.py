#!/usr/bin/env python3
"""Check the actual consumer lockfiles; never read the SurfaceRelay monorepo."""
from __future__ import annotations
import json
import sys
from pathlib import Path

EXPECTED_LARAVEL_MIRROR = "1d20fcc98f6a525362e30138aff44d23b719dde1"
EXPECTED_NPM_INTEGRITY = "sha512-THbTgfMxz/JisrBiU/X4jAJkMjiKQ5JMEP5YlPaoTJKnr4q/bQw4EF1nYu3+e3yaxdWafxnZqXYYwWXHFkmSVA=="
VERSION = "0.1.0-alpha.3"


def check(condition, reason, errors):
    if not condition:
        errors.append(reason)


def main():
    if len(sys.argv) != 2:
        print("usage: python3 verify_install.py /path/to/consumer", file=sys.stderr)
        return 2
    app = Path(sys.argv[1]).resolve()
    errors = []
    result = {"gate": "A2-01-lock-verification", "consumer": str(app), "packages": {}}
    for name in ("composer.json", "composer.lock", "package-lock.json", "package.json"):
        check((app / name).is_file(), f"missing {name}", errors)
    if errors:
        result.update({"result": "FAIL", "errors": errors})
        print(json.dumps(result, indent=2))
        return 1

    composer_json = json.loads((app / "composer.json").read_text(encoding="utf-8"))
    repos = composer_json.get("repositories", [])
    if isinstance(repos, dict):
        repos = list(repos.values())
    # This acceptance gate must use the default Packagist source, not a
    # consumer-defined Composer repository (including custom Composer mirrors).
    check(isinstance(repos, list) and not repos,
          "custom Composer repository detected", errors)
    check(
        composer_json.get("require", {}).get("surfacerelay/laravel") == VERSION,
        "Composer SurfaceRelay requirement not exact alpha.3", errors,
    )
    lock = json.loads((app / "composer.lock").read_text(encoding="utf-8"))
    packages = {p["name"]: p for p in lock.get("packages", [])}
    for name, major in (("laravel/framework", 13), ("filament/filament", 5), ("livewire/livewire", 4)):
        p = packages.get(name, {})
        result["packages"][name] = p.get("version")
        check(p.get("version", "").lstrip("v").startswith(f"{major}."), f"{name}: unexpected/missing version", errors)
    package = packages.get("surfacerelay/laravel", {})
    result["packages"]["surfacerelay/laravel"] = package.get("version")
    check(package.get("version", "").lstrip("v") == VERSION, "Composer SurfaceRelay version mismatch", errors)
    check(package.get("source", {}).get("reference") == EXPECTED_LARAVEL_MIRROR, "Composer SurfaceRelay source ref mismatch", errors)
    check(package.get("dist", {}).get("type") == "zip", "Composer SurfaceRelay not installed from expected dist ZIP", errors)
    node_package_json = json.loads((app / "package.json").read_text(encoding="utf-8"))
    node_requirements = node_package_json.get("dependencies", {})
    check(
        node_requirements.get("@surfacerelay/browser-runtime") == VERSION,
        "npm SurfaceRelay requirement not exact alpha.3", errors,
    )
    node_lock = json.loads((app / "package-lock.json").read_text(encoding="utf-8"))
    npm_package = node_lock.get("packages", {}).get("node_modules/@surfacerelay/browser-runtime", {})
    result["packages"]["@surfacerelay/browser-runtime"] = npm_package.get("version")
    check(npm_package.get("version") == VERSION, "npm SurfaceRelay version mismatch", errors)
    check(npm_package.get("integrity") == EXPECTED_NPM_INTEGRITY, "npm integrity mismatch", errors)
    resolved = npm_package.get("resolved", "")
    check(resolved.startswith("https://registry.npmjs.org/"), "npm package was not fetched from public npm registry", errors)
    check(not (app / "node_modules/@surfacerelay/browser-runtime").is_symlink(), "npm SurfaceRelay install is a symlink", errors)
    check((app / "vendor/surfacerelay/laravel").is_dir(), "Composer vendor package absent", errors)
    check(not (app / "vendor/surfacerelay/laravel").is_symlink(), "Composer SurfaceRelay install is a symlink", errors)
    check((app / "node_modules/@surfacerelay/browser-runtime").is_dir(), "npm package absent", errors)
    result.update({"result": "FAIL" if errors else "PASS_LOCK_METADATA_ONLY", "errors": errors})
    print(json.dumps(result, indent=2))
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
