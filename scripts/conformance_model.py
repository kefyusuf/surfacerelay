"""Pure model for SurfaceRelay executable conformance selection and verdicts."""

from dataclasses import dataclass
from typing import Any, Mapping


PROTOCOL_VERSION = "0.1"
PASS = "PASS"
FAIL = "FAIL"
ERROR = "ERROR"
NOT_APPLICABLE = "NOT_APPLICABLE"

ALLOWED_EXPECTATION_KEYS = {
    "termination",
    "frameworkDispatchCount",
    "replacementDispatchCount",
}
ALLOWED_TERMINATIONS = {"returned", "threw"}
ALLOWED_OBSERVATION_KEYS = {
    "termination",
    "errorCode",
    "frameworkDispatchCount",
    "replacementDispatchCount",
}
V1_EXECUTABLE_RUNTIME_SCENARIO_IDS = frozenset(
    {
        "BIND-EXACT-TARGET-EXECUTES",
        "BIND-EXPIRED-NOT-EXECUTABLE",
        "BIND-COMPONENT-STALE",
        "BIND-NO-SILENT-RETARGET",
    }
)
V1_TARGET_IDS = frozenset({"browser/livewire", "browser/htmx"})


@dataclass(frozen=True)
class SelectedCase:
    target_id: str
    scenario_id: str
    profile: str
    applicable: bool
    expectation: Mapping[str, Any]
    recommended_code: str | None


def _is_non_negative_int(value: Any) -> bool:
    return isinstance(value, int) and not isinstance(value, bool) and value >= 0


def _is_string_list(value: Any, *, allow_empty: bool = True) -> bool:
    if not isinstance(value, list):
        return False
    if not allow_empty and not value:
        return False
    return all(isinstance(item, str) and item != "" for item in value)


def _duplicates(values: list[str]) -> set[str]:
    seen: set[str] = set()
    duplicates: set[str] = set()
    for value in values:
        if value in seen:
            duplicates.add(value)
        seen.add(value)
    return duplicates


def _validate_expectation(expectation: Any, *, context: str) -> list[str]:
    errors: list[str] = []
    if not isinstance(expectation, Mapping):
        return [f"{context} expectation must be a mapping"]

    unknown = sorted(set(expectation) - ALLOWED_EXPECTATION_KEYS)
    for key in unknown:
        errors.append(f"{context} expectation has unknown key {key!r}")

    termination = expectation.get("termination")
    if termination not in ALLOWED_TERMINATIONS:
        errors.append(
            f"{context} expectation termination must be one of {sorted(ALLOWED_TERMINATIONS)!r}"
        )

    framework_dispatch_count = expectation.get("frameworkDispatchCount")
    if not _is_non_negative_int(framework_dispatch_count):
        errors.append(
            f"{context} expectation frameworkDispatchCount must be a non-negative integer"
        )

    if "replacementDispatchCount" in expectation and not _is_non_negative_int(
        expectation["replacementDispatchCount"]
    ):
        errors.append(
            f"{context} expectation replacementDispatchCount must be a non-negative integer"
        )

    return errors


def _is_positive_control(scenario: Mapping[str, Any]) -> bool:
    expectation = scenario.get("expectation", {})
    return (
        scenario.get("requiresCapabilities", []) == []
        and isinstance(expectation, Mapping)
        and expectation.get("termination") == "returned"
        and _is_non_negative_int(expectation.get("frameworkDispatchCount"))
        and expectation["frameworkDispatchCount"] > 0
    )


