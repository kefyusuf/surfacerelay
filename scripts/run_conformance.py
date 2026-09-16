#!/usr/bin/env python3
"""Execute SurfaceRelay repo-local conformance targets through a strict JSON process protocol."""

from __future__ import annotations

import argparse
from dataclasses import dataclass
import json
from pathlib import Path
import subprocess
import sys
from typing import Any, Mapping, Sequence

ROOT = Path(__file__).resolve().parents[1]
if str(ROOT) not in sys.path:
    sys.path.insert(0, str(ROOT))

from scripts.conformance_model import (
    ERROR,
    FAIL,
    NOT_APPLICABLE,
    PASS,
    PROTOCOL_VERSION,
    SelectedCase,
    aggregate_exit_code,
    evaluate_observation,
    select_cases,
    validate_observation,
    validate_v1_conformance_config,
)


REGISTRY_PATH = ROOT / "spec" / "0.1" / "fixtures" / "conformance-scenarios.json"
TARGETS_DIR = ROOT / "conformance" / "targets"
DEFAULT_NOW = "2026-09-14T00:00:00.000Z"


class ConfigurationError(ValueError):
    """Raised when repo-local runner configuration or filters are invalid."""


@dataclass(frozen=True)
class HarnessRun:
    status: str
    observation: dict[str, object] | None
    diagnostics: str
    infrastructure_error: str | None


@dataclass(frozen=True)
class CaseResult:
    target_id: str
    scenario_id: str
    profile: str
    status: str
    observation: dict[str, object] | None
    diagnostics: str
    infrastructure_error: str | None
    mismatches: tuple[str, ...]


def _error_run(message: str, diagnostics: str = "") -> HarnessRun:
    return HarnessRun(
        status=ERROR,
        observation=None,
        diagnostics=diagnostics,
        infrastructure_error=message,
    )


def _timeout_diagnostics(value: object) -> str:
    if value is None:
        return ""
    if isinstance(value, bytes):
        return value.decode(errors="replace")
    return str(value)


def run_harness(
    command: Sequence[str],
    request: Mapping[str, object],
    timeout_seconds: float = 10.0,
) -> HarnessRun:
    """Run one fresh harness process and validate its protocol envelope."""

    try:
        completed = subprocess.run(
            list(command),
            input=json.dumps(request, separators=(",", ":")),
            text=True,
            capture_output=True,
            timeout=timeout_seconds,
            shell=False,
            check=False,
            cwd=ROOT,
        )
    except subprocess.TimeoutExpired as exc:
        return _error_run(
            f"Harness timeout after {timeout_seconds:g} seconds",
            _timeout_diagnostics(exc.stderr),
        )
    except OSError as exc:
        return _error_run(f"Harness process could not start: {exc}")

    diagnostics = completed.stderr
    if completed.returncode != 0:
        return _error_run(
            f"Harness exited with non-zero status {completed.returncode}",
            diagnostics,
        )

    stdout = completed.stdout.strip()
    if not stdout:
        return _error_run("Harness stdout was empty", diagnostics)

    try:
        response = json.loads(stdout)
    except json.JSONDecodeError as exc:
        return _error_run(
            f"Harness stdout was not one valid JSON document: {exc.msg}",
            diagnostics,
        )

    if not isinstance(response, dict):
        return _error_run("Harness protocol response must be a JSON object", diagnostics)

    for field in ("protocolVersion", "requestId", "scenarioId", "targetId", "profile"):
        if response.get(field) != request.get(field):
            return _error_run(
                f"Harness protocol {field} mismatch: expected {request.get(field)!r}, "
                f"observed {response.get(field)!r}",
                diagnostics,
            )

    observation = response.get("observation")
    observation_errors = validate_observation(observation)  # type: ignore[arg-type]
    if observation_errors:
        return _error_run(
            "Invalid observation: " + "; ".join(observation_errors),
            diagnostics,
        )

    return HarnessRun(
        status=PASS,
        observation=dict(observation),  # type: ignore[arg-type]
        diagnostics=diagnostics,
        infrastructure_error=None,
    )


def execute_case(
    case: SelectedCase,
    target: Mapping[str, object],
    timeout_seconds: float = 10.0,
    now: str = DEFAULT_NOW,
) -> CaseResult:
    """Execute one selected target/scenario pair and compute the runner-owned verdict."""

    if not case.applicable:
        return CaseResult(
            target_id=case.target_id,
            scenario_id=case.scenario_id,
            profile=case.profile,
            status=NOT_APPLICABLE,
            observation=None,
            diagnostics="",
            infrastructure_error=None,
            mismatches=(),
        )

    command = target.get("command")
    if not isinstance(command, list) or not command or not all(
        isinstance(part, str) and part for part in command
    ):
        return CaseResult(
            target_id=case.target_id,
            scenario_id=case.scenario_id,
            profile=case.profile,
            status=ERROR,
            observation=None,
            diagnostics="",
            infrastructure_error="Target command is not a valid argv list",
            mismatches=(),
        )

    request = {
        "protocolVersion": PROTOCOL_VERSION,
        "requestId": f"{case.target_id}::{case.scenario_id}",
        "scenarioId": case.scenario_id,
        "targetId": case.target_id,
        "profile": case.profile,
        "now": now,
    }
    harness = run_harness(command, request, timeout_seconds=timeout_seconds)
    if harness.status == ERROR:
        return CaseResult(
            target_id=case.target_id,
            scenario_id=case.scenario_id,
            profile=case.profile,
            status=ERROR,
            observation=None,
            diagnostics=harness.diagnostics,
            infrastructure_error=harness.infrastructure_error,
            mismatches=(),
        )

    observation = harness.observation or {}
    mismatches = tuple(evaluate_observation(case.expectation, observation))
    return CaseResult(
        target_id=case.target_id,
        scenario_id=case.scenario_id,
        profile=case.profile,
        status=FAIL if mismatches else PASS,
        observation=observation,
        diagnostics=harness.diagnostics,
        infrastructure_error=None,
        mismatches=mismatches,
    )


