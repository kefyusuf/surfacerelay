#!/usr/bin/env python3
"""Non-mutating, bounded A2 environment preflight. No package installation."""
from __future__ import annotations
import argparse
import json
import re
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

REQUIRED_MODULES = ("mbstring", "pdo_sqlite", "sqlite3", "openssl", "xml", "zip")
REGISTRY_HOSTS = ("registry.npmjs.org", "repo.packagist.org")


def command_output(argv: list[str], *, truncate_at: int | None = 600) -> dict:
    tool = shutil.which(argv[0])
    if not tool:
        return {"available": False, "path": None}
    try:
        p = subprocess.run(argv, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=8, check=False)
        return {"available": p.returncode == 0, "path": tool, "output": (p.stdout if truncate_at is None else p.stdout[:truncate_at]).strip()}
    except (OSError, subprocess.TimeoutExpired) as e:
        return {"available": False, "path": tool, "error": type(e).__name__}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, help="Optional JSON report path")
    args = parser.parse_args()
    checks = {}
    for name, cmd in (
        ("php", ["php", "-v"]),
        ("composer", ["composer", "--version"]),
        ("node", ["node", "--version"]),
        ("npm", ["npm", "--version"]),
    ):
        checks[name] = command_output(cmd)

    php_major_minor = None
    php_output = checks["php"].get("output", "")
    match = re.search(r"PHP (\d+)\.(\d+)", php_output)
    if match:
        php_major_minor = tuple(int(v) for v in match.groups())
    checks["php_compatible"] = bool(php_major_minor and (8, 4) <= php_major_minor < (9, 0))

    modules = command_output(["php", "-m"], truncate_at=None)
    installed = {m.lower() for m in modules.get("output", "").splitlines()}
    checks["php_extensions"] = {name: name.lower() in installed for name in REQUIRED_MODULES}

    for host in REGISTRY_HOSTS:
        # Bounded DNS probe on WSL/Linux; avoids indefinite libc resolver delays.
        if not shutil.which("getent"):
            checks[host] = {"dns_resolves": False, "error": "getent unavailable"}
            continue
        try:
            p = subprocess.run(["getent", "ahosts", host], stdout=subprocess.DEVNULL,
                               stderr=subprocess.DEVNULL, timeout=4, check=False)
            checks[host] = {"dns_resolves": p.returncode == 0}
        except subprocess.TimeoutExpired:
            checks[host] = {"dns_resolves": False, "error": "DNS lookup timeout"}

    blockers = []
    for name in ("php", "composer", "node", "npm"):
        if not checks[name]["available"]:
            blockers.append(f"{name} unavailable")
    if not checks["php_compatible"]:
        blockers.append("PHP 8.4+ and <9 required by this pilot")
    blockers.extend(f"missing PHP extension: {name}" for name, ok in checks["php_extensions"].items() if not ok)
    blockers.extend(f"DNS unresolved: {host}" for host in REGISTRY_HOSTS if not checks[host]["dns_resolves"])
    report = {
        "gate": "A2-01-preflight",
        "checkedAtUtc": datetime.now(timezone.utc).isoformat(),
        "result": "BLOCKED" if blockers else "PASS_PREREQUISITES_ONLY",
        "checks": checks,
        "blockers": blockers,
        "note": "This does not prove installed packages, native browser behavior, or A2-02.",
    }
    if args.output:
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(report, indent=2, ensure_ascii=False))
    return 2 if blockers else 0


if __name__ == "__main__":
    sys.exit(main())
