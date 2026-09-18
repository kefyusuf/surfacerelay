# T-702 Adapter Author Guide Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish a documentation-only Adapter Author Guide that lets maintainers add definition/import, runtime/binding, or surface/projection integrations without redefining SurfaceRelay semantics, widening trust, or bypassing the executable conformance authority model.

**Architecture:** The guide is a navigable documentation layer over existing canonical contracts, accepted decisions, and executable conformance semantics; it is not a second specification. Four focused documents under `docs/adapters/` separate entry/navigation, end-to-end author workflow, conformance/evidence guidance, and security/trust boundaries while linking back to canonical repository sources. Implementation remains documentation-only; any newly discovered semantic requirement stops T-702 and opens a separate task/decision gate.

**Tech Stack:** Markdown, repository-relative links, existing SurfaceRelay `spec/0.1/**` contracts, `docs/DECISION-REGISTER.md`, `CONFORMANCE.md`, repo-local Python 3.12 validation/conformance tooling, shell/Python stdlib verification commands.

**Spec:** `docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md`

## Global Constraints

- T-702 is documentation and integration guidance only. It does not introduce a new adapter SDK, runtime abstraction, protocol, capability, error enum, certification program, or plugin architecture.
- The Adapter Author Guide is explanatory and non-authoritative relative to canonical contracts, accepted decisions, and executable conformance semantics. If guide prose/examples conflict with a canonical artifact, the canonical artifact wins.
- D-062 remains `PROPOSED` during documentation implementation. This plan does not authorize its promotion.
- D-026 remains `PROPOSED`. T-702 must not promote provisional binding failure-code names into a global normative error enum.
- Definition/import, runtime/binding, and surface/projection are author-facing responsibility categories only; do not create a universal `Adapter` interface, plugin loader, shared lifecycle abstraction, or cross-role registry.
- Trusted actor/tenant/record/selection/confirmation/binding authority cannot be manufactured from caller input or generic metadata.
- Action exposure remains explicit. Discovery permission does not imply invocation authorization.
- Runtime/binding guidance must preserve exact issued targets, explicit driver support, cumulative validity/lifecycle/expiry rules, deterministic bounded mapping, no silent retargeting, and precisely bounded cancellation guarantees.
- Profile/capability semantics must follow D-059 through D-061 exactly: targets advertise only real capabilities; the runner owns canonical scenario selection, applicability, and `PASS`/`FAIL`/`ERROR`/`NOT_APPLICABLE`; harnesses emit bounded raw observations only.
- Capability declarations may increase obligations and cannot be used as scenario-suppression escape hatches.
- Verified conformance claims must be bounded to the actual profile, target, declared capabilities, runner/repository revision, and executable evidence. Do not create a badge/certification authority or global compatibility claim.
- Examples are illustrative/non-normative. They must distinguish portable invariants from Livewire/HTMX-specific implementation techniques.
- T-703 Laravel MCP projection and T-704 OpenAPI import mapping remain `TODO / NOT STARTED`.
- Do not change production semantics or canonical conformance semantics. In particular, implementation must not require semantic changes under:

