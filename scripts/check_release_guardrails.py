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
    ("publication-command/gh-release-create", ("gh", "release", "create")),
    ("publication-command/git-tag", ("git", "tag")),
)

_PACKAGE_MANAGER_PUBLISH_RULES = (
    ("npm", "publication-command/npm-publish"),
    ("pnpm", "publication-command/pnpm-publish"),
    ("yarn", "publication-command/yarn-publish"),
)

_CREDENTIALS = (
    "NPM_TOKEN",
    "NODE_AUTH_TOKEN",
    "PACKAGIST_TOKEN",
)

_TOKEN_PATTERN = re.compile(r"[A-Za-z0-9_./:@+$-]+")
_COMMAND_SEGMENT_SEPARATOR = re.compile(r"&&|\|\||[;|]")
_FOLDED_WORKFLOW_RUN = re.compile(
    r"^(?P<indent>\s*)(?:-\s*)?run:\s*>[+-]?\s*(?:#.*)?$"
)
_GIT_PUSH_TAGS_RULE = "publication-command/git-push-tags"


def _tokens(text: str) -> list[str]:
    return _TOKEN_PATTERN.findall(text)


def _contains_sequence(tokens: list[str], sequence: tuple[str, ...]) -> bool:
    width = len(sequence)
    return any(
        tuple(tokens[index:index + width]) == sequence
        for index in range(len(tokens) - width + 1)
    )


def _contains_ordered_tokens(
    tokens: list[str],
    first: str,
    second: str,
) -> bool:
    try:
        first_index = tokens.index(first)
    except ValueError:
        return False
    return second in tokens[first_index + 1:]


def _scan_command_segment(
    path: str,
    segment: str,
) -> list[GuardrailViolation]:
    tokens = _tokens(segment)
    violations: list[GuardrailViolation] = []

    for rule_id, sequence in _COMMAND_RULES:
        if _contains_sequence(tokens, sequence):
            violations.append(
                GuardrailViolation(
                    path=path,
                    rule_id=rule_id,
                    message=f"prohibited publication command detected ({rule_id})",
                )
            )

    for manager, rule_id in _PACKAGE_MANAGER_PUBLISH_RULES:
        if not _contains_ordered_tokens(tokens, manager, "publish"):
            continue
        if manager == "yarn":
            yarn_index = tokens.index("yarn")
            publish_index = tokens.index("publish", yarn_index + 1)
            if "npm" in tokens[yarn_index + 1:publish_index]:
                continue
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

    return violations


def _scan_text(path: str, text: str) -> list[GuardrailViolation]:
    violations: list[GuardrailViolation] = []

    for line in text.splitlines():
        for segment in _COMMAND_SEGMENT_SEPARATOR.split(line):
            violations.extend(_scan_command_segment(path, segment))

        for credential in _CREDENTIALS:
            if re.search(
                rf"(?<![A-Za-z0-9_]){re.escape(credential)}(?![A-Za-z0-9_])",
                line,
            ):
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


def _leading_spaces(line: str) -> int:
    return len(line) - len(line.lstrip(" "))


def _fold_yaml_block_lines(lines: list[str]) -> str:
    non_empty = [line for line in lines if line.strip()]
    if not non_empty:
        return ""

    content_indent = min(_leading_spaces(line) for line in non_empty)
    normalized = [
        line[content_indent:].rstrip()
        if line.strip()
        else ""
        for line in lines
    ]

    paragraphs: list[str] = []
    current: list[str] = []

    for line in normalized:
        if line:
            current.append(line)
            continue

        if current:
            paragraphs.append(" ".join(current))
            current = []
        paragraphs.append("")

    if current:
        paragraphs.append(" ".join(current))

    return "\n".join(paragraphs)


def _folded_workflow_run_values(text: str) -> list[str]:
    lines = text.splitlines()
    values: list[str] = []
    index = 0

    while index < len(lines):
        match = _FOLDED_WORKFLOW_RUN.match(lines[index])
        if match is None:
            index += 1
            continue

        base_indent = len(match.group("indent"))
        body: list[str] = []
        index += 1

        while index < len(lines):
            line = lines[index]
            if line.strip() and _leading_spaces(line) <= base_indent:
                break
            body.append(line)
            index += 1

        values.append(_fold_yaml_block_lines(body))

    return values


def _scan_workflow_text(path: str, text: str) -> list[GuardrailViolation]:
    violations = _scan_text(path, text)

    for command in _folded_workflow_run_values(text):
        violations.extend(_scan_text(path, command))

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

    workflows_root = repository_root / ".github" / "workflows"

    for path in _executable_text_surfaces(repository_root):
        relative, text = _read_text(repository_root, path)
        if path.parent == workflows_root:
            violations.extend(_scan_workflow_text(relative, text))
        else:
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
