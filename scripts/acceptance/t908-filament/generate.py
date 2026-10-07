"""Extend the T-907 disposable consumer with an opt-in local browser candidate."""
import argparse
import json
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[3]
HERE = Path(__file__).resolve().parent
DEST = ROOT / '.tmp/t908-filament-demo'


def replace_once(text, old, new):
    if text.count(old) != 1:
        raise SystemExit(f'Acceptance scaffold changed; review transformation: {old!r}')
    return text.replace(old, new)


def generate():
    parser = argparse.ArgumentParser()
    parser.add_argument('--refresh-owned', action='store_true')
    parser.add_argument('--browser-artifact', required=True, type=Path)
    args = parser.parse_args()
    artifact = args.browser_artifact.resolve()
    if not artifact.is_file() or artifact.suffix != '.tgz':
        parser.error('--browser-artifact must be an existing local built npm .tgz')
    # Reuse the original ownership/path checks and pinned gateway transformation.
    source_path = HERE.parent / 't907-filament/generate.py'
    source = source_path.read_text()
    source = replace_once(source, "DEST = ROOT / '.tmp/t907-filament-demo'", "DEST = ROOT / '.tmp/t908-filament-demo'")
    source = source.replace('.t907-owned', '.t908-owned').replace('surfacerelay-t907-filament', 'surfacerelay-t908-filament')
    previous_argv = sys.argv
    try:
        sys.argv = [str(source_path)] + (['--refresh-owned'] if args.refresh_owned else [])
        exec(compile(source, str(source_path), 'exec'), {'__file__': str(source_path), '__name__': '__main__'})
    finally:
        sys.argv = previous_argv
    client = (DEST / 'client.mjs').read_text()
    client = replace_once(client, 'new WebMcpRegistrationLifecycle(modelContext, drivers)',
                          "new WebMcpRegistrationLifecycle(modelContext, drivers, { resultMode: 'envelope' })")
    (DEST / 'client.mjs').write_text(client)
    package = json.loads((DEST / 'package.json').read_text())
    package['dependencies']['@surfacerelay/browser-runtime'] = 'file:' + artifact.as_posix()
    (DEST / 'package.json').write_text(json.dumps(package, indent=2) + '\n')
    # The published browser lock cannot describe an unreleased candidate. Only
    # discard the disposable generated copy; both source Composer locks survive.
    (DEST / 'package-lock.json').unlink()
    (DEST / '.t908-browser-artifact').write_text(str(artifact) + '\n')
    (DEST / 'app/ResponseLossFault.php').write_text((HERE / 'app__ResponseLossFault.php.template').read_text())
    gateway_path = DEST / 'app/FilamentGateway.php'
    gateway = replace_once(gateway_path.read_text(),
        '$executor = new class implements ActionExecutor {',
        '$executor = new class($row->binding_id, $definition->id) implements ActionExecutor {\n'
        '            public function __construct(private string $bindingId, private string $actionId) {}')
    gateway = replace_once(gateway,
        "DB::table('effects')->insert(['actor_id'",
        "DB::table('effects')->insert(['t908_binding_id' => $this->bindingId, 't908_action_id' => $this->actionId, 'actor_id'")
    gateway = replace_once(gateway, '$outcome = (new \\SurfaceRelay\\Laravel\\Filament\\Invocation\\FilamentActionGateway',
        '$effectBoundary = ResponseLossFault::effectBoundary();\n'
        '        $outcome = (new \\SurfaceRelay\\Laravel\\Filament\\Invocation\\FilamentActionGateway')
    gateway = replace_once(gateway,
        'return (new ActionResultNormalizer())->normalize($outcome)->toArray();',
        '$result = (new ActionResultNormalizer())->normalize($outcome)->toArray();\n'
        '        ResponseLossFault::afterResult($component, $reason, $result, $effectBoundary);\n'
        '        return $result;')
    gateway_path.write_text(gateway)
    setup_path = DEST / 'setup.php'
    setup = replace_once(setup_path.read_text(), 'echo "Installed application fixture initialized.\\n";',
        (HERE / 'setup-fault.php.template').read_text() + '\necho "Installed application fixture initialized.\\n";')
    setup_path.write_text(setup)


if __name__ == '__main__':
    generate()