def validate_conformance_config(
    registry: Mapping[str, Any], targets: list[Mapping[str, Any]]
) -> list[str]:
    """Validate registry/target structure without performing I/O."""

    errors: list[str] = []

    scenarios = registry.get("scenarios") if isinstance(registry, Mapping) else None
    if not isinstance(scenarios, list):
        errors.append("registry scenarios must be a list")
        scenarios = []

    executable_runtime: list[Mapping[str, Any]] = []
    scenario_ids: set[str] = set()
    known_profiles: set[str] = set()
    known_capabilities: set[str] = set()

    for index, raw_scenario in enumerate(scenarios):
        context = f"scenario[{index}]"
        if not isinstance(raw_scenario, Mapping):
            errors.append(f"{context} must be a mapping")
            continue

        scenario_id = raw_scenario.get("id")
        if not isinstance(scenario_id, str) or not scenario_id:
            errors.append(f"{context} id must be a non-empty string")
        elif scenario_id in scenario_ids:
            errors.append(f"duplicate scenario id: {scenario_id}")
        else:
            scenario_ids.add(scenario_id)
        if isinstance(scenario_id, str) and scenario_id:
            context = f"scenario {scenario_id}"

        if raw_scenario.get("kind") != "runtime" or raw_scenario.get("status") != "executable":
            continue

        executable_runtime.append(raw_scenario)

        profile = raw_scenario.get("profile")
        if not isinstance(profile, str) or not profile:
            errors.append(f"{context} executable runtime scenario requires a non-empty profile")
        else:
            known_profiles.add(profile)

        capabilities = raw_scenario.get("requiresCapabilities", [])
        if not _is_string_list(capabilities):
            errors.append(f"{context} requiresCapabilities must contain only non-empty strings")
        else:
            duplicate_capabilities = _duplicates(capabilities)
            if duplicate_capabilities:
                errors.append(
                    f"{context} requiresCapabilities contains duplicates: "
                    f"{sorted(duplicate_capabilities)!r}"
                )
            known_capabilities.update(capabilities)

        if "expectation" not in raw_scenario:
            errors.append(f"{context} executable runtime scenario requires expectation")
        else:
            errors.extend(
                _validate_expectation(raw_scenario["expectation"], context=context)
            )

    if not isinstance(targets, list):
        errors.append("targets must be a list")
        targets = []

    seen_target_ids: set[str] = set()
    for index, raw_target in enumerate(targets):
        context = f"target[{index}]"
        if not isinstance(raw_target, Mapping):
            errors.append(f"{context} must be a mapping")
            continue

        target_id = raw_target.get("targetId")
        if not isinstance(target_id, str) or not target_id:
            errors.append(f"{context} targetId must be a non-empty string")
        else:
            context = f"target {target_id}"
            if target_id in seen_target_ids:
                errors.append(f"duplicate target id: {target_id}")
            seen_target_ids.add(target_id)

        if raw_target.get("protocolVersion") != PROTOCOL_VERSION:
            errors.append(
                f"{context} protocolVersion must equal {PROTOCOL_VERSION!r}"
            )

        profiles = raw_target.get("profiles")
        if not _is_string_list(profiles):
            errors.append(f"{context} profiles must be a list of non-empty strings")
            valid_profiles: list[str] = []
        else:
            valid_profiles = profiles
            duplicate_profiles = _duplicates(valid_profiles)
            if duplicate_profiles:
                errors.append(
                    f"{context} profiles contains duplicates: {sorted(duplicate_profiles)!r}"
                )
            for profile in valid_profiles:
                if profile not in known_profiles:
                    errors.append(f"{context} claims unknown profile {profile!r}")

        capabilities = raw_target.get("capabilities")
        if not _is_string_list(capabilities):
            errors.append(f"{context} capabilities must be a list of non-empty strings")
            valid_capabilities: list[str] = []
        else:
            valid_capabilities = capabilities
            duplicate_capabilities = _duplicates(valid_capabilities)
            if duplicate_capabilities:
                errors.append(
                    f"{context} duplicate capability claims: {sorted(duplicate_capabilities)!r}"
                )
            for capability in valid_capabilities:
                if capability not in known_capabilities:
                    errors.append(f"{context} claims unknown capability {capability!r}")

        command = raw_target.get("command")
        if not _is_string_list(command, allow_empty=False):
            errors.append(f"{context} command must be a non-empty argv list of non-empty strings")

        for profile in valid_profiles:
            has_positive_control = any(
                scenario.get("profile") == profile and _is_positive_control(scenario)
                for scenario in executable_runtime
            )
            if not has_positive_control:
                errors.append(
                    f"{context} claimed profile {profile!r} has no mandatory positive control"
                )

    return errors