```text
packages/browser-runtime/src/**
packages/laravel/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

- If any documentation step appears to require a change in those paths, stop T-702 and open a new scope/decision gate rather than broadening this plan.
- This plan does **not** itself authorize implementation. Execution starts only after a separate explicit user approval.

---

## File Structure

### New adapter-author documentation

- `docs/adapters/security.md` — mandatory trust/security invariants: explicit exposure, discovery-vs-invocation, trusted context, exact-target authority, fail-closed rules, and review checklist.
- `docs/adapters/conformance.md` — profile/capability model, runner/harness ownership, current repo-local process protocol, evidence scope, bounded claim wording, and versioning distinctions.
- `docs/adapters/author-guide.md` — complete author workflow, role taxonomy, responsibility matrix, runtime/projection/import guidance, examples, and semantic-change stop conditions.
- `docs/adapters/README.md` — compact entry point, role picker, navigation, source-of-truth warning, and canonical reference map.

### Existing documentation modified during implementation

- `README.md` — add one discoverable Adapter Authoring entry under the existing Development/start-here area without changing product scope claims.
- `TASKS.md` — after successful verification only, move T-702 from `DESIGN APPROVED / IMPLEMENTATION NOT STARTED` to `IMPLEMENTED / REVIEW PENDING`, list produced docs/evidence, keep D-062 `PROPOSED`, and keep T-703/T-704 not started.
- `STATUS.md` — after successful verification only, record the same bounded T-702 implementation state and verification evidence.
- `REVIEW_REQUEST.md` — after successful verification only, present the documentation-only review surface, changed files, commands/results, D-062 status, and explicit non-changes.

### Canonical/reference files that are read-only for T-702 implementation

- `docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md`
- `docs/DECISION-REGISTER.md`
- `docs/GLOSSARY.md`
- `docs/ARCHITECTURE.md`
- `spec/0.1/action-definition.schema.json`
- `spec/0.1/runtime-binding.schema.json`
- `spec/0.1/invocation.schema.json`
- `spec/0.1/action-result.schema.json`
- `spec/0.1/fixtures/conformance-scenarios.json`
- `CONFORMANCE.md`
- `conformance/README.md`
- `conformance/targets/browser-livewire.json`
- `conformance/targets/browser-htmx.json`
- `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`
- `docs/superpowers/plans/2026-09-15-executable-conformance-runner.md`

---

## Preflight Gate Before Any Implementation Task

The implementer must prove that execution is occurring on the approved T-702 branch and that this plan is the only implementation authorization artifact currently present.

```bash
test "$(git branch --show-current)" = "feat/t-702-adapter-author-guide"
test -z "$(git status --short)"
test -f docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md
test -f docs/superpowers/plans/2026-09-16-adapter-author-guide.md
test ! -e docs/adapters
```

Expected before implementation begins:

```text
branch: feat/t-702-adapter-author-guide
working tree: clean
design: present
plan: present
docs/adapters/: absent
```

Then read the authoritative inputs before authoring prose:

```bash
sed -n '1,260p' docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md
sed -n '261,620p' docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md
cat CONFORMANCE.md
cat conformance/README.md
grep -E '^\| D-(002|004|005|006|007|010|011|012|016|022|023|024|025|026|027|028|029|030|031|032|035|036|037|038|039|040|041|042|043|048|049|050|051|052|053|054|055|056|057|058|059|060|061|062) ' docs/DECISION-REGISTER.md
```

Expected: the implementation starts from the approved design, accepted D-059/D-060/D-061 conformance authority, `D-026 PROPOSED`, and `D-062 PROPOSED`.

---

### Task 1: Security and Trust Boundary Guide

**Files:**
- Create: `docs/adapters/security.md`
- Read only: `docs/DECISION-REGISTER.md`, `docs/GLOSSARY.md`, `docs/THREAT-MODEL.md`, `spec/0.1/runtime-binding.schema.json`, `spec/0.1/invocation.schema.json`

**Interfaces:**
- Produces: the canonical author-facing trust checklist and terminology that Tasks 3 and 4 link to.
- Consumes: accepted trust/exposure/runtime decisions, especially D-007, D-010, D-011, D-016, D-022–D-025, D-027–D-032, D-035–D-043, and D-048–D-058.
- Must not produce: new authorization rules, new trusted-context fields, new error codes, or adapter-local security semantics.

- [ ] **Step 1: Create the security guide with an explicit authority banner**

Create `docs/adapters/security.md` beginning with this contract:

```markdown
# Adapter Security and Trust Boundaries

> This guide explains existing SurfaceRelay contracts and accepted decisions. It is not an independent specification. If this document conflicts with `spec/0.1/**`, an accepted decision, or executable conformance semantics, the canonical artifact governs.

Security and trust behavior is part of adapter correctness, not an optional hardening layer.
```

The opening must link repository-relatively to:

```text
../DECISION-REGISTER.md
../THREAT-MODEL.md
../../spec/0.1/runtime-binding.schema.json
../../spec/0.1/invocation.schema.json
```

- [ ] **Step 2: Document the five mandatory trust boundaries without inventing new policy**

Add these exact conceptual sections:

```markdown
## 1. Explicit exposure
## 2. Discovery is not invocation authorization
## 3. Caller input is not trusted runtime context
## 4. Exact runtime targets must not silently retarget
## 5. Unknown or ambiguous authority fails closed
```

Each section must state the owning invariant and point to its accepted decision(s):

- explicit exposure: D-010;
- discovery vs invocation: D-011;
- caller input vs trusted context: D-007, D-027–D-029, D-048–D-052;
- exact issued target/no retarget: D-016, D-022–D-025, D-039, D-053–D-055;
- fail-closed ambiguity: D-016, D-025, D-027, D-035, D-039–D-043, D-053–D-056.

The caller-input section must explicitly call out that parsed `tenant_id`, actor IDs, record IDs, selected IDs, arbitrary confirmation flags/tokens outside defined receipt semantics, and host-target identifiers do not become trusted authority merely because an adapter can parse them.

- [ ] **Step 3: Add authority-widening anti-patterns using labeled non-normative examples**

Add a visible note before all examples:

```markdown
> **Illustrative examples — non-normative.** The owning canonical contract/decision, not the example syntax, defines the requirement.
```

Include these three forms:

```text
Correct
host capability is not implemented
→ do not advertise the capability
→ let the runner compute applicability
```

```text
Incorrect
host capability is not implemented
→ advertise it anyway
→ suppress the mandatory scenario in the harness
```

```text
Dangerous
caller sends tenant_id
→ adapter copies tenant_id into trusted runtime context
```

Also include a runtime-target dangerous example:

```text
Dangerous
issued target is stale/missing
→ adapter searches for a similar current target
→ invocation continues against the replacement
```

Follow it with the required behavior:

```text
Required
issued target is stale/missing
→ reject/fail closed
→ do not rediscover or substitute a replacement target
```

- [ ] **Step 4: Add the adapter security review checklist**

The checklist must include all of these review questions:

```markdown
- [ ] Is exposure explicit rather than convention-based/expose-all?
- [ ] Does discovery remain separate from invocation authorization?
- [ ] Does trusted actor/tenant/UI context come only from established trusted runtime sources?
- [ ] Are caller payload and generic metadata prevented from manufacturing authority?
- [ ] Does the runtime/binding path resolve the exact issued target only?
- [ ] Are unknown driver, stale target, unsupported mapping, missing authority, and ambiguous state rejected rather than guessed?
- [ ] Does the adapter preserve the normal validation/authorization/confirmation/execution path?
- [ ] Are cancellation guarantees stated only up to the actual supported dispatch frontier?
- [ ] Are framework/protocol-specific mechanics clearly separated from portable SurfaceRelay invariants?
- [ ] Did documentation avoid inventing a new global error vocabulary or promoting D-026?
```

- [ ] **Step 5: Verify the security guide is self-consistent and its links resolve**

Run:

```bash
python - <<'PY'
from pathlib import Path
import re

