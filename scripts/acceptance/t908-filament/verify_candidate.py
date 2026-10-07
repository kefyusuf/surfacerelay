"""Build a private candidate and verify its disposable signed-HTTP consumer."""
import argparse
import os
from pathlib import Path
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[3]
HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
from scripts.browser_release_candidate import build_browser_release_candidate

DEMO = ROOT / '.tmp/t908-filament-demo'
STAGE = ROOT / '.tmp/t908-candidate'


def run(*command, cwd=ROOT, env=None):
    subprocess.run(command, cwd=cwd, env=env, check=True)


def check_local_path(path):
    if path.absolute() != path.resolve() or path.is_symlink() or not path.is_relative_to(ROOT / '.tmp'):
        raise RuntimeError(f'Refusing redirected task path: {path}')


def remove_owned(path, marker_name, marker_text):
    check_local_path(path)
    marker = path / marker_name
    if not marker.is_file() or marker.read_text() != marker_text:
        raise RuntimeError(f'Preserving unverified directory: {path}')
    # npm's bin links point inside this owned tree. Permit those and reject
    # external redirects; rmtree unlinks symlinks without following them.
    if any(not entry.resolve().is_relative_to(path) for entry in path.rglob('*')):
        raise RuntimeError(f'Preserving redirected task directory: {path}')
    shutil.rmtree(path)
    print(f'Removed task-owned directory: {path}', flush=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--require-clean-source', action='store_true',
                        help='CI: verify tracked source equals the checked-out HEAD before building')
    args = parser.parse_args()
    for path in [ROOT / '.tmp', DEMO, STAGE]:
        check_local_path(path)
    for path in [DEMO, STAGE]:
        if path.exists():
            raise RuntimeError(f'Refusing existing task directory: {path}')
    if args.require_clean_source:
        run('git', 'diff', '--exit-code', 'HEAD', '--')
    revision = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    env = dict(os.environ, PILOT_URL='http://127.0.0.1:8000',
               PILOT_DATABASE=str(DEMO / 'database/acceptance.sqlite'), PILOT_CONFIRMATION_TTL='5')
    server = None
    stage_owned = False
    try:
        run(sys.executable, str(HERE / 'test_generator.py'))
        run('npm', 'ci', '--no-audit', '--no-fund', cwd=ROOT / 'packages/browser-runtime')
        run('npm', 'run', 'build', cwd=ROOT / 'packages/browser-runtime')
        # Builder requires an empty stage. We create it exclusively and record
        # ownership afterward, including partial build failure, before cleanup.
        STAGE.mkdir(parents=True, exist_ok=False)
        stage_owned = True
        candidate = build_browser_release_candidate(repo=ROOT, stage_root=STAGE,
            artifact_version='0.0.0-t908.1', source_revision=revision)
        run(sys.executable, str(HERE / 'generate.py'), '--browser-artifact', candidate['archivePath'])
        run('composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress', cwd=DEMO)
        run('composer', 'validate', '--strict', cwd=DEMO)
        run('npm', 'install', '--no-audit', '--no-fund', cwd=DEMO)
        run('npm', 'ci', '--no-audit', '--no-fund', cwd=DEMO)
        run('npm', 'run', 'build', cwd=DEMO)
        run('php', 'setup.php', cwd=DEMO, env=env)
        run('php', str(HERE.parent / 't907-filament/command.php'), 'filament:assets', cwd=DEMO, env=env)
        with socket.socket() as probe:
            probe.bind(('127.0.0.1', 8000))
        with (DEMO / 'http-server.log').open('wb') as log:
            server = subprocess.Popen(['php', '-S', '127.0.0.1:8000', '-t', 'public', 'public/router.php'],
                cwd=DEMO, env=env, stdout=log, stderr=subprocess.STDOUT)
            for attempt in range(30):
                if server.poll() is not None:
                    raise RuntimeError('Owned PHP server exited before readiness')
                try:
                    with urllib.request.urlopen(env['PILOT_URL'] + '/session', timeout=1) as response:
                        if response.status == 200:
                            break
                except (urllib.error.URLError, TimeoutError):
                    time.sleep(1)
            else:
                raise RuntimeError('Owned PHP server did not become ready')
            run(sys.executable, str(HERE.parent / 't907-filament/filament_acceptance.py'), env=env)
            run(sys.executable, str(HERE / 'fault_acceptance.py'), env=env)
        print('T-908 candidate Filament signed-HTTP acceptance: PASS (native Chrome unverified)', flush=True)
    except Exception:
        log = DEMO / 'http-server.log'
        if log.is_file():
            print(log.read_text(errors='replace')[-8000:], file=sys.stderr)
        raise
    finally:
        if server is not None and server.poll() is None:
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill(); server.wait(timeout=5)
        if DEMO.exists():
            remove_owned(DEMO, '.t908-owned', 'surfacerelay-t908-filament\n')
        if stage_owned and STAGE.exists():
            check_local_path(STAGE)
            (STAGE / '.t908-candidate-owned').write_text('surfacerelay-t908-candidate\n')
            remove_owned(STAGE, '.t908-candidate-owned', 'surfacerelay-t908-candidate\n')


if __name__ == '__main__':
    main()
