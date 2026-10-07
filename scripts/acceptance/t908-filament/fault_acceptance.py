"""Real signed Livewire requests prove the one-shot response fault boundary."""
import importlib.util
import json
from pathlib import Path
import sqlite3
import unittest

spec = importlib.util.spec_from_file_location('t907_http', Path(__file__).resolve().parent.parent / 't907-filament/filament_acceptance.py')
baseline = importlib.util.module_from_spec(spec)
spec.loader.exec_module(baseline)


class ResponseLossAcceptance(unittest.TestCase):
    database = baseline.FilamentAcceptance.database
    setUp = baseline.FilamentAcceptance.setUp
    effects = baseline.FilamentAcceptance.effects
    pending = baseline.FilamentAcceptance.pending
    approve = baseline.FilamentAcceptance.approve

    def arm(self, method='refundSelected', reason='customer-request'):
        snapshot = json.loads(self.browser.snapshot)
        self.binding = snapshot['data']['bindingId']
        with sqlite3.connect(self.database) as connection:
            connection.execute('insert into t908_response_faults (binding_id,component_id,method,reason,armed,effects_before) values (?,?,?,?,1,?)',
                (self.binding, snapshot['memo']['id'], method, reason, self.effects()))
        self.addCleanup(self.clear_fault)

    def clear_fault(self):
        with sqlite3.connect(self.database) as connection:
            connection.execute('delete from t908_response_faults where binding_id=?', (self.binding,))

    def fault(self):
        with sqlite3.connect(self.database) as connection:
            return connection.execute('select armed,effects_before,effects_after,dropped_at from t908_response_faults where binding_id=?', (self.binding,)).fetchone()

    def test_committed_effect_response_is_discarded_once_and_explicit_replay_is_one_effect(self):
        self.pending(('101', '102')); self.approve(); self.arm()
        response = self.browser.call('refundSelected', 'customer-request')
        self.assertEqual(response[0], 503)
        self.assertEqual(response[1], {'error': 'request_rejected'})
        self.assertEqual(self.effects(), self.before + 1)
        armed, before, after, dropped = self.fault()
        self.assertEqual((armed, before, after), (0, self.before, self.before + 1))
        self.assertIsNotNone(dropped)
        # This is a deliberate test call, never recovery issued by the adapter.
        replay = self.browser.call('refundSelected', 'customer-request')
        self.assertEqual(replay[0], 200)
        self.assertEqual(replay[1]['status'], 'succeeded')
        self.assertEqual(replay[1]['data'], {'orderIds': [101, 102], 'affectedCount': 2})
        self.assertEqual(self.effects(), self.before + 1)

    def test_confirmation_and_approval_do_not_consume_fault_or_create_effect(self):
        self.arm()
        self.pending(); self.approve()
        self.assertEqual(self.fault(), (1, self.before, None, None))
        self.assertEqual(self.effects(), self.before)

    def test_bundled_unrelated_livewire_work_is_not_discarded(self):
        self.pending(); self.approve(); self.arm()
        response = self.browser.request('/livewire/update', {'_token': self.browser.csrf,
            'components': [{'snapshot': self.browser.snapshot, 'updates': {}, 'calls': [
                {'path': '', 'method': 'refundSelected', 'params': ['customer-request']},
                {'path': '', 'method': '$refresh', 'params': []}]}]})
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1]['components'][0]['effects']['returns'][0]['status'], 'succeeded')
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.fault(), (1, self.before, None, None))

    def test_different_action_input_is_not_discarded(self):
        self.pending(); self.approve(); self.arm(reason='duplicate-order')
        response = self.browser.call('refundSelected', 'customer-request')
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1]['status'], 'succeeded')
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.fault(), (1, self.before, None, None))

    def test_bundled_effect_followed_by_singleton_replay_cannot_consume_fault(self):
        self.pending(); self.approve(); self.arm()
        response = self.browser.request('/livewire/update', {'_token': self.browser.csrf,
            'components': [{'snapshot': self.browser.snapshot, 'updates': {}, 'calls': [
                {'path': '', 'method': 'refundSelected', 'params': ['customer-request']},
                {'path': '', 'method': '$refresh', 'params': []}]}]})
        self.assertEqual(response[0], 200)
        self.browser.snapshot = response[1]['components'][0]['snapshot']
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.fault(), (1, self.before, None, None))
        # The prior bundled call has committed; this exact intent is a replay,
        # so its own zero-effect invocation must not discard a successful response.
        replay = self.browser.call('refundSelected', 'customer-request')
        self.assertEqual(replay[0], 200)
        self.assertEqual(replay[1]['status'], 'succeeded')
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.fault(), (1, self.before, None, None))


if __name__ == '__main__':
    unittest.main(verbosity=2)
