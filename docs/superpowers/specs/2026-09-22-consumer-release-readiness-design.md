# M8 — Consumer & Release Readiness Design

Status: APPROVED / IMPLEMENTATION NOT STARTED
Date: 2026-09-22
Branch: docs/m8-consumer-release-readiness-design
Base: post-M7 reassessment head 3ea6904f0131588769464dd16ff68180220ec7bd
Base validation: #957 / 35682496627 — 12/12 SUCCESS

## 1. Goal

M8 exists to prove that the already-implemented SurfaceRelay product can be consumed outside the monorepo before any public registry publication is attempted.

Candidate milestone outcome:

> A clean downstream project can install the intended SurfaceRelay release-candidate artifacts without monorepo path/dev-main coupling, follow a minimal documented setup, exercise the supported happy path, and reproduce bounded compatibility evidence.

M8 is release-readiness work, not a feature-expansion milestone.

## 2. Product boundary

M8 does not change Action Definition, Runtime Binding, trusted-context, authorization, confirmation, idempotency, output-policy, audit, WebMCP, MCP, OpenAPI-import semantics, or conformance verdict semantics.

It may change package metadata, build/export boundaries, artifact tooling, clean-consumer fixtures, and release-facing documentation only where necessary to prove consumption of existing behavior.

## 3. Blocker 1 — first release-candidate package set

### Decision

The first external consumer contract is deliberately limited to:

- `surfacerelay/laravel`;
- `@surfacerelay/browser-runtime`.

The following packages remain outside the first release-candidate public set:

- `surfacerelay/laravel-mcp`;
- `@surfacerelay/openapi-importer`.

### Rationale

`surfacerelay/laravel` plus `@surfacerelay/browser-runtime` represent the core SurfaceRelay user journey: trusted server execution plus stateful browser binding/projection.

The Laravel MCP bridge is optional ecosystem projection work and currently carries repository-only `dev-main`/path coupling. The OpenAPI importer is a secondary import adapter under D-012. Publishing either in the first consumer line would increase dependency and support surface without improving proof of the primary product journey.

Keeping them private in M8 is not a statement that they will never be public. Each requires a later explicit package-promotion gate and its own clean-consumer evidence.

Current package names are logical candidate names only. Registry namespace ownership/availability is not claimed by this design and must be confirmed before publication.

## 4. Blocker 2 — version topology

### Decision

The first public-candidate set uses one coordinated SurfaceRelay release train:

- all candidate packages are built from the same exact Git revision;
- all candidate artifacts receive the same SemVer release identity;
- Action Definition versions remain completely independent from package/release versions;
- independent package versioning is deferred until real release-cadence evidence justifies divergence.

### No public version is selected here

M8 design does not choose `0.1.0`, `0.4.0`, `0.5.0`, or any other first public version.

The historical roadmap release labels were never published and therefore do not automatically determine the first public version.

During M8 verification, the artifact builder receives an explicit non-public prerelease SemVer as an input. Source manifests must not be changed merely to pretend that a public release exists.

The later Publication Go/No-Go gate selects the actual first public version.

## 5. Blocker 3 — registry-independent release-candidate artifacts

### Common artifact rules

Every release-candidate artifact must be bound to:

- exact package name;
- explicit artifact version;
- exact Git source revision;
- deterministic package-content manifest;
- SHA-256 archive digest.

Artifacts are generated into an ignored staging directory and are never committed.

Consumer tests may access the generated artifact archives but must not use source-relative imports, Composer path repositories, workspace links, symlinks into `packages/**`, or `dev-main` package resolution.

M8 requires deterministic package contents and recorded archive hashes. Byte-for-byte reproducibility across different operating systems is not an M8 requirement.

### PHP artifact

`surfacerelay/laravel` is staged as a Composer-consumable archive containing only release package files, including its Composer manifest, production source, migrations, package documentation, and license material.

For registry-independent Composer artifact-repository testing, the staged archive may receive build-time version metadata. The source `composer.json` must not gain a committed `version` field solely for M8.

### Browser artifact

`@surfacerelay/browser-runtime` is built to a staged ESM package and packed with `npm pack`.

The consumer installs the produced `.tgz` directly. M8 must never run `npm publish`.

## 6. Blocker 4 — PHP dependency boundary

The first public-candidate set contains only `surfacerelay/laravel`, so no SurfaceRelay-to-SurfaceRelay Composer dependency is required in the first consumer contract.

