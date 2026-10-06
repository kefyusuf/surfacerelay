# Publication Metadata Preview Review Request

Branch: `release/t-903-publication-preview`.
Task: T-903 preparation; gates remain open. Base: `test/t-902-installed-app-acceptance`,
[PR #37](https://github.com/kefyusuf/surfacerelay/pull/37), itself stacked on PR #36.

## Change and evidence

A separate local preview tool verifies both actual candidate identities, source/
version, archive/manifest hashes and every archived file before generating proposed
staged npm/Composer metadata. Existing private builders and source metadata are
unchanged. Offline npm dry-run checks the exact proposed inventory; Composer
validates an explicit manifest with plugins/scripts disabled in a fresh environment.
The tool always reports NO-GO; no settings, credential injection, tags or registry
write commands are added.

Eleven TDD preview tests pass, including tamper, mismatched source, destination
preservation, preview drift, invalid inventory, command failure and ambient
Composer override/credential isolation. Full Python: 170 tests pass. Actual
Docker dry-runs pass on both T-901 archives. The added CI job rebuilds local
candidates from exact archived sources and repeats these checks.

Independent review found an ambient COMPOSER false-proof path; explicit targeting,
environment isolation and a regression test fix it. Review the full diff and
current-head CI before merge.

## Owner handoff and limits

[Concrete proposals and gates](docs/releases/0.1.0-alpha.1-publication-handoff.md):
Packagist channel/mirror choice, disabled private intake, unverified registry
authority and pending formal decision dispositions. A public endpoint 404 is not
ownership or reservation. No publication contract/channel is implemented.

T-903 cannot close yet. T-904 is not started; no automatic merge/publication.
The two owner lockfiles remain excluded. Development monorepo, runtime packages,
contracts and Proposed decision statuses remain unchanged.
