# T-911 Alpha.3 Preparation Review

Branch: `feat/t-911-alpha3-preparation`. Owner-approved coordinated alpha.3 plan;
no registry publication or new runtime behavior in the preparation change.

Review the public artifact README, installed consumer fixture and CI version.
New Filament root values/types must compile and bundle from an isolated tarball
installation. Node smoke retains the old registry/envelope checks and rejects an
unexposed binding before framework lookup or selection writes. New instructions
require one shared coordinator and exact binding objects, preserve server authority
and prohibit automatic retries of unknown outcomes.

README regression was RED before correction, then 13 artifact tests passed. The
new smoke rejects published alpha.2's missing export as a negative control.
Docker 195 Python/508 browser tests, canonical validation and separate public
tarball root/type/bundle/smoke/manifest checks passed; installed Laravel 11 HTTP/
shared-store controls passed and detected deliberate authorization mutation.
Independent preparation review found no blocker. [Evidence](docs/reviews/t911-alpha3-preparation.md).
Exact merged-source and new native acceptance remain pending release gates.
Publication requires merged-source rebuild, immutable mappings and fresh registry
proof; working-tree evidence is distinct.

User Composer locks and unrelated Docker resources are preserved. No new image,
volume or worktree is created. Temporary verification resources remain T-911-owned.
