"""CLI integration checks use only disposable marker-owned SQLite state."""
import json
from contextlib import closing
from pathlib import Path
import sqlite3
import subprocess
import sys
import tempfile
import time
import unittest

SCRIPT = Path(__file__).resolve().parent / 'fault_control.py'
ROOT = SCRIPT.parents[3]


class FaultControlCli(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.demo = Path(self.temp.name) / 'demo'
        (self.demo / 'database').mkdir(parents=True)
        (self.demo / '.t908-owned').write_text('surfacerelay-t908-filament\n')
        self.database = self.demo / 'database/acceptance.sqlite'
        with closing(sqlite3.connect(self.database)) as connection, connection:
            connection.executescript('''
                CREATE TABLE pilot_livewire_bindings (
                    binding_id TEXT PRIMARY KEY, component_id TEXT, active INTEGER,
                    expires_at INTEGER, descriptor TEXT, receipt_ciphertext TEXT, session_hash TEXT);
                CREATE TABLE effects (id INTEGER PRIMARY KEY, tenant_id TEXT,
                    order_ids TEXT, reason TEXT, t908_binding_id TEXT, t908_action_id TEXT);
                CREATE TABLE t908_response_faults (binding_id TEXT PRIMARY KEY,
                    component_id TEXT, method TEXT, reason TEXT, armed INTEGER,
                    effects_before INTEGER, effects_after INTEGER, dropped_at INTEGER,
                    invocation_effects_before INTEGER);
            ''')
            connection.execute('INSERT INTO pilot_livewire_bindings VALUES (?,?,?,?,?,?,?)',
                ('exact-binding', 'exact-component', 1, int(time.time()) + 120,
                 json.dumps({'target': {'method': 'holdCurrent'}}),
                 'RECEIPT_CANARY_MUST_NOT_PRINT', 'SESSION_CANARY_MUST_NOT_PRINT'))
            connection.execute('INSERT INTO effects VALUES (1,?,?,?,?,?)',
                ('tenant-a', '[102]', 'customer-request', 'previous-binding', 'pilot.filament.orders.hold'))

    def run_control(self, operation):
        return subprocess.run([sys.executable, str(SCRIPT), operation, '--database',
            str(self.database), '--binding-id', 'exact-binding'], cwd=ROOT,
            capture_output=True, text=True)

    def fault_rows(self):
        with closing(sqlite3.connect(self.database)) as connection, connection:
            return connection.execute('SELECT binding_id,component_id,method,reason,armed,effects_before FROM t908_response_faults').fetchall()

    def test_arm_persists_exact_binding_and_current_ledger_count_without_secret_output(self):
        result = self.run_control('arm')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(self.fault_rows(), [('exact-binding', 'exact-component', 'holdCurrent', 'customer-request', 1, 1)])
        evidence = json.loads(result.stdout)
        self.assertEqual(evidence['fault']['binding_id'], 'exact-binding')
        self.assertEqual(len(evidence['effects']), 1)
        self.assertNotIn('RECEIPT_CANARY', result.stdout + result.stderr)
        self.assertNotIn('SESSION_CANARY', result.stdout + result.stderr)

    def test_stale_binding_is_refused_without_fault_insertion(self):
        with closing(sqlite3.connect(self.database)) as connection, connection:
            connection.execute('UPDATE pilot_livewire_bindings SET expires_at=0')
        result = self.run_control('arm')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Refusing stale or unsupported binding', result.stderr)
        self.assertEqual(self.fault_rows(), [])

    def test_repeat_arm_refuses_without_resetting_consumed_fault(self):
        self.assertEqual(self.run_control('arm').returncode, 0)
        with closing(sqlite3.connect(self.database)) as connection, connection:
            connection.execute('UPDATE t908_response_faults SET armed=0,effects_after=2,dropped_at=123')
        result = self.run_control('arm')
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.fault_rows(), [('exact-binding', 'exact-component', 'holdCurrent', 'customer-request', 0, 1)])
        with closing(sqlite3.connect(self.database)) as connection, connection:
            self.assertEqual(connection.execute('SELECT effects_after,dropped_at FROM t908_response_faults').fetchone(), (2, 123))

    def test_missing_ownership_marker_refuses_without_fault_insertion(self):
        (self.demo / '.t908-owned').unlink()
        result = self.run_control('arm')
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Refusing database outside a marker-owned T-908 consumer', result.stderr)
        self.assertEqual(self.fault_rows(), [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
