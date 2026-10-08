"""Reuse T-907 ownership tests and protect the separate candidate destination."""
import importlib.util
from pathlib import Path
import shutil
import unittest
import subprocess
import sys
import json

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('t907_generator_tests', HERE.parent / 't907-filament/test_generator.py')
baseline = importlib.util.module_from_spec(spec)
spec.loader.exec_module(baseline)


class GeneratorSafety(baseline.GeneratorSafety):
    npm_install_mode='install'
    def setUp(self):
        super().setUp()
        shutil.copytree(HERE, self.root / 'scripts/acceptance/t908-filament', ignore=shutil.ignore_patterns('__pycache__'))
        self.script = self.root / 'scripts/acceptance/t908-filament/generate.py'
        self.target = self.root / '.tmp/t908-filament-demo'
        self.artifact = self.root / 'candidate.tgz'
        self.artifact.write_bytes(b'generator-only artifact placeholder')

    def run_generator(self, *args):
        return super().run_generator('--browser-artifact', str(self.artifact), *args)

    def test_missing_candidate_refuses_generation(self):
        self.artifact.unlink()
        self.assertNotEqual(self.run_generator().returncode, 0)
        self.assertFalse(self.target.exists())
    def test_historical_client_builds_when_live_example_uses_future_exports(self):
        npm='npm.cmd' if sys.platform=='win32' else 'npm'
        packed=subprocess.run([npm,'pack','@surfacerelay/browser-runtime@0.1.0-alpha.2','--json'],
            cwd=self.root,capture_output=True,text=True)
        self.assertEqual(packed.returncode,0,packed.stderr)
        self.artifact=self.root/json.loads(packed.stdout)[0]['filename']
        super().test_historical_client_builds_when_live_example_uses_future_exports()

    def test_source_locks_and_existing_t907_consumer_are_preserved(self):
        locks = [self.root / 'examples/alpha-livewire-pilot/composer.lock',
                 self.root / 'scripts/acceptance/t907-filament/composer.lock']
        before = [lock.read_bytes() for lock in locks]
        other = self.root / '.tmp/t907-filament-demo'
        other.mkdir(parents=True)
        sentinel = other / 'sentinel'; sentinel.write_text('owner data')
        result = self.run_generator()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual([lock.read_bytes() for lock in locks], before)
        self.assertEqual(sentinel.read_text(), 'owner data')
        self.assertEqual((self.target / 'composer.lock').read_bytes(), before[1])


if __name__ == '__main__':
    unittest.main(verbosity=2)