path = Path('docs/adapters/security.md')
text = path.read_text(encoding='utf-8')
required = [
    'Explicit exposure',
    'Discovery is not invocation authorization',
    'Caller input is not trusted runtime context',
    'silently retarget',
    'fail closed',
    'D-026',
    'Illustrative examples',
]
for needle in required:
    assert needle.lower() in text.lower(), needle

for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', text):
    if target.startswith(('http://', 'https://', 'mailto:', '#')):
        continue
    resolved = (path.parent / target.split('#', 1)[0]).resolve()
    assert resolved.exists(), f'broken link: {target} -> {resolved}'
print('security guide checks: PASS')
PY
```

Expected:

```text
security guide checks: PASS
```

- [ ] **Step 6: Commit Task 1**

```bash
git add docs/adapters/security.md
git commit -m "docs(adapters): add trust and security guide"
```

---

### Task 2: Conformance, Evidence, and Compatibility Claim Guide

**Files:**
- Create: `docs/adapters/conformance.md`
- Read only: `CONFORMANCE.md`, `conformance/README.md`, `conformance/targets/browser-livewire.json`, `conformance/targets/browser-htmx.json`, `spec/0.1/fixtures/conformance-scenarios.json`, `scripts/run_conformance.py`, `scripts/conformance_model.py`, `docs/DECISION-REGISTER.md`

**Interfaces:**
- Produces: author-facing explanation of profile/capability claims, runner/harness authority, evidence recording, bounded compatibility wording, and version dimensions.
- Consumes: D-059/D-060/D-061 and the implemented T-701 repo-local runner.
- Must not produce: new profiles, capabilities, scenarios, target discovery, public certification, or alternate verdict logic.

- [ ] **Step 1: Create the conformance guide with the repo-local/non-certification boundary first**

Create `docs/adapters/conformance.md` with this opening:

```markdown
# Adapter Conformance and Evidence

> SurfaceRelay conformance is currently a repository-local project contract. It is not a public certification program, compatibility badge, remote target protocol, or standalone adapter SDK.

Canonical runtime scenario semantics live in `../../spec/0.1/fixtures/conformance-scenarios.json`. The runner owns scenario selection, applicability, and verdicts; a harness emits bounded raw observations only.
```

Link to:

```text
../../CONFORMANCE.md
../../conformance/README.md
../DECISION-REGISTER.md
../../spec/0.1/fixtures/conformance-scenarios.json
```

- [ ] **Step 2: Freeze the author-facing profile/capability vocabulary to the current implemented facts**

Document the definitions exactly at the conceptual level:

```text
profile
= atomic conformance claim boundary

capability
= declared functionality/environment property used for applicability

scenario applicability
= runner decision

PASS / FAIL / ERROR / NOT_APPLICABLE
= runner-owned result semantics
```

Then state the current repo-local v1 facts without generalizing them into a public standard:

```text
profile: runtime-binding/driver
reference target: browser/livewire
  capability: lifecycle.component
reference target: browser/htmx
  additional capabilities: none
```

State explicitly:

1. a target declares only capabilities it actually provides;
2. capability claims may add mandatory obligations;
3. a target/harness cannot provide a private scenario allowlist;
4. a harness cannot self-report `NOT_APPLICABLE`;
5. inability to execute a runner-selected applicable mandatory scenario is an `ERROR`/infrastructure condition, not local N/A;
6. every executable claimed profile requires a mandatory positive scenario.

- [ ] **Step 3: Document the harness boundary and process protocol as implemented, not as a new public API**

Include this responsibility diagram:

```text
adapter / production runtime
            │
            ▼
        thin harness
            │
      raw observations
            │
            ▼
          runner
            │
 applicability + verdict