def validate_v1_conformance_config(
    registry: Mapping[str, Any], targets: list[Mapping[str, Any]]
) -> list[str]:
    """Validate structure plus the closed T-701 v1 reference matrix."""

    errors = validate_conformance_config(registry, targets)

    scenarios = registry.get("scenarios") if isinstance(registry, Mapping) else None
    executable_ids: set[str] = set()
    if isinstance(scenarios, list):
        for scenario in scenarios:
            if not isinstance(scenario, Mapping):
                continue
            if scenario.get("kind") != "runtime" or scenario.get("status") != "executable":
                continue
            scenario_id = scenario.get("id")
            if isinstance(scenario_id, str) and scenario_id:
                executable_ids.add(scenario_id)

    for scenario_id in sorted(V1_EXECUTABLE_RUNTIME_SCENARIO_IDS - executable_ids):
        errors.append(f"missing executable runtime scenario for T-701 v1: {scenario_id}")
    for scenario_id in sorted(executable_ids - V1_EXECUTABLE_RUNTIME_SCENARIO_IDS):
        errors.append(f"unexpected executable runtime scenario for T-701 v1: {scenario_id}")

    target_ids: set[str] = set()
    if isinstance(targets, list):
        for target in targets:
            if not isinstance(target, Mapping):
                continue
            target_id = target.get("targetId")
            if isinstance(target_id, str) and target_id:
                target_ids.add(target_id)

    for target_id in sorted(V1_TARGET_IDS - target_ids):
        errors.append(f"missing target for T-701 v1: {target_id}")
    for target_id in sorted(target_ids - V1_TARGET_IDS):
        errors.append(f"unexpected target for T-701 v1: {target_id}")

    return errors


def select_cases(
    registry: Mapping[str, Any], target: Mapping[str, Any]
) -> list[SelectedCase]:
    """Select executable runtime scenarios for claimed profiles in stable ID order."""

    profiles_value = target.get("profiles", [])
    capabilities_value = target.get("capabilities", [])
    profiles = set(profiles_value if isinstance(profiles_value, list) else [])
    capabilities = set(capabilities_value if isinstance(capabilities_value, list) else [])
    target_id = target.get("targetId", "")

    selected: list[SelectedCase] = []
    scenarios = registry.get("scenarios", [])
    if not isinstance(scenarios, list):
        return selected

    for scenario in scenarios:
        if not isinstance(scenario, Mapping):
            continue
        if scenario.get("kind") != "runtime" or scenario.get("status") != "executable":
            continue

        profile = scenario.get("profile")
        if not isinstance(profile, str) or profile not in profiles:
            continue

        required_value = scenario.get("requiresCapabilities", [])
        required = set(required_value if isinstance(required_value, list) else [])
        expectation = scenario.get("expectation", {})
        recommended_code = scenario.get("recommendedCode")

        selected.append(
            SelectedCase(
                target_id=target_id if isinstance(target_id, str) else "",
                scenario_id=scenario.get("id", "") if isinstance(scenario.get("id"), str) else "",
                profile=profile,
                applicable=required.issubset(capabilities),
                expectation=dict(expectation) if isinstance(expectation, Mapping) else {},
                recommended_code=recommended_code if isinstance(recommended_code, str) else None,
            )
        )

    return sorted(selected, key=lambda case: case.scenario_id)


def validate_observation(observation: Mapping[str, Any]) -> list[str]:
    """Validate the bounded raw observation vocabulary emitted by a harness."""

    if not isinstance(observation, Mapping):
        return ["observation must be a mapping"]

    errors: list[str] = []
    unknown = sorted(set(observation) - ALLOWED_OBSERVATION_KEYS)
    for key in unknown:
        errors.append(f"unknown observation field {key!r}")

    termination = observation.get("termination")
    if termination not in ALLOWED_TERMINATIONS:
        errors.append(
            f"observation termination must be one of {sorted(ALLOWED_TERMINATIONS)!r}"
        )

    if "frameworkDispatchCount" not in observation:
        errors.append("observation frameworkDispatchCount is required")
    elif not _is_non_negative_int(observation["frameworkDispatchCount"]):
        errors.append("observation frameworkDispatchCount must be a non-negative integer")

    if "replacementDispatchCount" in observation and not _is_non_negative_int(
        observation["replacementDispatchCount"]
    ):
        errors.append("observation replacementDispatchCount must be a non-negative integer")

    if "errorCode" in observation and not isinstance(observation["errorCode"], str):
        errors.append("observation errorCode must be a string")

    return errors


def evaluate_observation(
    expectation: Mapping[str, Any], observation: Mapping[str, Any]
) -> list[str]:
    """Return normative expectation mismatches; advisory errorCode is ignored."""

    mismatches: list[str] = []
    for key in ("termination", "frameworkDispatchCount", "replacementDispatchCount"):
        if key not in expectation:
            continue
        expected = expectation[key]
        actual = observation.get(key)
        if actual != expected:
            mismatches.append(f"{key}: expected {expected!r}, observed {actual!r}")
    return mismatches


def aggregate_exit_code(statuses: list[str]) -> int:
    """Aggregate per-case statuses into the runner process exit contract."""

    if ERROR in statuses:
        return 2
    if FAIL in statuses:
        return 1
    return 0
