# Repo-local executable conformance

This directory contains the experimental repository-local execution layer for SurfaceRelay conformance. It is implementation and CI infrastructure for this repository; it is **not** a public SDK, third-party certification program, or compatibility badge.

## Semantic source of truth

Canonical scenario semantics live in `spec/0.1/fixtures/conformance-scenarios.json`. Schema scenarios continue to be checked structurally by `scripts/validate.py`. Runtime scenarios with `status: "executable"` are selected and evaluated by `scripts/run_conformance.py`.

A target manifest cannot define its own scenario allowlist. Selection is derived from:

1. executable runtime scenarios in the canonical registry;
2. the profiles claimed by the target;
3. the capabilities claimed by the target.

A required capability adds an obligation only when the target claims it. If a selected scenario requires a capability that the target does not claim, the Python runner emits `NOT_APPLICABLE` **before** spawning a harness process.

## Target manifests

Target manifests live under `conformance/targets/*.json` and use these fields:

- `targetId` — stable repo-local target identifier;
- `protocolVersion` — currently `0.1`;
- `profiles` — conformance profiles implemented by the target;
- `capabilities` — optional obligations supported by the target;
- `command` — argv used to launch one fresh harness process.

The current reference targets are:

- `browser/livewire` — profile `runtime-binding/driver`, capability `lifecycle.component`;
- `browser/htmx` — profile `runtime-binding/driver`, no additional capability claim.

The current profile vocabulary is exactly `runtime-binding/driver`. The current capability vocabulary is exactly `lifecycle.component`.

## Process protocol

One target/scenario pair runs in one fresh subprocess. The runner writes exactly one JSON request document to stdin. The request contains routing and deterministic clock data but **never expected results**. The harness writes exactly one protocol JSON response to stdout; stderr is diagnostic-only.

The bounded raw observation vocabulary is:

- `termination`: `returned` or `threw`;
- optional `errorCode` string;
- `frameworkDispatchCount` non-negative integer;
- optional `replacementDispatchCount` non-negative integer.

Harnesses report observations only. They must not emit interpretation fields such as `passed`, `conformant`, `failClosed`, or `retargetPrevented`.

## Verdict authority

Only `scripts/run_conformance.py` assigns `PASS`, `FAIL`, `ERROR`, or `NOT_APPLICABLE` by comparing raw observations with the structured canonical `expectation` fields.

`recommendedCode` remains advisory. A missing or different raw `errorCode` cannot change a normative PASS when the structured expectation fields match. This preserves the current D-026 boundary while the error-code decision remains proposed.

## Local execution

Build the browser harnesses first:

```text
cd packages/browser-runtime
npm run conformance:build
cd ../..
```

Then run the canonical matrix:

```text
python scripts/run_conformance.py
```

The v1 reference matrix contains four canonical scenarios across the two browser targets. Livewire applies to all four; HTMX does not claim `lifecycle.component`, so `BIND-COMPONENT-STALE` is runner-owned `NOT_APPLICABLE` and its HTMX subprocess is not spawned.

This layer is intentionally repo-local and experimental. It does not define a standalone external conformance specification, plugin discovery mechanism, remote target protocol, or public certification surface.
