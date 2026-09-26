# Post-M7 Product / Scope Reassessment

Status: REASSESSMENT COMPLETE / RECOMMENDATION READY / IMPLEMENTATION NOT STARTED
Date: 2026-09-22
Baseline: main@a3ee94751cef344e0d1038325297629a0f212c02
Baseline verification: Validate #953 / 35681090013 — 12/12 SUCCESS
Milestone opened by this document: NONE

## 1. Purpose

M7 is fully closed, externally reviewed, merged, and main-revalidated. The next body of work must not be selected by feature momentum alone.

This reassessment asks one product question:

> What next body of work produces the highest product learning and user value without widening SurfaceRelay's semantic surface prematurely?

This gate is documentation-only. It does not create a task ID, decision record, package release, version promise, or implementation authorization.

## 2. Current product baseline

SurfaceRelay now has:

- provisional protocol-neutral Action Definition / Runtime Binding contracts;
- a Laravel trusted action runtime;
- Livewire and Filament integration evidence;
- a browser runtime / WebMCP projection;
- production-oriented confirmation, idempotency, output-policy, and audit controls;
- an HTMX portability proof;
- executable conformance infrastructure and adapter-author guidance;
- an optional maintained Laravel MCP projection bridge;
- an optional bounded OpenAPI 3.1/3.2 importer.

The capability side is therefore materially broader than the repository's external-consumer story.

## 3. Observed adoption/distribution gap

### 3.1 Root user journey remains repository-development-first

The root README points users to architecture/status/task documentation and repository validation commands. It does not define a supported clean downstream installation path, first consumer application, supported package combination, or release-candidate workflow.

### 3.2 TypeScript packages are source/test packages, not reviewed distribution artifacts

@surfacerelay/browser-runtime currently has version 0.0.0-dev and private:true, with no reviewed distribution entry-point or release build/package-content contract.

@surfacerelay/openapi-importer likewise has version 0.0.0-dev and private:true, and its README explicitly avoids a publication/compatibility promise.

This does not mean either package should be published now. It means distribution behavior has not yet been designed or proven.

### 3.3 PHP packages remain repository-coupled at the MCP bridge boundary

surfacerelay/laravel is a normal Composer library boundary, but surfacerelay/laravel-mcp currently resolves the base package through a local path repository and surfacerelay/laravel:dev-main, with minimum-stability:dev.

That is appropriate for repository development, but it is not a clean external-consumer dependency contract.

### 3.4 No release lifecycle has been exercised

At the reassessment baseline:

- GitHub releases: none;
- release/publish workflow: none;
- CHANGELOG: none;
- root vulnerability-reporting policy: none;
- clean downstream consumer fixture: none;
- artifact installation proof outside monorepo source/path coupling: none.

### 3.5 Existing roadmap release labels are historical intent, not an exercised sequence

The roadmap lists 0.1.0-alpha through 0.4.0-beta gates tied to earlier milestones. M0 through M7 are now complete, while none of those releases was actually published.

Therefore the next public version must not be chosen mechanically from the old milestone-to-version mapping. Version strategy requires a fresh design decision based on the real first external-consumer contract.

## 4. Candidate directions

### Candidate A — add more framework/protocol integrations

Assessment:
- increases breadth;
- does not solve how an external user consumes today's capability;
- adds maintenance/support surface before a release contract exists;
- produces lower product learning than testing real consumption.

Recommendation: DEFER.

### Candidate B — open a v0.2 canonical contract expansion

Assessment:
- there is no clean external-consumer evidence showing which semantic pain points require a contract change;
- changing the contract now risks optimizing for repository-internal assumptions.

Recommendation: DEFER until consumer evidence identifies concrete contract pressure.

### Candidate C — certification, registry, badge, or public-standard work

Assessment:
- conformance infrastructure exists;
- public certification claims would be stronger and more consequential than current adoption evidence supports;
- adapter-author guidance explicitly keeps certification/registry infrastructure out of scope.

Recommendation: DEFER.

### Candidate D — consumer and release readiness

Assessment:
- directly attacks the clearest gap between implemented capability and usable product;
- validates package boundaries without requiring new semantic capability;
- creates evidence about installation, dependency, documentation, and compatibility friction;
- allows public publication to remain a later explicit gate.

Recommendation: SELECT AS NEXT MILESTONE CANDIDATE.

## 5. Recommended milestone candidate

Working name:

> M8 — Consumer & Release Readiness

This is a recommendation only. M8 is not opened by this document.

Candidate outcome:

> A clean downstream project can install the intended SurfaceRelay release-candidate artifacts without monorepo path/dev-main coupling, follow a minimal documented setup, exercise the supported happy path, and reproduce bounded compatibility evidence.

