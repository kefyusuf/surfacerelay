#!/usr/bin/env python3
"""Verify selected immutable published alpha.3 payload bytes; no monorepo files."""
from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

# Pinned from docs/reviews/alpha-0.1.0-alpha.3-publication.json at
# kefyusuf/surfacerelay@2cea491b8581bc84b1b692a722931083b6f79cb8.
# This is deliberately a bounded critical-path fingerprint check, NOT
# the 205-file exact-manifest verification already performed at publication.
PUBLISHED_CRITICAL_FILES = {
    "browser-runtime": {
        "dist/index.js": "8c9fdb6f517ce48ed082a46b2fa06fa7227a3c689118ab7cbf337044b8eed542",
        "dist/index.d.ts": "0303e79e024d4da29085df74a4dd3823b21c358a2b93ed9d5c52c3b4f3d0b65a",
        "dist/filament-browser-driver.js": "8bd33c7a1539a7182a85f97c35ebeb6c52baeee31d0681feee34af896301ad24",
        "package.json": "5d5b7ec2559d7bb3550ffa9f8854a5b9fed14e0e96fcc76558918ed206734635",
    },
    "laravel": {
        "composer.json": "abdb91399963b925867e6c5b41b42087ac37abfc03aed257d44eab1964f75371",
        "src/SurfaceRelayServiceProvider.php": "5de2a6053c8e4109c9efd54df02d2c4872bf2884c0b1852c740da1b82568b937",
        "src/Runtime/Pipeline/ActionBus.php": "b18e90048bb2daa91cf9168ba70a0e0cea905ce5f6e0b7d22a76c87e6a4ef90b",
        "src/Filament/Invocation/FilamentActionGateway.php": "746704a9899f57c0a391ccb7abb690088a681ee9d0c41f67ff9aa4a7d4112370",
        "src/Confirmation/ConfirmationService.php": "b98956f1d225924f18d2b6f25774c2e7cd2268730fd6ddc49f61f21d27b441c5",
    },
}


def verify_files(app: Path, expected: dict[str, dict[str, str]] = PUBLISHED_CRITICAL_FILES) -> dict:
    roots = {
        "browser-runtime": app / "node_modules/@surfacerelay/browser-runtime",
        "laravel": app / "vendor/surfacerelay/laravel",
    }
    errors: list[str] = []
    checked = 0
    for package, hashes in expected.items():
        root = roots[package]
        for relative, expected_digest in hashes.items():
            file = root / relative
            if not file.is_file() or file.is_symlink():
                errors.append(f"{package}/{relative}: missing or symlinked")
                continue
            # A symlink on a parent path can also escape the installed vendor tree.
            if any(p.is_symlink() for p in (file, *file.parents) if p != app.parent):
                errors.append(f"{package}/{relative}: symlinked parent")
                continue
            if hashlib.sha256(file.read_bytes()).hexdigest() != expected_digest:
                errors.append(f"{package}/{relative}: published SHA-256 mismatch")
                continue
            checked += 1
    return {
        "gate": "A2-01-critical-payload-verification",
        "result": "PASS_CRITICAL_SUBSET" if not errors else "FAIL",
        "checked": checked,
        "expected": sum(len(v) for v in expected.values()),
        "errors": errors,
        "scope": "Nine critical files only; not the full 205-file release parity claim.",
    }


def main() -> int:
    if len(sys.argv) != 2:
        print("usage: python3 verify_payload.py /path/to/consumer", file=sys.stderr)
        return 2
    app = Path(sys.argv[1]).resolve()
    result = verify_files(app)
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["result"] == "PASS_CRITICAL_SUBSET" else 1


if __name__ == "__main__":
    sys.exit(main())
