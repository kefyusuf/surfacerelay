# Alpha.2 preparation evidence

This is local preparation evidence, not a publication receipt. Version
`0.1.0-alpha.2` was built from exact committed preparation source
`803dc1e0046444984d9e8968502e2149df525cc5` in a clean Docker clone. The final
merged release revision must be frozen and rebuilt before tagging/publication.

## Packages and installed consumers

- Final public npm tarball SHA-256:
  `547a6e97d6ee497f6d0bf73fc641ea07e727b3df796d80779742e51f66dc8577`.
  All 39 manifest entries were verified; root ESM import, shipped types,
  bundling, Node smoke and blocked deep import passed. Candidate-to-public
  runtime/license bytes are unchanged; public metadata has the alpha tag.
- All 36 browser dist hashes match the T-908 native-qualified candidate in
  [the retained manifest](t908-candidate-proof.json).
- Laravel candidate ZIP SHA-256:
  `621080a4e253e7bee58cf24b7730f1eadf6d1a3cb115405de1c344148bbb5720`.
  Final public Laravel tree has 160 manifest entries. Runtime/license entries
  match the published alpha.1 receipt; metadata/README are coordinated for alpha.2.
  Composer strict validation passed. A fresh installed Laravel 13.35.0 consumer
  passed 11 HTTP/shared-store checks with two PHP 8.4.26 workers, SQLite WAL
  and file-cache locks; the deliberately removed authorization was detected.

## Fresh Chrome / SQL preparation controls

The task-owned disposable Filament app on loopback 4187 installed the exact
public npm tarball above and the exact local Laravel candidate ZIP above.
Only SurfaceRelay's Composer dependency changed from the preserved fixture lock;
Laravel 13.35.0, Livewire 4.4.7 and Filament 5.10.0 stayed pinned.
The native Chrome connection used an isolated `t909-alpha2` context and actual
UI login, row checkboxes and approval dialogs. No credential, receipt, challenge
or cache record is retained. These are local artifacts, not registry installs.

| Control | Native result | SQL effects |
| --- | --- | --- |
| Invalid reason | Nested `rejected/input_validation_failed` | 0 |
| Selected refund approval | Nested `confirmation_required`; UI approval alone has no effect | 0 |
| Authorized refund for 101+102 | Nested `succeeded` | 1 |
| Fresh edit102 approval, exact one-shot response-loss arm | Nested confirmation, then UI approval | 1 |
| Commit then discard successful HTTP response | Safe `execution_failed`, `outcome: unknown` | 2; fault consumed |
| Old list binding after edit mount | Safe `execution_failed`, `outcome: unknown` | 2 |
| Observe without retry, then explicit same edit102 replay | Nested original `succeeded` | 2 |

The [sanitized native/SQL record](alpha-0.1.0-alpha.2-native-preparation.json)
retains the observed outputs and ledger boundaries. The consumed fault records
`invocation_effects_before=1`, `effects_after=2`,
`armed=0` and a non-null drop timestamp. The new effect is exactly tenant-a
order102; the preceding effect is the separately approved 101+102 refund.
The initial list completion is a normal control, not response-loss evidence.
Final SQL inspection after replay retained exactly those two effects.

## Regression / review and open gates

The follow-up-alpha README regression failed against the first-alpha claim;
the smallest wording change produced 12 passing public-artifact tests.
194 Python tests, canonical fixtures, 22 HTMX fixtures, 41 envelope fixtures
and release guardrails passed. Independent source review found no blocker;
tracking was corrected to describe current gates rather than already fixed defects.

Exact-head hosted CI, final merged-source rebuild, owner identity/device
authentication, source/mirror tags, registry publication and fresh registry/native
consumer acceptance remain pending. No registry write occurred. Existing
candidate/readiness/preview NO-GO guards retain their meaning. This serial
simulated fixture is not real payment, broad concurrency or production qualification.