`surfacerelay/laravel-mcp` remains repository-only during M8. Its current `dev-main` + local path repository relationship therefore does not become a public dependency promise.

A later Laravel-MCP package-promotion gate must replace that repository-only relationship with a versioned dependency compatible with the coordinated release line and prove it using built artifacts rather than monorepo path resolution.

## 7. Blocker 5 — browser-runtime distribution contract

### Public module shape

The first browser-runtime candidate is:

- ESM only;
- JavaScript target ES2022;
- browser/DOM runtime only;
- one curated root package entry point;
- TypeScript declaration files included;
- no CommonJS build;
- no public source maps in the first candidate;
- no supported deep-import contract.

### Root export rule

A new root public API entry point must explicitly re-export only reviewed consumer-facing types/classes/functions required by the documented Livewire, HTMX, driver registry, and WebMCP journey.

`package.json` must expose only the package root through `exports`. Internal `dist/*` modules are not public API merely because they exist in the archive.

Existing repository tests may continue to use source-relative imports internally. Clean consumer tests must import only from `@surfacerelay/browser-runtime`.

### Public API evidence

M8 implementation must add an explicit public-export snapshot/type test so an accidental new root export or removal is review-visible.

Node may be used as a build/test host, but M8 does not claim Node as a supported browser-runtime execution environment.

## 8. Blocker 6 — clean-consumer verification

Consumer proof must occur outside package source directories and consume only release-candidate archives.

### 8.1 Composer installation matrix

`surfacerelay/laravel` artifact installation is checked for the currently supported compatibility matrix:

- PHP 8.3 + Laravel/Illuminate 12;
- PHP 8.3 + Laravel/Illuminate 13;
- PHP 8.4 + Laravel/Illuminate 12;
- PHP 8.4 + Laravel/Illuminate 13.

This matrix proves dependency resolution and package loading from the artifact, not merely repository-source compatibility.

### 8.2 Laravel application smoke

At least one latest-supported Laravel consumer smoke must:

- live in a temporary clean consumer directory;
- install the Composer artifact through an artifact repository;
- use normal Composer autoload/package discovery or explicit documented registration;
- register a minimal existing SurfaceRelay Action;
- execute one safe non-consequential happy path through the existing ActionBus;
- use no source/path link back into `packages/laravel`.

The smoke is not allowed to create a second simplified runtime.

### 8.3 Browser package consumer smoke

A temporary TypeScript/browser-oriented consumer must:

- install only the generated `.tgz`;
- import exclusively from the package root;
- typecheck against the shipped declarations;
- successfully bundle the consumer entry with a normal ESM browser bundler;
- execute a small package-root smoke over a side-effect-free public primitive or controlled browser test;
- prove that no source-relative/deep import is required.

### 8.4 Isolation checks

CI must statically reject consumer configuration or source that references:

- `packages/laravel`;
- `packages/browser-runtime/src`;
- Composer `path` repositories for SurfaceRelay;
- npm `file:` links to the source package;
- workspace links or symlinks into the monorepo.

## 9. Blocker 7 — release-facing documentation and operations

M8 must create or update the following release-facing surfaces:

- root README: clearly separate development from consumer installation; registry commands remain unavailable until publication;
- package-specific consumer/getting-started documentation for Laravel and browser runtime;
- `CHANGELOG.md` beginning with an `Unreleased` section rather than inventing a released version;
- `SECURITY.md` with honest support status and a publication blocker if no private vulnerability-reporting channel is configured;
- versioning/compatibility documentation that separates package SemVer from Action versioning;
- release checklist that builds artifacts, records hashes, runs clean-consumer proof, and prepares an external-review handoff.

### 0.x compatibility policy

For the first public 0.x line:

- patch releases must not intentionally break the documented package API;
- minor releases may contain breaking package API changes but require explicit changelog/migration notes;
- prerelease artifacts may change before the public publication gate;
- compatibility claims are bounded to the tested package version, environment matrix, and evidence revision.

This policy does not alter canonical Action version semantics.

## 10. Publication separation

M8 is complete when release-candidate artifacts and consumer evidence are reviewable. It does not publish them.

The following remain prohibited during M8:

- `npm publish`;
- Packagist package registration/publication;
- Git tags;
- GitHub Releases;
- public certification/badges;
- registry credentials in M8 CI;
- a 1.0 claim.

After M8 external review, a separate **Publication Go/No-Go Gate** must verify:

