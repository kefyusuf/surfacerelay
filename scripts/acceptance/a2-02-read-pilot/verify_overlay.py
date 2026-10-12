#!/usr/bin/env python3
"""Safety tests for the overlay installer, NOT for a running Laravel consumer."""
from __future__ import annotations
import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
from install_overlay import assert_fresh_target, main


class OverlayTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.app = Path(self.tmp.name) / 'application'
        self.app.mkdir()
        self.source = Path(__file__).resolve().parent
        (self.app / 'bootstrap').mkdir()
        (self.app / 'bootstrap/providers.php').write_text('<?php\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n    App\\Providers\\Filament\\AdminPanelProvider::class,\n];\n')
        (self.app / 'vendor/surfacerelay/laravel').mkdir(parents=True)
        (self.app / 'composer.json').write_text(json.dumps({'require': {'surfacerelay/laravel': '0.1.0-alpha.3'}}))
        (self.app / 'package.json').write_text(json.dumps({'dependencies': {'@surfacerelay/browser-runtime': '0.1.0-alpha.3'}}))
        (self.app / 'composer.lock').write_text(json.dumps({'packages': [
            {'name': 'laravel/framework', 'version': 'v13.35.0'},
            {'name': 'filament/filament', 'version': 'v5.10.1'},
            {'name': 'surfacerelay/laravel', 'version': 'v0.1.0-alpha.3',
             'source': {'reference': '1d20fcc98f6a525362e30138aff44d23b719dde1'}}]}))
        (self.app / 'package-lock.json').write_text(json.dumps({'packages': {
            'node_modules/@surfacerelay/browser-runtime': {
                'version': '0.1.0-alpha.3',
                'integrity': 'sha512-THbTgfMxz/JisrBiU/X4jAJkMjiKQ5JMEP5YlPaoTJKnr4q/bQw4EF1nYu3+e3yaxdWafxnZqXYYwWXHFkmSVA=='}}}))

    def test_correct_installed_consumer_is_eligible(self):
        self.assertEqual(len(assert_fresh_target(self.source, self.app)), 8)

    def test_missing_filament_provider_denied(self):
        (self.app / 'bootstrap/providers.php').write_text('<?php\nreturn [App\\Providers\\AppServiceProvider::class];\n')
        with self.assertRaisesRegex(ValueError, 'AdminPanelProvider'):
            assert_fresh_target(self.source, self.app)

    def test_empty_app_denied(self):
        (self.app / 'composer.lock').unlink()
        with self.assertRaisesRegex(ValueError, 'Missing'):
            assert_fresh_target(self.source, self.app)

    def test_custom_composer_mirror_denied(self):
        (self.app / 'composer.json').write_text(json.dumps({'require': {'surfacerelay/laravel': '0.1.0-alpha.3'},
            'repositories': [{'type': 'composer', 'url': 'https://custom.invalid'}]}))
        with self.assertRaisesRegex(ValueError, 'Non-default'):
            assert_fresh_target(self.source, self.app)

    def test_version_mismatch_denied(self):
        (self.app / 'package.json').write_text(json.dumps({'dependencies': {'@surfacerelay/browser-runtime': '^0.1'}}))
        with self.assertRaisesRegex(ValueError, 'not exact'):
            assert_fresh_target(self.source, self.app)

    def test_wrong_mirror_source_denied(self):
        lock = json.loads((self.app / 'composer.lock').read_text())
        lock['packages'][2]['source']['reference'] = '0'*40
        (self.app / 'composer.lock').write_text(json.dumps(lock))
        with self.assertRaisesRegex(ValueError, 'source revision'):
            assert_fresh_target(self.source, self.app)

    def test_wrong_npm_integrity_denied(self):
        lock = json.loads((self.app / 'package-lock.json').read_text())
        lock['packages']['node_modules/@surfacerelay/browser-runtime']['integrity'] = 'wrong'
        (self.app / 'package-lock.json').write_text(json.dumps(lock))
        with self.assertRaisesRegex(ValueError, 'npm integrity'):
            assert_fresh_target(self.source, self.app)

    def test_existing_consumer_file_not_overwritten(self):
        p = self.app / 'app/A2/InspectOrder.php'; p.parent.mkdir(parents=True)
        p.write_text('<?php // owned by user')
        with self.assertRaisesRegex(ValueError, 'Refusing to replace'):
            assert_fresh_target(self.source, self.app)
        self.assertEqual(p.read_text(), '<?php // owned by user')

    def test_symlinked_target_denied(self):
        app_symlink = Path(self.tmp.name) / 'linked-app'
        app_symlink.symlink_to(self.app, target_is_directory=True)
        with self.assertRaisesRegex(ValueError, 'non-symlink'):
            assert_fresh_target(self.source, app_symlink)

    def test_valid_install_copies_exact_8_without_replacing_app_provider(self):
        with patch.object(sys, 'argv', ['install_overlay.py', str(self.app)]):
            self.assertEqual(main(), 0)
        self.assertTrue((self.app/'app/A2/InspectOrder.php').exists())
        self.assertEqual((self.app/'bootstrap/providers.php').read_text().count('A2OrdersProvider::class'), 1)
        self.assertEqual((self.app/'bootstrap/providers.php').read_text().count('AppServiceProvider::class'), 1)

    def test_second_run_rejected(self):
        with patch.object(sys, 'argv', ['install_overlay.py', str(self.app)]):
            self.assertEqual(main(), 0)
            self.assertEqual(main(), 2)


if __name__ == '__main__':
    unittest.main()