```

Document current T-701 process facts:

```text
one target/scenario pair = one fresh subprocess
stdin  = one JSON request document
stdout = exactly one JSON protocol response
a child stderr stream = diagnostics only
timeout = 10 seconds
```

Document bounded raw observation vocabulary:

```text
termination: returned | threw
errorCode?: string
frameworkDispatchCount: non-negative integer
replacementDispatchCount?: non-negative integer
```

State that harnesses must not emit interpretation fields such as `passed`, `conformant`, `failClosed`, `retargetPrevented`, or runner verdicts.

- [ ] **Step 4: Document evidence scope and bounded verified-claim wording**

Require authors to record at least:

```text
repository/adapter revision
claimed profile
declared capabilities
runner/repository revision
scenario result summary
host framework/protocol version support
```

Explain that a material adapter change invalidates reuse of old evidence for a fresh claim until conformance is rerun.

Include this approved bounded claim form:

```text
Conforms to SurfaceRelay profile `runtime-binding/driver`
for target `<target-id>` under the capabilities declared by that target,
verified by the repository conformance runner at the recorded revision.
```

Immediately state that wording such as `fully SurfaceRelay compatible` is unsupported when evidence covers only a bounded profile.

- [ ] **Step 5: Separate all five version/compatibility dimensions**

Document these as independent values:

```text
SurfaceRelay contract/spec revision
adapter/package version
claimed conformance profile
conformance runner/repository revision
host framework/protocol version support
```

State that T-702 introduces no version negotiation, compatibility solver, release matrix service, badge, signing, or attestation system.

- [ ] **Step 6: Explain failure/error categories without freezing D-026**

Use conceptual categories only:

```text
unsupported adapter/host behavior
invalid or stale binding/target state
unavailable capability
invalid trusted-context preconditions
host/runtime execution failure
cancellation/timeout at a defined boundary
protocol/normalization failure in a harness or projection
```

Then state:

```text
Where an existing contract defines exact result/error semantics, use it.
Where vocabulary is extensible or provisional, do not invent/freeze a new global name.
D-026 remains PROPOSED.
```

- [ ] **Step 7: Include current repo-local verification commands with a scope warning**

Document the currently implemented reference-runner flow:

```bash
cd packages/browser-runtime
npm run conformance:build
cd ../..
python scripts/run_conformance.py
```

Label it as **current repository-local reference verification**, not an external stable SDK/remote harness contract.

Expected current canonical reference matrix from T-701:

```text
7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

- [ ] **Step 8: Verify conformance terminology and links**

Run:

```bash
python - <<'PY'
from pathlib import Path
import re

path = Path('docs/adapters/conformance.md')
text = path.read_text(encoding='utf-8')
required = [
    'runtime-binding/driver',
    'lifecycle.component',
    'browser/livewire',
    'browser/htmx',
    'PASS / FAIL / ERROR / NOT_APPLICABLE',
    'raw observations',
    '10 seconds',
    'D-026 remains PROPOSED',
    'repository-local',
]
for needle in required:
    assert needle.lower() in text.lower(), needle

for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', text):
    if target.startswith(('http://', 'https://', 'mailto:', '#')):
        continue
    resolved = (path.parent / target.split('#', 1)[0]).resolve()
    assert resolved.exists(), f'broken link: {target} -> {resolved}'
print('conformance guide checks: PASS')
PY
```

Expected:

```text
conformance guide checks: PASS
```

- [ ] **Step 9: Commit Task 2**

```bash
git add docs/adapters/conformance.md
git commit -m "docs(adapters): document conformance and evidence"
```

---

### Task 3: End-to-End Adapter Author Guide

**Files:**
- Create: `docs/adapters/author-guide.md`
- Read only: `docs/adapters/security.md`, `docs/adapters/conformance.md`, `docs/ARCHITECTURE.md`, `docs/GLOSSARY.md`, `docs/DECISION-REGISTER.md`, `spec/0.1/action-definition.schema.json`, `spec/0.1/runtime-binding.schema.json`, existing Livewire/HTMX implementation/reference documentation.

**Interfaces:**
- Produces: the main author workflow and role-specific implementation guidance.
- Consumes: Task 1 trust boundaries and Task 2 conformance/evidence model.
- Must not produce: a universal adapter interface, framework-independent host target schema, new projection semantics, new import mapping rules, or T-703/T-704 implementation guidance pretending those tasks are complete.

- [ ] **Step 1: Create the main guide with authority and audience boundaries**

Start `docs/adapters/author-guide.md` with:

```markdown
# Adapter Author Guide

> This guide explains how to integrate with existing SurfaceRelay contracts. It is not a second specification, adapter SDK, or plugin API. Canonical contracts, accepted decisions, and executable conformance semantics take precedence over this prose and all examples.

This guide is for maintainers implementing an integration boundary. It does not teach the host framework itself.
```

Link immediately to:

```text
README.md
security.md
conformance.md
../ARCHITECTURE.md
../DECISION-REGISTER.md
../../spec/0.1/action-definition.schema.json
../../spec/0.1/runtime-binding.schema.json
```

- [ ] **Step 2: Add the three-role taxonomy without creating a common interface**

Document exactly these author-facing categories:

```text
definition / import adapter
runtime / binding adapter
surface / projection adapter
```

For each role, include:

- conceptual flow;
- owned responsibilities;
- explicit non-responsibilities;
- relevant canonical sources;
- a warning that the taxonomy is organizational guidance, not a new `Adapter` interface.

Use these flows:

```text
external description
        │
        ▼
import adapter
        │
        ▼
SurfaceRelay-neutral Action Definition
```

```text
Action + RuntimeBinding
          │
          ▼
framework/runtime BindingDriver
          │
          ▼
exact host target
```

```text
SurfaceRelay Action
        │
        ▼
projection adapter
        │
        ▼
external protocol/tool representation
```

The import section must state that T-704 OpenAPI mapping rules do not exist yet. The projection section must state that T-703 Laravel MCP support is not implemented by T-702.

- [ ] **Step 3: Add the responsibility matrix from the approved design**

Include a matrix preserving this ownership boundary:

