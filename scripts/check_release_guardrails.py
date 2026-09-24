from __future__ import annotations

from dataclasses import dataclass
import json
from pathlib import Path
import re
import sys


@dataclass(frozen=True, order=True)
class GuardrailViolation:
    path: str
    rule_id: str
    message: str


class GuardrailScanError(RuntimeError):
    """Raised when an executable release surface cannot be inspected safely."""


_COMMAND_RULES: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("publication-command/yarn-npm-publish", ("yarn", "npm", "publish")),
    ("publication-command/npm-publish", ("npm", "publish")),
    ("publication-command/pnpm-publish", ("pnpm", "publish")),
    ("publication-command/yarn-publish", ("yarn", "publish")),
    ("publication-command/gh-release-create", ("gh", "release", "create")),
    ("publication-command/git-tag", ("git", "tag")),
)

_CREDENTIALS = (
    "NPM_TOKEN",
    "NODE_AUTH_TOKEN",
    "PACKAGIST_TOKEN",
)

_TOKEN_PATTERN = re.compile(r"[A-Za-z0-9_./:@+\\-$]+")
_GIT_PUSH_TAGS_RULE = "publication-command/git-push-tags"


def _tokens(text: str) -> list[str]:
    return _TOKEN_PATTERN.findall(text)


def _contains_sequence(tokens: list[str], sequence: tuple[str, ...]) -> bool:
    width = len(sequence)
    return any(
        tuple(tokens[index:index + width]) == sequence
        for index in range(len(tokens) - width + 1)
    )


def _scan_text(path: str, text: str) -> list[GuardrailViolation]:
    violations: list[GuardrailViolation] = []

    for line in text.splitlines():
        tokens = _tokens(line)

        for rule_id, sequence in _COMMAND_RULES:
            if _contains_sequence(tokens, sequence):
                violations.append(
                    GuardrailViolation(
                        path=path,
                        rule_id=rule_id,
                        message=f"prohibited publication command detected ({rule_id})",
                    )
                )

        if len(tokens) >= 3:
            for index in range(len(tokens) - 1):
                if tokens[index:index + 2] != ["git", "push"]:
                    continue
                if "--tags" in tokens[index + 2:]:
                    violations.append(
                        GuardrailViolation(
                            path=path,
                            rule_id=_GIT_PUSH_TAGS_RULE,
                            message=(
                                "prohibited publication command detected "
                                f"({_GIT_PUSH_TAGS_RULE})"
                            ),
                        )
                    )
                    break

        token_set = set(tokens)
        for credential in _CREDENTIALS:
            if credential in token_set:
                rule_id = f"publication-credential/{credential}"
                violations.append(
                    GuardrailViolation(
                        path=path,
                        rule_id=rule_id,
                        message=(
                            "publication credential identifier is not allowed "
                            f"in executable release surfaces ({rule_id})"
                        ),
                    )
                )

    return violations


def _scan_package_json(root: Path, path: Path) -> list[GuardrailViolation]:
    relative = path.relative_to(root).as_posix()

    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise GuardrailScanError(f"cannot inspect {relative}") from exc

    scripts = value.get("scripts", {}) if isinstance(value, dict) else {}
    if scripts is None:
        scripts = {}
    if not isinstance(scripts, dict):
        raise GuardrailScanError(f"{relative} scripts must be an object")

    violations: list[GuardrailViolation] = []
    for command in scripts.values():
        if not isinstance(command, str):
            raise GuardrailScanError(f"{relative} script values must be strings")
        violations.extend(_scan_text(relative, command))

    return violations


def _read_text(root: Path, path: Path) -> tuple[str, str]:
    relative = path.relative_to(root).as_posix()
    try:
        return relative, path.read_text(encoding="utf-8")
    except (OSError, UnicodeError) as exc:
        raise GuardrailScanError(f"cannot inspect {relative}") from exc


def _executable_text_surfaces(root: Path) -> list[Path]:
    surfaces: list[Path] = []

    workflows = root / ".github" / "workflows"
    if workflows.is_dir():
        surfaces.extend(workflows.glob("*.yml"))
        surfaces.extend(workflows.glob("*.yaml"))

    makefile = root / "Makefile"
    if makefile.is_file():
        surfaces.append(makefile)

    scripts = root / "scripts"
    if scripts.is_dir():
        surfaces.extend(scripts.glob("release_*.py"))

    return sorted(set(surfaces), key=lambda path: path.relative_to(root).as_posix())


def _package_json_surfaces(root: Path) -> list[Path]:
    surfaces: list[Path] = []

    root_package = root / "package.json"
    if root_package.is_file():
        surfaces.append(root_package)

    packages = root / "packages"
    if packages.is_dir():
        surfaces.extend(packages.glob("*/package.json"))

    return sorted(set(surfaces), key=lambda path: path.relative_to(root).as_posix())


def scan_release_guardrails(root: Path | str) -> list[GuardrailViolation]:
    repository_root = Path(root)

    if not repository_root.is_dir():
        raise GuardrailScanError("repository root must be a directory")

    violations: list[GuardrailViolation] = []

    for path in _package_json_surfaces(repository_root):
        violations.extend(_scan_package_json(repository_root, path))

    for path in _executable_text_surfaces(repository_root):
        relative, text = _read_text(repository_root, path)
        violations.extend(_scan_text(relative, text))

    return sorted(set(violations))


def main(argv: list[str] | None = None) -> int:
    args = sys.argv[1:] if argv is None else argv

    if len(args) > 1:
        print("usage: python scripts/check_release_guardrails.py [repository-root]", file=sys.stderr)
        return 2

    root = Path(args[0]) if args else Path(__file__).resolve().parents[1]

    try:
        violations = scan_release_guardrails(root)
    except GuardrailScanError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    if violations:
        for violation in violations:
            print(
                f"FAIL {violation.path}: {violation.rule_id}: {violation.message}",
                file=sys.stderr,
            )
        return 1

    print("SurfaceRelay release publication guardrails passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
