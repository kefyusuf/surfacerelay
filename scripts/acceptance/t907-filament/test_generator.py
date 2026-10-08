"""Behavior checks: generation must not overwrite unrelated or redirected files."""
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import unittest

class GeneratorSafety(unittest.TestCase):
    npm_install_mode='ci'
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root=Path(self.temp.name)/'repo'
        source=Path(__file__).resolve().parents[3]
        shutil.copytree(source/'examples/alpha-livewire-pilot',self.root/'examples/alpha-livewire-pilot',ignore=shutil.ignore_patterns('node_modules','vendor','.tmp'))
        (self.root/'examples/filament-orders-live').mkdir()
        shutil.copy2(source/'examples/filament-orders-live/client.mjs',self.root/'examples/filament-orders-live/client.mjs')
        shutil.copytree(Path(__file__).parent,self.root/'scripts/acceptance/t907-filament',ignore=shutil.ignore_patterns('__pycache__'))
        self.script=self.root/'scripts/acceptance/t907-filament/generate.py'
        self.target=self.root/'.tmp/t907-filament-demo'
    def run_generator(self,*args):
        return subprocess.run([sys.executable,str(self.script),*args],capture_output=True,text=True)
    def test_existing_directory_is_rejected_without_changing_sentinel(self):
        self.assertEqual(self.run_generator().returncode,0)
        sentinel=self.target/'sentinel.txt'; sentinel.write_text('owner data')
        self.assertNotEqual(self.run_generator().returncode,0)
        self.assertEqual(sentinel.read_text(),'owner data')
    def test_historical_client_builds_when_live_example_uses_future_exports(self):
        # Migration of the live example must not change pinned alpha.1 consumers.
        (self.root/'examples/filament-orders-live/client.mjs').write_text(
            "import { FilamentBrowserDriver } from '@surfacerelay/browser-runtime';\n")
        result=self.run_generator()
        self.assertEqual(result.returncode,0,result.stderr)
        npm='npm.cmd' if sys.platform=='win32' else 'npm'
        install=subprocess.run([npm,self.npm_install_mode,'--no-audit','--no-fund'],cwd=self.target,capture_output=True,text=True)
        self.assertEqual(install.returncode,0,install.stderr)
        build=subprocess.run([npm,'run','build'],cwd=self.target,capture_output=True,text=True)
        self.assertEqual(build.returncode,0,build.stderr)
        self.assertGreater((self.target/'public/assets/client.js').stat().st_size,1000)
    def test_redirected_parent_is_rejected_without_writing_external_target(self):
        external=Path(self.temp.name)/'external'; external.mkdir()
        self.root.mkdir(exist_ok=True)
        (self.root/'.tmp').symlink_to(external,target_is_directory=True)
        self.assertNotEqual(self.run_generator().returncode,0)
        self.assertEqual(list(external.iterdir()),[])
    def test_owned_refresh_rejects_redirected_internal_directory(self):
        self.assertEqual(self.run_generator().returncode,0)
        external=Path(self.temp.name)/'external'; external.mkdir()
        shutil.rmtree(self.target/'app')
        (self.target/'app').symlink_to(external,target_is_directory=True)
        self.assertNotEqual(self.run_generator('--refresh-owned').returncode,0)
        self.assertEqual(list(external.iterdir()),[])

if __name__=='__main__': unittest.main(verbosity=2)