| Concern | Canonical/core/runner authority | Adapter/harness responsibility |
|---|---|---|
| Action semantics | canonical contracts | translate without redefining |
| Runtime target shape | binding contract + driver-owned contract | validate exact host-specific shape |
| Caller business input | validation pipeline | map only validated input |
| Trusted actor/tenant/UI context | trusted runtime services/context | consume; never manufacture from caller input |
| Discovery/exposure | explicit exposure rules | project only explicitly exposed actions |
| Invocation authorization | normal invocation pipeline | must not bypass |
| Scenario selection | conformance runner | none |
| Capability advertisement | runner validates/uses claim | declare only real capabilities |
| Applicability | conformance runner | none |
| PASS/FAIL verdict | conformance runner | none |
| Raw runtime observation | runner consumes | harness captures bounded facts |
| Unsupported ambiguity | fail-closed contract | report/reject explicitly |

Do not broaden any row during prose editing.

- [ ] **Step 4: Add the complete 12-step minimal author workflow**

Document this sequence verbatim at the conceptual level:

```text
1. Identify the integration boundary.
2. Classify the role: import, runtime/binding, projection, or a package containing clearly separated roles.
3. Read the canonical contracts and accepted decisions relevant to that role.
4. Identify the existing profile, if any, that the adapter intends to claim.
5. Determine the capabilities the implementation actually provides.
6. Implement host/protocol translation without widening authority.
7. Preserve exact-target, trusted-context, exposure, authorization, and fail-closed rules.
8. Normalize only what the existing contracts require; keep host-owned semantics host-owned.
9. Wire a conformance harness when an executable profile exists.
10. Run every runner-selected applicable mandatory scenario.
11. Fix FAIL/ERROR results instead of suppressing scenarios or changing applicability locally.
12. Publish only claims supported by the relevant executable evidence.
```

Link steps 6–8 to `security.md` and steps 9–12 to `conformance.md`.

- [ ] **Step 5: Add runtime/binding-specific author guidance**

The runtime/binding section must preserve these invariants:

```text
RuntimeBinding references exact Action identity/version and one exact issued binding instance.
driver resolution is explicit; unknown driver support fails closed.
the driver validates its own target shape.
execution resolves the exact issued target only.
lifecycle and expiry remain cumulative validity conditions.
stale targets do not silently retarget.
Action input mapping is deterministic and bounded by the driver/host contract.
unsupported mapping/target states fail closed.
cancellation claims stop at the actual supported framework-dispatch boundary.
```

Use Livewire and HTMX only as contrasting reference techniques, never as a universal target model.

- [ ] **Step 6: Add the portable-invariant/reference-technique comparison**

Include at least this table:

| Portable invariant | Livewire reference technique | HTMX reference technique |
|---|---|---|
| Exact issued target must not silently retarget | exact component identity through supported Livewire API | exact rendered source identity + declarative request contract |
| Input mapping must be deterministic | server-issued positional call plan | named allowlisted Action-input mapping |
| Pre-dispatch cancellation boundary must be explicit | component-scoped action interception before send frontier | no-dispatch only before `htmx.ajax()` frontier |
| Host-owned lifecycle semantics stay host-owned | component-oriented identity/lifecycle | page-scoped rendered source identity |

Precede the table with:

```markdown
> Reference techniques are illustrative/non-normative. Future adapters may satisfy the portable invariant differently.
```

- [ ] **Step 7: Add projection-specific author guidance**

State all of the following:

```text
projection is representation, not new core semantics;
external tool identity is deterministic only where an accepted mapping defines it;
protocol annotations project existing semantics only through accepted mappings;
discovery remains separate from invocation authorization;
projection must not create an agent-only business execution path;
unsupported protocol fields are not populated by speculative inference.
```

Reference existing WebMCP projection as precedent without presenting T-703 MCP support as implemented.

- [ ] **Step 8: Add import/definition-specific author guidance**

State all of the following:

```text
external descriptions are translation input, not trusted runtime authority;
only semantics representable by existing Action Definition contracts may be produced;
ambiguous/lossy mappings that change meaning are rejected or left unsupported;
runtime binding information is not smuggled into Action Definition;
host/protocol-specific metadata stays secondary/non-authoritative unless an existing canonical field owns it.
```

State explicitly that T-702 does not define T-704 OpenAPI mapping rules.

- [ ] **Step 9: Add an author stop-condition section that prevents documentation-driven architecture drift**

Add:

```markdown
## Stop and open a new gate when

Stop T-702 implementation instead of extending this guide if authoring reveals a need for:

- a new canonical Action Definition or RuntimeBinding field;
- a universal Adapter interface or plugin lifecycle;
- a new trusted-context authority source;
- a new profile, capability, or canonical conformance scenario;
- changed runner selection/applicability/verdict semantics;
- a new global failure-code enum;
- changed Livewire/HTMX production behavior;
- T-703 Laravel MCP implementation;
- T-704 OpenAPI import mapping;
- standalone public-spec extraction or certification infrastructure.
```

- [ ] **Step 10: Add a concise author review checklist**

The checklist must prove role separation, canonical-source review, trust preservation, exact-target behavior, truthful capability claims, runner-owned verdicts, bounded evidence claims, and non-normative examples.

- [ ] **Step 11: Verify author-guide coverage and links**

Run:

