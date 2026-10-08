"""Candidate generation retains own ownership and opts into the modern adapter."""
import importlib.util
from pathlib import Path
import shutil
import json
import unittest

HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('t908_generator_tests',HERE.parent/'t908-filament/test_generator.py')
baseline=importlib.util.module_from_spec(spec)
spec.loader.exec_module(baseline)

class GeneratorSafety(baseline.GeneratorSafety):
    test_historical_client_builds_when_live_example_uses_future_exports=None
    def setUp(self):
        super().setUp()
        shutil.copytree(HERE,self.root/'scripts/acceptance/t910-filament',ignore=shutil.ignore_patterns('__pycache__'))
        self.script=self.root/'scripts/acceptance/t910-filament/generate.py'
        self.target=self.root/'.tmp/t910-filament-demo'
    def test_modern_client_uses_local_candidate_and_own_marker(self):
        # This candidate explicitly needs modern exports; old registries remain
        # qualified by their own inherited T-907/T-908 build tests.
        result=self.run_generator()
        self.assertEqual(result.returncode,0,result.stderr)
        client=(self.target/'client.mjs').read_text()
        self.assertIn('FilamentBrowserDriver',client)
        self.assertIn('GlobalFilamentSelectionRuntime',client)
        self.assertIn('FilamentSelectionCoordinator',client)
        self.assertNotIn('/surfacerelay/runtime/',client)
        self.assertNotIn('class FilamentSelectionSyncingDriver',client)
        self.assertIn("resultMode: 'envelope'",client)
        package=json.loads((self.target/'package.json').read_text())
        self.assertEqual(package['dependencies']['@surfacerelay/browser-runtime'],'file:'+self.artifact.as_posix())
        self.assertEqual((self.target/'.t910-owned').read_text(),'surfacerelay-t910-filament\n')
    def test_other_task_destination_is_untouched(self):
        other=self.root/'.tmp/t908-filament-demo'; other.mkdir(parents=True)
        sentinel=other/'sentinel'; sentinel.write_text('owner data')
        result=self.run_generator()
        self.assertEqual(result.returncode,0,result.stderr)
        self.assertEqual(sentinel.read_text(),'owner data')

if __name__=='__main__':unittest.main(verbosity=2)
