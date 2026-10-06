"""Install a local alpha archive and qualify real application policy/store wiring."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import signal
import socket
import subprocess
import sys
import time

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.alpha_application_tests import ApplicationAcceptance, Client, run_acceptance
from scripts.laravel_release_candidate import create_clean_consumer_workspace, verify_clean_consumer_install


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--artifact", type=Path, required=True)
    parser.add_argument("--sha256", required=True)
    parser.add_argument("--source-revision", required=True)
    parser.add_argument("--version", default="0.1.0-alpha.1")
    parser.add_argument("--consumer", type=Path, required=True)
    parser.add_argument("--evidence", type=Path, required=True)
    args = parser.parse_args()
    if os.name != "posix":
        parser.error("Real multi-process PHP acceptance requires Linux (Docker or CI).")
    digest = hashlib.sha256(args.artifact.read_bytes()).hexdigest()
    if digest != args.sha256:
        parser.error("Archive SHA-256 does not match the selected evidence.")
    provenance = json.loads((args.artifact.parent / "artifact-evidence.json").read_text())
    if any(provenance.get(field) != value for field, value in {
        "archiveSha256": digest, "sourceRevision": args.source_revision,
        "artifactVersion": args.version, "packageName": "surfacerelay/laravel",
        "archiveFilename": args.artifact.name}.items()):
        parser.error("Archive provenance does not match the selected alpha source/version.")
    repo = Path(__file__).resolve().parents[1]
    create_clean_consumer_workspace(consumer_root=args.consumer,
        artifact_directory=args.artifact.parent, artifact_version=args.version,
        laravel_constraint="^13.0", package_source_root=repo / "packages/laravel")
    shutil.copytree(repo / "scripts/fixtures/laravel-alpha-application", args.consumer, dirs_exist_ok=True)
    manifest_path = args.consumer / "composer.json"
    manifest = json.loads(manifest_path.read_text())
    manifest["autoload"] = {"psr-4": {"App\\": "app/"}}
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n")
    subprocess.run(["composer", "update", "--no-interaction", "--prefer-dist", "--no-progress"], cwd=args.consumer, check=True)
    verify_clean_consumer_install(consumer_root=args.consumer, artifact_version=args.version,
                                 package_source_root=repo / "packages/laravel")
    subprocess.run(["php", "setup.php", str(args.consumer)], cwd=args.consumer, check=True)
    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]
    args.evidence.mkdir(parents=True, exist_ok=True)
    url = f"http://127.0.0.1:{port}"
    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        second_port = probe.getsockname()[1]
    second_url = f"http://127.0.0.1:{second_port}"
    server_environment = dict(os.environ)
    server_environment.pop("PHP_CLI_SERVER_WORKERS", None)
    with (args.consumer / "server.log").open("wb") as output:
        server = subprocess.Popen(["php", "-d", "opcache.enable=0", "-d", "opcache.enable_cli=0", "-S", f"127.0.0.1:{port}", "-t", "public", "public/index.php"],
            cwd=args.consumer, env=server_environment,
            stdout=output, stderr=output, start_new_session=True)
        second = None
        try:
            second = subprocess.Popen(["php", "-d", "opcache.enable=0", "-d", "opcache.enable_cli=0", "-S", f"127.0.0.1:{second_port}", "-t", "public", "public/index.php"],
                cwd=args.consumer, env=server_environment, stdout=output, stderr=output, start_new_session=True)
            for attempt in range(60):
                try:
                    Client(url)
                    Client(second_url)
                    break
                except (OSError, ValueError):
                    if server.poll() is not None or second.poll() is not None:
                        raise RuntimeError("Application server stopped before acceptance.")
                    time.sleep(0.1)
            else:
                raise RuntimeError("Application server did not become ready.")
            runtime_path = args.consumer / "app/AcceptanceRuntime.php"
            original = runtime_path.read_text()
            anchor = "Gate::define('acceptance.refund', static function (User $user, InvocationContext $context): bool {"
            if original.count(anchor) != 1:
                raise RuntimeError("Authorization mutation anchor changed; review the control.")
            try:
                runtime_path.write_text(original.replace(anchor, anchor + " return true;"))
                mutation = run_acceptance(url, args.consumer / "database/acceptance.sqlite",
                    "test_guest_denied_actor_and_forged_authority")
                if len(mutation.failures) != 1 or mutation.errors:
                    raise RuntimeError("The negative acceptance test did not detect bypassed Gate authorization.")
            finally:
                runtime_path.write_text(original)
            ApplicationAcceptance.concurrency_url = second_url
            result = run_acceptance(url, args.consumer / "database/acceptance.sqlite")
            evidence = Client(url).evidence()
            installed = json.loads((args.consumer / "vendor/composer/installed.json").read_text())
            versions = {package["name"]: package["version"] for package in installed["packages"]
                        if package["name"] in ("laravel/framework", "surfacerelay/laravel")}
            record = {"version": args.version, "sourceRevision": args.source_revision,
                "archiveSha256": digest, "installed": versions, "testsRun": result.testsRun,
                "failures": len(result.failures), "errors": len(result.errors),
                "effects": len(evidence["effects"]), "audits": len(evidence["audits"]),
                "authorizationMutationDetected": True,
                "testNames": sorted(name for name in dir(ApplicationAcceptance) if name.startswith("test_")),
                "fixtureSha256": hashlib.sha256(b"".join(path.relative_to(repo).as_posix().encode() + b"\0" + path.read_bytes()
                    for path in sorted((repo / "scripts/fixtures/laravel-alpha-application").rglob("*.php")))).hexdigest(),
                "testSuiteSha256": hashlib.sha256((repo / "scripts/alpha_application_tests.py").read_bytes()).hexdigest(),
                "idempotencyStates": sorted({row["state"] for row in evidence["idempotency"]}),
                "php": subprocess.check_output(["php", "-r", "echo PHP_VERSION;"], text=True),
                "store": "SQLite WAL + file-cache locks", "workers": 2,
                "scope": "fixture HTTP host; no UI, native-agent or production claim"}
            (args.evidence / "application-acceptance.json").write_text(json.dumps(record, indent=2) + "\n")
            if not result.wasSuccessful():
                raise SystemExit(1)
        finally:
            stop_servers(server, second)


def stop_servers(*processes):
    for process in processes:
        if process is None:
            continue
        try:
            os.killpg(process.pid, signal.SIGTERM)
        except ProcessLookupError:
            pass
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            process.wait()


if __name__ == "__main__":
    main()