```bash
python - <<'PY'
from pathlib import Path
import re

path = Path('docs/adapters/author-guide.md')
text = path.read_text(encoding='utf-8')
required = [
    'definition / import',
    'runtime / binding',
    'surface / projection',
    'Identify the integration boundary',
    'Scenario selection',
    'Trusted actor/tenant/UI context',
    'exact issued target',
    'portable invariant',
    'Livewire',
    'HTMX',
    'T-703',
    'T-704',
    'Stop and open a new gate',
]
for needle in required:
    assert needle.lower() in text.lower(), needle

for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', text):
    if target.startswith(('http://', 'https://', 'mailto:', '#')):
        continue
    resolved = (path.parent / target.split('#', 1)[0]).resolve()
    assert resolved.exists(), f'broken link: {target} -> {resolved}'
print('author guide checks: PASS')
PY
```

Expected:

```text
author guide checks: PASS
```

- [ ] **Step 12: Commit Task 3**

```bash
git add docs/adapters/author-guide.md
git commit -m "docs(adapters): add end-to-end author guide"
```

---

### Task 4: Adapter Documentation Entry Point and Canonical Reference Map

**Files:**
- Create: `docs/adapters/README.md`
- Read only: `docs/adapters/author-guide.md`, `docs/adapters/conformance.md`, `docs/adapters/security.md`, `docs/ARCHITECTURE.md`, `docs/DECISION-REGISTER.md`, `docs/GLOSSARY.md`, `CONFORMANCE.md`, `spec/0.1/**`

**Interfaces:**
- Produces: the stable human entry point for adapter authors and reviewers.
- Consumes: Tasks 1–3.
- Must not produce: a duplicate specification or long-form repetition of the child documents.

- [ ] **Step 1: Create a short source-of-truth warning and role picker**

Create `docs/adapters/README.md` starting with:

```markdown
# Adapter Authoring

SurfaceRelay adapter documentation explains how to extend existing integration boundaries without redefining the core model.

> **Source of truth:** canonical contracts, accepted decisions, and executable conformance semantics govern. These guides are explanatory and non-normative.
```

Add a role picker:

| You are adding… | Start with |
|---|---|
| external-definition import/translation | `author-guide.md` → Definition / import |
| runtime/framework execution binding | `author-guide.md` → Runtime / binding, then `security.md` |
| external protocol/tool projection | `author-guide.md` → Surface / projection, then `security.md` |
| executable profile/capability evidence | `conformance.md` |
| architecture/security review | `security.md` + responsibility matrix in `author-guide.md` |

All file references in the table must be Markdown links.

- [ ] **Step 2: Add the recommended author reading order**

Use this bounded flow:

```text
1. Read this entry point and classify the integration role.
2. Read author-guide.md for the workflow and ownership matrix.
3. Read security.md before wiring any trusted/runtime behavior.
4. Read conformance.md before claiming a profile/capability or publishing compatibility evidence.
5. Follow the linked canonical source for exact contract semantics.
```

- [ ] **Step 3: Add a concise canonical reference map**

Include links and one-line responsibility descriptions for:

```text
../../spec/0.1/action-definition.schema.json
../../spec/0.1/runtime-binding.schema.json
../../spec/0.1/invocation.schema.json
../../spec/0.1/action-result.schema.json
../../spec/0.1/fixtures/conformance-scenarios.json
../DECISION-REGISTER.md
../GLOSSARY.md
../ARCHITECTURE.md
../../CONFORMANCE.md
../../conformance/README.md
```

The map must state that `docs/DECISION-REGISTER.md` is the status authority for `ACCEPTED` versus `PROPOSED` decisions and that `D-026`/`D-062` remain proposed during T-702 implementation.

- [ ] **Step 4: Add a boundary summary, not a second spec**

Keep this summary to these points:

```text
translate; do not redefine core semantics
consume trusted context; do not manufacture it
resolve exact issued targets; do not silently retarget
project explicitly exposed actions; discovery is not invocation authority
advertise only real capabilities
let the runner select/apply/evaluate mandatory conformance scenarios
publish only revision-bounded evidence claims
open a new gate for new semantics
```

- [ ] **Step 5: Verify all four adapter docs as one navigable set**

Run:

```bash
python - <<'PY'
from pathlib import Path
import re

root = Path('docs/adapters')
expected = {
    root / 'README.md',
    root / 'author-guide.md',
    root / 'conformance.md',
    root / 'security.md',
}
assert {p for p in root.glob('*.md')} == expected

for path in sorted(expected):
    text = path.read_text(encoding='utf-8')
    for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', text):
        if target.startswith(('http://', 'https://', 'mailto:', '#')):
            continue
        relative = target.split('#', 1)[0]
        if not relative:
            continue
        resolved = (path.parent / relative).resolve()
        assert resolved.exists(), f'{path}: broken link {target} -> {resolved}'
print('adapter docs file-set/link checks: PASS')
PY
```

Expected:

```text
adapter docs file-set/link checks: PASS
```

- [ ] **Step 6: Commit Task 4**

```bash
git add docs/adapters/README.md
git commit -m "docs(adapters): add authoring entry point"
```

---

### Task 5: Repository Navigation and Documentation-Only Regression Verification

**Files:**
- Modify: `README.md`
- Verify only: all new `docs/adapters/*.md`, canonical/read-only paths, repository validation tooling.

**Interfaces:**
- Produces: discoverability from the repository start-here flow and documented regression evidence.
- Consumes: complete four-file adapter guide set from Tasks 1–4.
- Must not produce: product-scope changes, production code changes, canonical schema changes, target-manifest changes, or conformance verdict changes.