def filter_targets(
    targets: Sequence[Mapping[str, object]], target_id: str | None
) -> list[Mapping[str, object]]:
    ordered = sorted(targets, key=lambda target: str(target.get("targetId", "")))
    if target_id is None:
        return ordered

    filtered = [target for target in ordered if target.get("targetId") == target_id]
    if not filtered:
        raise ConfigurationError(f"Unknown target filter: {target_id}")
    return filtered


def filter_cases(cases: Sequence[SelectedCase], scenario_id: str | None) -> list[SelectedCase]:
    ordered = sorted(cases, key=lambda case: (case.target_id, case.scenario_id))
    if scenario_id is None:
        return ordered

    filtered = [case for case in ordered if case.scenario_id == scenario_id]
    if not filtered:
        raise ConfigurationError(f"Unknown scenario filter: {scenario_id}")
    return filtered


def _sorted_results(results: Sequence[CaseResult]) -> list[CaseResult]:
    return sorted(results, key=lambda result: (result.target_id, result.scenario_id))


def _summary(results: Sequence[CaseResult]) -> dict[str, int]:
    counts = {PASS: 0, FAIL: 0, ERROR: 0, NOT_APPLICABLE: 0}
    for result in results:
        if result.status in counts:
            counts[result.status] += 1
    return counts


def render_json(results: Sequence[CaseResult]) -> str:
    ordered = _sorted_results(results)
    payload = {
        "results": [
            {
                "targetId": result.target_id,
                "scenarioId": result.scenario_id,
                "profile": result.profile,
                "status": result.status,
                "observation": result.observation,
                "diagnostics": result.diagnostics,
                "infrastructureError": result.infrastructure_error,
                "mismatches": list(result.mismatches),
            }
            for result in ordered
        ],
        "summary": _summary(ordered),
    }
    return json.dumps(payload, sort_keys=True, separators=(",", ":"))


def render_text(results: Sequence[CaseResult]) -> str:
    ordered = _sorted_results(results)
    labels = {
        PASS: "PASS",
        FAIL: "FAIL",
        ERROR: "ERROR",
        NOT_APPLICABLE: "N/A ",
    }
    lines = [
        f"{labels.get(result.status, result.status)} {result.target_id} {result.scenario_id}"
        for result in ordered
    ]
    summary = _summary(ordered)
    lines.append(
        "Summary: "
        f"{summary[PASS]} PASS, {summary[FAIL]} FAIL, {summary[ERROR]} ERROR, "
        f"{summary[NOT_APPLICABLE]} NOT_APPLICABLE"
    )
    return "\n".join(lines)


def _load_json_object(path: Path) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except OSError as exc:
        raise ConfigurationError(f"Could not read {path}: {exc}") from exc
    except json.JSONDecodeError as exc:
        raise ConfigurationError(f"Invalid JSON in {path}: {exc.msg}") from exc
    if not isinstance(value, dict):
        raise ConfigurationError(f"Expected JSON object in {path}")
    return value


def load_registry(path: Path = REGISTRY_PATH) -> dict[str, Any]:
    return _load_json_object(path)


def discover_targets(directory: Path = TARGETS_DIR) -> list[dict[str, Any]]:
    if not directory.is_dir():
        raise ConfigurationError(f"Target directory does not exist: {directory}")
    paths = sorted(directory.glob("*.json"))
    if not paths:
        raise ConfigurationError(f"No target manifests found in {directory}")
    targets = [_load_json_object(path) for path in paths]
    return sorted(targets, key=lambda target: str(target.get("targetId", "")))


def main(argv: Sequence[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Run SurfaceRelay executable conformance scenarios.")
    parser.add_argument("--target", dest="target_id")
    parser.add_argument("--scenario", dest="scenario_id")
    parser.add_argument("--json", action="store_true", dest="json_output")
    args = parser.parse_args(list(argv) if argv is not None else None)

    try:
        registry = load_registry(REGISTRY_PATH)
        targets = discover_targets(TARGETS_DIR)
        config_errors = validate_v1_conformance_config(registry, targets)
        if config_errors:
            raise ConfigurationError("; ".join(config_errors))

        selected_targets = filter_targets(targets, args.target_id)
        selected_cases = [
            case
            for target in selected_targets
            for case in select_cases(registry, target)
        ]
        selected_cases = filter_cases(selected_cases, args.scenario_id)
        targets_by_id = {
            str(target["targetId"]): target
            for target in selected_targets
        }
        results = [
            execute_case(case, targets_by_id[case.target_id])
            for case in selected_cases
        ]
    except ConfigurationError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2

    print(render_json(results) if args.json_output else render_text(results))
    return aggregate_exit_code([result.status for result in results])


if __name__ == "__main__":
    raise SystemExit(main())
