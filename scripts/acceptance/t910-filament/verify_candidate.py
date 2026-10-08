"""Verify the T-910 local artifact and prepare a disposable native consumer.

Run inside a task-owned POSIX environment with a writable isolated repository
copy. This prepares, but does not start, a server or claim native acceptance.
"""
import json
import os
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT))
from scripts.browser_release_candidate import build_browser_release_candidate, create_clean_consumer_workspace, execute_clean_consumer_proof


def run(*command, cwd=ROOT):
    subprocess.run(command, cwd=cwd, check=True)


def main():
    stage = ROOT / '.tmp/t910-candidate'
    consumer = ROOT / '.tmp/t910-clean-consumer'
    if stage.exists() or consumer.exists() or (ROOT / '.tmp/t910-filament-demo').exists():
        raise RuntimeError('Preserving existing T-910 task directories.')
    run(sys.executable, 'scripts/validate.py')
    browser = ROOT / 'packages/browser-runtime'
    run('npm', 'ci', '--no-audit', '--no-fund', cwd=browser)
    for command in ('typecheck', 'test', 'build', 'conformance:build'):
        run('npm', 'run', command, cwd=browser)
    revision = os.environ['T910_SOURCE_REVISION']
    artifact = build_browser_release_candidate(repo=ROOT, stage_root=stage,
        artifact_version='0.0.0-t910.1', source_revision=revision)
    create_clean_consumer_workspace(consumer_root=consumer,
        fixture_root=ROOT / 'scripts/fixtures/browser-clean-consumer', package_source_root=browser)
    proof = execute_clean_consumer_proof(consumer_root=consumer,
        archive_path=artifact['archivePath'], artifact_version='0.0.0-t910.1',
        package_source_root=browser)
    # Generic clean-consumer proof checks root/type/bundle/smoke/deep-import.
    # Independently import the new values from the installed tarball as well.
    run('node', '--input-type=module', '-e',
        "import { FilamentBrowserDriver, FilamentSelectionCoordinator, GlobalFilamentSelectionRuntime } "
        "from '@surfacerelay/browser-runtime'; "
        "if (![FilamentBrowserDriver, FilamentSelectionCoordinator, GlobalFilamentSelectionRuntime]"
        ".every(value => typeof value === 'function')) throw new Error('Missing Filament exports');", cwd=consumer)
    run(sys.executable, 'scripts/acceptance/t910-filament/generate.py', '--browser-artifact', artifact['archivePath'])
    demo = ROOT / '.tmp/t910-filament-demo'
    run('composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress', cwd=demo)
    run('composer', 'validate', '--strict', cwd=demo)
    run('npm', 'install', '--no-audit', '--no-fund', cwd=demo)
    run('npm', 'ci', '--no-audit', '--no-fund', cwd=demo)
    run('npm', 'run', 'build', cwd=demo)
    run('php', 'setup.php', cwd=demo)
    run('php', str(ROOT / 'scripts/acceptance/t907-filament/command.php'), 'filament:assets', cwd=demo)
    output = ROOT / '.tmp/t910-verification.json'
    output.write_text(json.dumps({'artifact': artifact, 'cleanConsumer': proof,
        'native': 'unverified', 'source': 'isolated working-tree candidate; not a registry release'}, indent=2) + '\n')
    print('T-910 artifact and generated consumer prepared; native acceptance not yet run.', flush=True)


if __name__ == '__main__':
    main()