- [ ] **Step 1: Add Adapter Authoring to the existing Development start list**

In `README.md`, keep the current start-here list and add a single discoverable entry:

```markdown
6. [`docs/adapters/README.md`](docs/adapters/README.md) — authoring new import, runtime/binding, or projection integrations without redefining core semantics.
```

Do not rewrite the repository positioning or claim a public adapter SDK/certification surface.

- [ ] **Step 2: Run the adapter-doc link/file-set verifier again after root navigation is added**

Run the same Task 4 Python verifier and require:

```text
adapter docs file-set/link checks: PASS
```

- [ ] **Step 3: Run existing structural validation**

```bash
python scripts/validate.py
```

Expected:

```text
all existing repository structural checks pass
```

No new production test is required solely for prose, but existing validation must remain green.

- [ ] **Step 4: Re-run the current executable conformance matrix as semantic non-regression evidence**

Build current browser harnesses and run the existing T-701 matrix without changing them:

```bash
cd packages/browser-runtime
npm run conformance:build
cd ../..
python scripts/run_conformance.py
```

Expected canonical summary:

```text
7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

If this differs, do not edit conformance semantics from T-702; investigate whether the branch/environment is stale and stop the task if semantic changes would be required.

- [ ] **Step 5: Prove forbidden implementation paths were not changed by T-702**

Run:

```bash
BASE="$(git merge-base main HEAD)"
for path in \
  packages/browser-runtime/src \
  packages/laravel/src \
  spec/0.1 \
  conformance/targets \
  scripts/conformance_model.py \
  scripts/run_conformance.py
do
  if ! git diff --quiet "$BASE" HEAD -- "$path"; then
    echo "T-702 scope violation: $path changed" >&2
    exit 1
  fi
done
echo "T-702 forbidden-path check: PASS"
```

Expected:

```text
T-702 forbidden-path check: PASS
```

- [ ] **Step 6: Inspect documentation diff for accidental architecture claims**

Run:

```bash
git diff "$(git merge-base main HEAD)" HEAD -- README.md docs/adapters docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md docs/superpowers/plans/2026-09-16-adapter-author-guide.md docs/DECISION-REGISTER.md
```

Review specifically for:

```text
no new universal Adapter interface
no new profile/capability/scenario vocabulary
no harness-owned applicability/verdict claims
no caller-input-to-trusted-context path
no discovery-equals-authorization claim
no silent retarget fallback
no D-026 promotion
no D-062 promotion
no T-703/T-704 implementation claim
no public certification/badge claim
```

- [ ] **Step 7: Commit Task 5**

```bash
git add README.md
git commit -m "docs(readme): link adapter authoring guide"
```

---

### Task 6: Record T-702 Implementation Evidence for Review — Do Not Close or Promote Decisions

**Files:**
- Modify: `TASKS.md`
- Modify: `STATUS.md`
- Modify: `REVIEW_REQUEST.md`
- Read only: `docs/DECISION-REGISTER.md`

**Interfaces:**
- Produces: review-ready project tracking for the completed documentation implementation.
- Consumes: verified results from Task 5.
- Must not produce: T-702 final closure before review, D-062 acceptance, D-026 acceptance, T-703/T-704 start, or semantic code/spec changes.

- [ ] **Step 1: Update T-702 tracking to implementation-complete/review-pending only**

In `TASKS.md`, preserve the existing design record and change the current implementation state to the equivalent of:

```text
T-702: IMPLEMENTED / REVIEW PENDING
Branch: feat/t-702-adapter-author-guide
Design: APPROVED
Plan: docs/superpowers/plans/2026-09-16-adapter-author-guide.md
Adapter docs:
  docs/adapters/README.md
  docs/adapters/author-guide.md
  docs/adapters/conformance.md
  docs/adapters/security.md
D-062: PROPOSED
D-026: PROPOSED
Production changes: NONE
Canonical spec changes: NONE
Conformance semantic changes: NONE
T-703/T-704: NOT STARTED
```

Record the actual verification results from Task 5, including structural validation, executable matrix summary, link/file-set checks, and forbidden-path check.

Do **not** write `DONE / REVIEWED / MERGED / MAIN REVALIDATED` until a later review/merge gate actually proves those states.

- [ ] **Step 2: Update STATUS.md with the same bounded state**

Record:

```text
current task: T-702 Adapter Author Guide
state: IMPLEMENTED / REVIEW PENDING
scope: documentation only
D-062: PROPOSED
D-026: PROPOSED
next tasks T-703/T-704: not started
```

Include the exact commands/results actually observed during Task 5.

- [ ] **Step 3: Replace REVIEW_REQUEST.md with a T-702-focused review request**

The review request must direct the reviewer to inspect:

```text
docs/adapters/README.md
docs/adapters/author-guide.md
docs/adapters/conformance.md
docs/adapters/security.md
README.md
TASKS.md
STATUS.md
```

Review questions must include:

```text
Does any prose create a second specification?
Are definition/import, runtime/binding, and projection responsibilities correctly separated?
Is trusted context protected from caller authority?
Are exact-target/no-retarget/fail-closed invariants preserved?
Do D-059/D-060/D-061 remain exact, especially runner-owned selection/applicability/verdicts?
Are harnesses limited to bounded raw observations?
Are capability and compatibility claims truthful and evidence-bounded?
Are Livewire/HTMX techniques clearly non-universal examples?
Do D-026 and D-062 remain PROPOSED?
Are T-703 and T-704 still unimplemented?
Did T-702 avoid production/spec/conformance semantic changes?
```

- [ ] **Step 4: Run the full final documentation verification after tracking edits**

Run:

```bash
python - <<'PY'
from pathlib import Path
import re

