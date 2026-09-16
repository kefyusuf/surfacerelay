# SurfaceRelay Conformance Scenarios

SurfaceRelay conformance is a repo-local project contract. It is **not** W3C WebMCP browser certification and it is not a public certification program or SDK compatibility badge.

Canonical scenario IDs live in `spec/0.1/fixtures/conformance-scenarios.json`. There are two executable paths:

- `kind=schema` / `status=executable` scenarios are validated structurally through `scripts/validate.py` and the fixture manifest.
- Four `kind=runtime` / `status=executable` scenarios in profile `runtime-binding/driver` are executed by `scripts/run_conformance.py` through fresh target subprocesses.

The distinction remains normative: **schema-invalid is not the same as schema-valid but runtime-invalid/stale.** JSON Schema proves structure; runtime authority and target identity are execution properties.

## Executable `runtime-binding/driver` profile

T-701 v1 executes exactly these canonical runtime scenarios:

| Scenario | Normative observation | Livewire | HTMX |
| --- | --- | --- | --- |
| `BIND-EXACT-TARGET-EXECUTES` | `returned`, framework dispatch `1`, replacement dispatch `0` | applicable | applicable |
| `BIND-EXPIRED-NOT-EXECUTABLE` | `threw`, framework dispatch `0` | applicable | applicable |
| `BIND-COMPONENT-STALE` | `threw`, framework dispatch `0` | applicable through `lifecycle.component` | `NOT_APPLICABLE` |
| `BIND-NO-SILENT-RETARGET` | `threw`, framework dispatch `0`, replacement dispatch `0` | applicable | applicable |

The canonical two-target matrix is therefore **7 applicable executions + 1 runner-owned `NOT_APPLICABLE`**. A missing capability is resolved before subprocess execution; a harness cannot self-report `NOT_APPLICABLE`.

Current targets are:

- `browser/livewire` — profile `runtime-binding/driver`, capability `lifecycle.component`;
- `browser/htmx` — profile `runtime-binding/driver`, no capability claims.

`recommendedCode` remains advisory. Raw `errorCode` may be observed and reported, but only the structured expectation fields (`termination`, `frameworkDispatchCount`, and where present `replacementDispatchCount`) determine PASS/FAIL. Harnesses emit observations; the Python runner owns verdicts.

## Related browser evidence

T-701 does not replace the broader browser-runtime regression layers:

- **T-604** owns the shared package-level BindingDriver conformance suite: 11 cases for Livewire and the same 11 cases for HTMX. That suite includes malformed target/input and cancellation behavior that is intentionally not promoted into the T-701 canonical runtime registry.
- **T-603** remains separate real-browser evidence for the non-Laravel HTMX fixture using real HTMX and Chromium. It is end-to-end fixture evidence, not a substitute for the repo-local T-701 process matrix.

No T-701 change grants HTMX a `lifecycle.component` capability or changes production browser-driver semantics.

## Structural Binding scenarios

Schema-level binding scenarios remain executable through `scripts/validate.py`:

- `BIND-ACTION-VERSION-EXACT` — a binding without an exact action version is schema-invalid.
- `BIND-LIFECYCLE-UNKNOWN` — an unknown lifecycle value is rejected by schema.
- `BIND-PAGE-VALID`, `BIND-COMPONENT-VALID`, `BIND-SESSION-VALID`, `BIND-PERSISTENT-VALID` — the four lifecycle forms are structurally valid.

The lifecycle meanings remain:

- `page` — one runtime-defined page/surface instance;
- `component` — one exact component instance; rerenders may preserve identity, replacements do not inherit it;
- `session` — trusted runtime session authority, never caller-supplied identity;
- `persistent` — not page/component/session bound, but still revocable and subject to expiry, action-version availability, driver availability, and runtime policy.

## Runtime scenarios still documented, not executable in T-701

These canonical runtime scenarios remain `status=documented`:

- `BIND-ID-UNKNOWN` — binding lookup/not-found behavior;
- `BIND-DRIVER-UNKNOWN` — driver-registry membership and fail-closed unsupported-driver behavior;
- `BIND-ACTION-VERSION-UNAVAILABLE` — exact referenced action/version availability.

They are intentionally outside the v1 `runtime-binding/driver` executable profile. Their package/runtime evidence, where present, does not create a T-701 cross-target conformance claim.

## Trust, Output, and Projection

T-701 makes **no** Trust, Output, or Projection conformance claim.

Existing Laravel trust/idempotency/confirmation tests, output-policy behavior, WebMCP projection tests, and browser cancellation tests remain package/reference-runtime evidence under their own tasks and decisions. They are not promoted into the T-701 executable scenario set by this implementation.

## Runner boundary

Target manifests under `conformance/targets/` declare target identity, protocol version, profiles, capabilities, and argv command. Each applicable target/scenario pair runs in a fresh subprocess using protocol version `0.1`:

1. the runner writes one JSON request to stdin;
2. the harness writes exactly one JSON response to stdout;
3. stderr is diagnostic-only;
4. a non-zero child exit, timeout, malformed protocol response, or invalid observation is `ERROR`;
5. a valid raw observation is evaluated centrally against the canonical structured expectation.

Use `conformance/README.md` for the repo-local protocol and target-manifest details.