- registry namespace ownership/availability;
- exact first public SemVer;
- registry package metadata;
- private vulnerability reporting channel;
- publication credentials/provenance mechanism;
- final release notes and hashes;
- whether the publication should proceed at all.

## 11. Proposed milestone task decomposition

### T-801 — Release-candidate artifact contract

Define the shared artifact-version input, source-revision binding, staging layout, package-content manifest, SHA-256 evidence, and no-publication guardrails.

### T-802 — Laravel release-candidate artifact + clean consumer proof

Build `surfacerelay/laravel` as a Composer artifact and prove the install matrix plus one real Laravel ActionBus consumer smoke without path/dev-main coupling.

### T-803 — Browser runtime public API + package artifact + clean consumer proof

Create the curated root export surface, ESM/declaration build, root-only exports contract, npm tarball, and clean consumer typecheck/bundle/smoke.

### T-804 — Release-facing documentation and compatibility policy

Create consumer installation guidance, changelog, security/versioning/compatibility wording, and release checklist without claiming that a public release exists.

### T-805 — Integrated release-readiness verification + external-review handoff

Build both artifacts from one revision/version input, run all existing repository validation plus clean-consumer proof, record hashes/evidence, conduct external review, and stop at Publication Go/No-Go handoff.

No T-801 implementation begins from this design gate.

## 12. Proposed decisions

### D-069 — First release-candidate package set

PROPOSED: the first external SurfaceRelay consumer contract includes only `surfacerelay/laravel` and `@surfacerelay/browser-runtime`; Laravel MCP and OpenAPI importer remain outside the first public candidate set.

### D-070 — Coordinated first release train

PROPOSED: first-public-candidate packages use one SemVer release identity and exact source revision; package release versioning is independent from Action versions; actual first public SemVer is selected only by the publication gate.

### D-071 — Artifact-first clean-consumer proof

PROPOSED: release readiness is proven from generated Composer/npm artifacts in isolated consumers; source/path/workspace/dev-main shortcuts do not qualify as consumer evidence.

### D-072 — Browser runtime root-only ESM API

PROPOSED: `@surfacerelay/browser-runtime` publishes one curated ESM root entry with declarations and no supported deep-import/CommonJS contract in the first candidate line.

### D-073 — Publication is a separate gate

PROPOSED: M8 may prepare versioned artifacts and release evidence but must not publish registries/tags/releases; public publication requires a later explicit go/no-go gate.

All five decisions remain PROPOSED until this design is separately approved and later implementation/evidence justifies acceptance.

## 13. Explicit non-goals

M8 does not include:

- Laravel MCP package publication;
- OpenAPI importer package publication;
- new MCP capabilities;
- new framework/binding adapters;
- OpenAPI-generated HTTP execution;
- v0.2 canonical contract changes;
- D-026 promotion;
- standalone spec extraction;
- conformance certification infrastructure;
- registry publication;
- a first public version decision;
- tag/release creation.

## 14. Design acceptance criteria

The scope/design gate is ready for approval only if:

- the first package set is explicit and minimal;
- version topology is separated from Action versions and does not invent a public version;
- registry-independent artifact proof is defined;
- no repository path/dev-main shortcut can satisfy clean-consumer evidence;
- browser root API/deep-import boundary is explicit;
- clean-consumer matrix is bounded and executable;
- release/security/changelog responsibilities are explicit;
- publication remains a separate later gate;
- no implementation file is changed.

## 15. Self-review

### Scope alignment — PASS

The design focuses on consumption of existing product capability rather than adding agent/runtime semantics.

### Invariant / ADR consistency — PASS

Action/Binding/trust/projection/conformance semantics are untouched. D-008 monorepo remains valid; M8 proves packaging without splitting repositories.

### Dependency direction — PASS

The first package set avoids promoting the current Laravel-MCP dev-main/path coupling into a public contract.

### Unnecessary complexity — PASS

Only two packages are in the first candidate set. MCP/OpenAPI publication, independent package versioning, CommonJS, certification and registries are deferred.

### Brownfield/public-commitment safety — PASS

Exact public SemVer, registry namespaces, tags, releases and credentials are deferred until consumer evidence exists.

### Verification/evidence discipline — PASS

Consumer claims must come from built archives, exact revisions, recorded hashes, bounded matrices, and isolated downstream projects.

## 16. Next gate

The M8 design was approved on 2026-09-22. The next gate is **T-801 implementation-plan approval only** after the plan is prepared. Implementation must not begin automatically.