root = Path('docs/adapters')
expected = {
    root / 'README.md',
    root / 'author-guide.md',
    root / 'conformance.md',
    root / 'security.md',
}
assert {p for p in root.glob('*.md')} == expected
for path in sorted(expected):
    text = path.read_text(encoding='utf-8')
    for target in re.findall(r'\[[^\]]+\]\(([^)]+)\)', text):
        if target.startswith(('http://', 'https://', 'mailto:', '#')):
            continue
        relative = target.split('#', 1)[0]
        if not relative:
            continue
        resolved = (path.parent / relative).resolve()
        assert resolved.exists(), f'{path}: broken link {target} -> {resolved}'
print('adapter docs file-set/link checks: PASS')
PY

python scripts/validate.py

cd packages/browser-runtime
npm run conformance:build
cd ../..
python scripts/run_conformance.py

BASE="$(git merge-base main HEAD)"
for path in \
  packages/browser-runtime/src \
  packages/laravel/src \
  spec/0.1 \
  conformance/targets \
  scripts/conformance_model.py \
  scripts/run_conformance.py
do
  git diff --quiet "$BASE" HEAD -- "$path" || exit 1
done
```

Expected evidence:

```text
adapter docs file-set/link checks: PASS
scripts/validate.py: PASS
conformance matrix: 7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
forbidden production/spec/conformance paths: unchanged
```

- [ ] **Step 5: Confirm decision/task boundaries were not silently widened**

Run:

```bash
grep -F '| D-026 | PROPOSED |' docs/DECISION-REGISTER.md
grep -F '| D-062 | PROPOSED |' docs/DECISION-REGISTER.md
grep -n 'T-703' TASKS.md
grep -n 'T-704' TASKS.md
```

Expected:

```text
D-026 remains PROPOSED
D-062 remains PROPOSED
T-703 remains not started/TODO
T-704 remains not started/TODO
```

- [ ] **Step 6: Commit Task 6**

```bash
git add TASKS.md STATUS.md REVIEW_REQUEST.md
git commit -m "docs(tasks): record T-702 implementation evidence"
```

---

## Final Self-Review Checklist

Before requesting review, verify each approved design requirement maps to an implemented document/task:

- [ ] Adapter taxonomy is present without a universal interface — Task 3.
- [ ] Core/runner authority versus adapter/harness responsibility is explicit — Task 3.
- [ ] Guide authority is subordinate to canonical artifacts — Tasks 1–4.
- [ ] D-059/D-060/D-061 profile/capability/runner semantics are preserved exactly — Task 2.
- [ ] Harnesses emit bounded raw observations only — Task 2.
- [ ] Explicit exposure and discovery-vs-invocation are documented — Task 1.
- [ ] Caller input is separated from trusted runtime context — Task 1.
- [ ] Exact-target/no-retarget/fail-closed rules are documented — Tasks 1 and 3.
- [ ] Runtime/binding, projection, and import author guidance is present — Task 3.
- [ ] Current conformance author workflow, evidence scope, and bounded claim wording are present — Task 2.
- [ ] Correct/incorrect/dangerous examples are labeled non-normative — Tasks 1 and 3.
- [ ] Livewire/HTMX examples separate portable invariant from framework technique — Task 3.
- [ ] Version dimensions are separated without negotiation/solver semantics — Task 2.
- [ ] Canonical repository-relative links resolve — Tasks 1–4 and Task 6 final verification.
- [ ] D-026 remains `PROPOSED`; no new global error enum exists — Tasks 2 and 6.
- [ ] D-062 remains `PROPOSED` through implementation — Task 6.
- [ ] T-703/T-704 remain unimplemented — Tasks 3 and 6.
- [ ] No production code or canonical conformance semantics changed — Tasks 5 and 6.

## Scope-Stop Conditions

Implementation must stop and return to a new explicit design/decision gate if any task reveals a need to modify:

```text
packages/browser-runtime/src/**
packages/laravel/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

The same stop rule applies if the guide cannot be written accurately without introducing:

```text
a new adapter SDK/interface
a new trusted authority source
a new profile/capability/scenario
a runner/harness authority change
a new global error enum
new Livewire/HTMX runtime semantics
T-703 implementation
T-704 implementation
public certification/registry/signing/attestation semantics
standalone public-spec extraction
```

## Execution Handoff Gate

This plan is complete only as an implementation plan. **Do not execute any task above from this commit automatically.**

Current gate after this plan is committed:

```text
T-702 design: APPROVED
T-702 implementation plan: PREPARED
T-702 implementation: NOT STARTED
docs/adapters/**: NOT CREATED
D-062: PROPOSED
D-026: PROPOSED
T-703/T-704: NOT STARTED
```

A separate explicit user approval is required before creating `docs/adapters/**` or modifying implementation/tracking files for T-702.
