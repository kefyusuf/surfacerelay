#!/usr/bin/env python3
"""Synthetic verifier tests only. They do NOT qualify published packages."""
import contextlib
import io
import json
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest
from verify_install import main as verify_main, EXPECTED_LARAVEL_MIRROR, EXPECTED_NPM_INTEGRITY
from preflight import command_output
from unittest.mock import patch


class VerifierTests(unittest.TestCase):
    def setup_consumer(self, root: Path):
        (root / 'composer.json').write_text(json.dumps({"require": {"surfacerelay/laravel": "0.1.0-alpha.3"}}))
        packages = []
        for name, version in (
            ('laravel/framework', 'v13.0.1'),
            ('filament/filament', 'v5.10.0'),
            ('livewire/livewire', 'v4.4.7'),
            ('surfacerelay/laravel', 'v0.1.0-alpha.3'),
        ):
            entry = {"name": name, "version": version}
            if name == 'surfacerelay/laravel':
                entry['source'] = {"reference": EXPECTED_LARAVEL_MIRROR}
                entry['dist'] = {"type": "zip"}
            packages.append(entry)
        (root / 'composer.lock').write_text(json.dumps({"packages": packages}))
        (root / 'package.json').write_text(json.dumps({"dependencies": {"@surfacerelay/browser-runtime": "0.1.0-alpha.3"}}))
        (root / 'package-lock.json').write_text(json.dumps({"packages": {"node_modules/@surfacerelay/browser-runtime": {
            "version": "0.1.0-alpha.3", "integrity": EXPECTED_NPM_INTEGRITY,
            "resolved": "https://registry.npmjs.org/@surfacerelay/browser-runtime/-/browser-runtime-0.1.0-alpha.3.tgz"
        }}}))
        (root / 'vendor/surfacerelay/laravel').mkdir(parents=True)
        (root / 'node_modules/@surfacerelay/browser-runtime').mkdir(parents=True)

    def call(self, root):
        with patch('sys.argv', ['verify_install.py', str(root)]), contextlib.redirect_stdout(io.StringIO()) as out:
            rc = verify_main()
        return rc, json.loads(out.getvalue())

    def test_long_php_module_listing_is_not_truncated(self):
        from types import SimpleNamespace
        long_output = '[PHP Modules]\n' + ('filler\n' * 110) + 'zip\n'
        with patch('preflight.shutil.which', return_value='/usr/bin/php'), patch(
            'preflight.subprocess.run', return_value=SimpleNamespace(returncode=0, stdout=long_output)
        ):
            self.assertIn('zip', command_output(['php', '-m'], truncate_at=None)['output'].splitlines())
            self.assertNotIn('zip', command_output(['php', '-m'])['output'].splitlines())

    def test_matching_synthetic_lock_passes(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            rc, report = self.call(root)
            self.assertEqual(rc, 0, report)
            self.assertEqual(report['result'], 'PASS_LOCK_METADATA_ONLY')

    def test_wrong_mirror_ref_fails(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            data = json.loads((root/'composer.lock').read_text())
            data['packages'][3]['source']['reference'] = '0'*40
            (root/'composer.lock').write_text(json.dumps(data))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('Composer SurfaceRelay source ref mismatch', report['errors'])

    def test_path_repo_fails(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            (root/'composer.json').write_text(json.dumps({'repositories': [{'type': 'path', 'url': '../packages/*'}]}))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('custom Composer repository detected', report['errors'])

    def test_composer_map_style_path_repository_is_rejected(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            (root/'composer.json').write_text(json.dumps({'repositories': {'local': {'type': 'path', 'url': '../packages/*'}}, 'require': {'surfacerelay/laravel': '0.1.0-alpha.3'}}))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('custom Composer repository detected', report['errors'])

    def test_custom_packagist_mirror_repository_is_rejected(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            (root/'composer.json').write_text(json.dumps({
                'repositories': [{'type': 'composer', 'url': 'https://packages.example.test'}],
                'require': {'surfacerelay/laravel': '0.1.0-alpha.3'},
            }))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('custom Composer repository detected', report['errors'])

    def test_unpinned_composer_requirement_is_rejected(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            (root/'composer.json').write_text(json.dumps({'require': {'surfacerelay/laravel': '^0.1'}}))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('Composer SurfaceRelay requirement not exact alpha.3', report['errors'])

    def test_unpinned_npm_requirement_is_rejected(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            (root/'package.json').write_text(json.dumps({'dependencies': {'@surfacerelay/browser-runtime': '^0.1.0-alpha.3'}}))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('npm SurfaceRelay requirement not exact alpha.3', report['errors'])

    def test_wrong_npm_integrity_fails(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            self.setup_consumer(root)
            data = json.loads((root/'package-lock.json').read_text())
            data['packages']['node_modules/@surfacerelay/browser-runtime']['integrity'] = 'sha512-invalid'
            (root/'package-lock.json').write_text(json.dumps(data))
            rc, report = self.call(root)
            self.assertEqual(rc, 1)
            self.assertIn('npm integrity mismatch', report['errors'])


# Exercise byte-checking with test-owned hashes; never label synthetic fixtures as
# evidence of the published packages. Run with `python3 -m unittest -v test_kit.py`.
import hashlib
from verify_payload import verify_files, PUBLISHED_CRITICAL_FILES


class PayloadVerifierTests(unittest.TestCase):
    def test_empty_install_with_matching_lockfiles_fails_payload(self):
        with TemporaryDirectory() as tmp:
            root = Path(tmp)
            VerifierTests().setup_consumer(root)
            result = verify_files(root)
            self.assertEqual(result['result'], 'FAIL')
            self.assertEqual(result['checked'], 0)
            self.assertEqual(len(result['errors']), 9)

    def test_test_owned_hash_matches_then_mutation_fails(self):
        with TemporaryDirectory() as tmp:
            app = Path(tmp)
            file = app/'node_modules/@surfacerelay/browser-runtime/dist/index.js'
            file.parent.mkdir(parents=True)
            file.write_bytes(b'known synthetic fixture')
            digest = hashlib.sha256(file.read_bytes()).hexdigest()
            expected = {'browser-runtime': {'dist/index.js': digest}}
            self.assertEqual(verify_files(app, expected)['result'], 'PASS_CRITICAL_SUBSET')
            file.write_bytes(b'changed synthetic fixture')
            report = verify_files(app, expected)
            self.assertEqual(report['result'], 'FAIL')
            self.assertIn('published SHA-256 mismatch', report['errors'][0])

    def test_symlink_cannot_satisfy_payload_fingerprint(self):
        with TemporaryDirectory() as tmp:
            app = Path(tmp)
            file = app/'node_modules/@surfacerelay/browser-runtime/dist/index.js'
            file.parent.mkdir(parents=True)
            target = app/'fake-actual.js'
            target.write_bytes(b'known synthetic fixture')
            file.symlink_to(target)
            digest = hashlib.sha256(target.read_bytes()).hexdigest()
            expected = {'browser-runtime': {'dist/index.js': digest}}
            report = verify_files(app, expected)
            self.assertEqual(report['result'], 'FAIL')
            self.assertIn('symlinked', report['errors'][0])

    def test_release_fingerprint_set_has_both_packages(self):
        self.assertEqual(sum(len(x) for x in PUBLISHED_CRITICAL_FILES.values()), 9)
        self.assertEqual(set(PUBLISHED_CRITICAL_FILES), {'browser-runtime', 'laravel'})


if __name__ == '__main__':
    unittest.main()
