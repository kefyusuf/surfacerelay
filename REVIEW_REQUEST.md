# Review handoff

Open PR: T-922 from `docs/t922-try-it` — a one-command try-it flow for the
quickstart, closing the "how do I actually run this right now" gap flagged by
the owner. Ships:

- [try-it.sh](examples/minimal-quickstart/try-it.sh): host wrapper — builds
  the committed toolchain image if missing, creates a disposable volume,
  installs a fresh registry-only consumer with the documented quickstart
  code, seeds synthetic demo data, serves on `http://127.0.0.1:8080`, polls
  reachability and prints a Chrome checklist
  (`window.__quickstartRegistration`, agent path via chrome-devtools-mcp).
  Self-diagnoses the wedged-port-forwarder environment failure with concrete
  fixes instead of a silent timeout.
- [docker/Dockerfile](examples/minimal-quickstart/docker/Dockerfile): the
  toolchain image recipe (PHP 8.4 + intl/zip + Composer + Node 22 +
  Python 3), committed so users don't depend on a private image.
- [seed_demo.php](examples/minimal-quickstart/seed_demo.php): synthetic demo
  user and orders (single-tenant quickstart tenant).
- getting-started: prerequisites table, one-command try-it pointer, expected
  console outputs; README: "Got Docker? Try the demo first" box.

Verified end to end on this machine: full flow run (build → install → seed →
serve), all server-side endpoints exercised, PHPUnit 5/5 inside the demo
container. One honest finding: on this Windows host Docker's port forwarder
(`com.docker.backend.exe`) stopped binding published ports entirely (raw
host processes CAN bind the same ports; survives Docker and WSL restarts) —
the script now self-diagnoses this with the likely fixes (antivirus
per-application rule for the backend binary is the leading suspect). The
diagnosis and repro are documented in the script's warning block; server
containers elsewhere on the machine are unaffected.

Docs and demo tooling; no runtime, schema, fixture or release bytes change.
`validate.py` and the link checker are green.
