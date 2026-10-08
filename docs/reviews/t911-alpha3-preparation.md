# T-911 Alpha.3 Preparation Acceptance

Preparation branch: `feat/t-911-alpha3-preparation`. Coordinated alpha.3 plan is
owner-approved; publication and registry acceptance are separate remaining gates.

## Actual local evidence

- Public artifact README regression failed before the minimal README correction;
  all 13 artifact tests passed after it. No runtime source changed.
- The updated installed smoke fixture failed against npm alpha.2 with missing
  `FilamentBrowserDriver` export. This demonstrates the new import gate is sensitive.
- Owned Docker isolation passed 195 Python regressions, 508 browser tests,
  typecheck/build/conformance build, release guards and canonical validation,
  including 22 HTMX and 41 WebMCP result fixtures.
- Separate final-public alpha.3 tarball installed into a clean npm consumer.
  Root imports, new Filament constructors/interfaces through TypeScript and Vite,
  preserved registry/envelope smoke and unexposed-binding rejection before lookup
  or synchronization passed. Filament deep import was rejected. Installed bytes
  matched the reviewed public manifest.
- The alpha.3 Laravel ZIP installed into a clean Laravel 13.35.0 HTTP fixture;
  11 tests passed with two workers and real SQLite/file-cache locks. Deliberate
  authorization mutation was detected before the restored suite passed.
- Independent read-only review found no remaining preparation blocker. It did
  not rerun hosted CI/native/registry acceptance.

## Provenance and remaining gates

The local build above used an isolated working-tree candidate based on merged
T-910 (`d400323ffd6c5fc340ceecf04fdc3041b399903a`). Its source-revision label is the
base, not proof of exact candidate Git identity. The Docker fixture's local Git
repository only supports archive-dependent regressions; it is not release origin.
Clean exact-head CI and exact merged-source rebuild must qualify release bytes.

Public artifact native Chrome selection/approval/response-loss/replay checks,
immutable source/mirror tags, publication and fresh registry native/consumer proof
remain pending. T-910 native evidence is historical and cannot fill these gates.
No production, payment, broad concurrency or general interoperability claim.

## Resource ownership

`surfacerelay-t911-preparation` uses the existing `surfacerelay-t807a-live:local`
image and contains all consumer/staging data. `.tmp/t911-session` contains only
owned archive/helper files. No image, named volume or worktree was created.
Remove these when verification ends; preserve both user Composer lock files and
all unrelated Docker/browser resources.