The phrase release-candidate artifacts is deliberate. This milestone should prove consumability before authorizing registry publication.

## 6. Required M8 design blockers

A separate M8 scope/design gate must resolve these before implementation.

### 6.1 Public package set

Do not assume every current repository package should be published. Resolve which of these belong in the first consumer contract:

- surfacerelay/laravel;
- surfacerelay/laravel-mcp;
- @surfacerelay/browser-runtime;
- @surfacerelay/openapi-importer.

A package may remain repository-private if publishing it does not improve the first supported user journey.

### 6.2 Version topology

Resolve whether packages share one release version, version independently, or use explicit cross-package compatibility constraints. Do not infer the answer from milestone numbers.

### 6.3 Registry-independent release-candidate artifacts

Define how consumer proof works before registry publication. Prefer installable built/archive artifacts or another deterministic local release-candidate mechanism rather than source-tree/path shortcuts that bypass the package contract under test.

### 6.4 PHP dependency boundary

Replace the repository-only dev-main/path relationship in release-candidate consumer proof with an explicit version-compatible dependency model, without publishing prematurely.

### 6.5 TypeScript distribution contract

Define, separately for each intended public TypeScript package:

- build output;
- ESM/package entry points;
- type declarations;
- exports/package content;
- supported Node/browser boundary;
- source-map policy if relevant;
- consumer import smoke test.

### 6.6 Clean-consumer verification

Design at least one fresh consumer fixture per supported public journey. Consumer proof must test what users receive, not what monorepo tests can reach through source-relative paths.

Candidate proof dimensions include:

- fresh Laravel application + base package;
- fresh Laravel application + optional MCP bridge;
- browser-runtime consumer build/import;
- OpenAPI importer consumer import/execution of its bounded public API.

The final scope may intentionally publish fewer packages and therefore require fewer fixtures.

### 6.7 Release-facing documentation and operations

Define the minimum reviewed set:

- installation / getting started;
- supported compatibility matrix wording;
- changelog policy;
- vulnerability-reporting policy;
- release checklist;
- migration/breaking-change policy for the 0.x phase;
- artifact verification evidence.

## 7. Publication must remain a separate gate

M8 readiness must not automatically perform:

- npm publication;
- Packagist publication;
- GitHub tag creation;
- GitHub Release creation;
- public compatibility certification;
- a 1.0 claim.

After clean-consumer evidence and external review, publication requires a separate explicit go/no-go publication gate.

## 8. Explicitly deferred work

The reassessment does not authorize:

- T-705;
- T-801;
- a new runtime/binding/projection adapter;
- HTTP execution generated from OpenAPI;
- broader MCP features;
- v0.2 canonical schema work;
- D-026 promotion;
- certification/registry infrastructure;
- package version bumps;
- package publication;
- tags/releases.

## 9. Product rationale

The repository has already proved that SurfaceRelay can implement the architecture across multiple contexts. The next unanswered product question is different:

> Can a developer who did not build this repository consume a bounded SurfaceRelay package contract safely and predictably?

Answering that question generates more useful evidence than adding another integration.

## 10. Reassessment verdict

~~~text
M0-M7:                         COMPLETE
Next feature expansion:        DEFER
Canonical v0.2 expansion:      DEFER
Certification/registry:        DEFER
Recommended milestone:         M8 — Consumer & Release Readiness
M8 state:                      PROPOSED ONLY / NOT OPENED
Implementation:                NOT STARTED
Registry publication:          NOT AUTHORIZED
Tags/releases:                 NOT AUTHORIZED
Next explicit gate:            M8 scope/design gate only, after approval
~~~

## 11. Self-review

### Scope alignment — PASS

The recommendation improves consumption of the existing SurfaceRelay product rather than widening its semantic mission.

### Invariant / ADR consistency — PASS

No Action Definition, Runtime Binding, trusted-context, execution, projection, or conformance semantic is changed.

### Dependency direction — PASS

The reassessment identifies repository-only coupling as a release-readiness problem without introducing a new dependency direction.

### Unnecessary complexity — PASS

New adapters, certification, registries, and contract expansion are deferred. The proposed work is bounded around proving the existing product boundary.

### Brownfield/public-commitment safety — PASS

Registry publication, tags, versions, and public compatibility promises are explicitly separated from readiness implementation.

### Verification/evidence discipline — PASS

The recommendation is grounded in current package metadata, README/CI/release state, and the fully green M7 baseline. It does not claim external consumer compatibility that has not yet been proven.

## 12. Next gate

If this reassessment is approved, the next step is M8 scope/design only.

That gate should resolve the seven blockers above and stop again before implementation.
