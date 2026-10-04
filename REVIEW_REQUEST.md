# Review Request — T-805 integrated release readiness

Branch `feat/t-805-release-readiness`, merged for review via integration PR #34.

## What changed

- `scripts/release_readiness.py`: `build_release_readiness()` — one clean exact revision and
  one prerelease version in; both candidates built into separate stages by the unchanged
  builders; identity and archive/manifest hashes independently re-verified; aggregate
  `readiness-evidence.json` written only after every check passes.
- Publication handoff is always `NO-GO` with explicit blockers (D-073).
- CI: Validate runs the new tests. Docs: `docs/RELEASE-READINESS.md` (run record),
  RELEASE-CHECKLIST points at it; CHANGELOG; tracking files (no open tasks left).

## Review focus

- Verification depth: identity in result, evidence and manifest; filename, size, archive
  and manifest SHA-256 recomputed.
- The recorded run is **pre-merge** (`e722d01`, branch revision); must be repeated on merged `main`.

## Verification

- RED first: 10/10 new tests errored (module missing); now 10/10 OK.
- Real run on a clean `git worktree` of `e722d01` (Windows): both archives built and verified;
  browser clean consumer PASS; Laravel artifact install + identity + ActionBus smoke PASS.
- Release-candidate suites, publication guardrails, `validate.py`: pass.
