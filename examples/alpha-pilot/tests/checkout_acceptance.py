"""Local simulated 3D checkout acceptance; no bank, money or real OTP service."""
import json
import os
import concurrent.futures
import time
import threading
import uuid
import subprocess
import sqlite3
import unittest
import urllib.request
from application_acceptance import Client


class CheckoutAcceptance(unittest.TestCase):
    url = 'http://127.0.0.1:8000'
    database = os.environ.get('PILOT_DATABASE', '/tmp/pilot/database/acceptance.sqlite')

    def setUp(self):
        self.client = Client(self.url)
        self.assertEqual(self.client.login()[0], 200)
        self.binding = self.client.request('GET', '/session')[1]['bindingId']
        self.before = self.effects()

    def effects(self):
        with sqlite3.connect(self.database) as connection:
            # Allows the first RED run to identify missing routes rather than DDL.
            exists = connection.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name='pilot_payment_effects'").fetchone()
            return connection.execute('SELECT COUNT(*) FROM pilot_payment_effects').fetchone()[0] if exists else 0

    def invoke(self, client=None, **extra):
        return (client or self.client).request('POST', '/checkout/invoke',
            {'bindingId': self.binding, 'input': {'method': 'test-card'}, **extra})

    def start(self):
        response = self.invoke()
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1]['status'], 'confirmation_required')
        state = self.client.request('GET', '/checkout/status')[1]
        self.assertEqual(state['status'], 'pending')
        self.assertRegex(state['nextPageUrl'], r'^/3d/[a-f0-9]{32}$')
        self.assertEqual(state['orderId'], 101)
        return state

    def verify(self, state, code='123456', client=None):
        return (client or self.client).request('POST', state['nextPageUrl'] + '/verify', {'code': code})

    def test_wrong_code_then_correct_code_then_tool_completion_and_replay(self):
        state = self.start()
        self.assertEqual(self.verify(state, '111111')[0], 422)
        self.assertEqual(self.effects(), self.before)
        self.assertEqual(self.client.request('GET', '/checkout/status')[1]['status'], 'pending')
        self.assertEqual(self.verify(state)[1]['status'], 'verified')
        self.assertEqual(self.effects(), self.before, '3D verification itself must not execute payment')
        completed = self.invoke()
        self.assertEqual(completed[0], 200)
        self.assertEqual(completed[1]['status'], 'succeeded')
        self.assertEqual(completed[1]['data']['orderId'], 101)
        self.assertTrue(completed[1]['data']['simulation'])
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.invoke()[1]['data'], completed[1]['data'])
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.client.request('GET', '/checkout/status')[1]['status'], 'completed')
        self.assertIsNone(self.client.request('GET', '/checkout/status')[1]['nextPageUrl'])

    def test_distinct_3d_page_is_available_only_to_its_owner(self):
        state = self.start()
        request = urllib.request.Request(self.url + state['nextPageUrl'], headers={
            'Cookie': '; '.join(f'{cookie.name}={cookie.value}' for cookie in self.client.cookies)})
        with urllib.request.urlopen(request) as response:
            text = response.read().decode()
            self.assertIn('Simulated 3D verification', text)
            self.assertIn('123456', text)
            self.assertNotIn('receipt', text.lower())
        guest = Client(self.url)
        self.assertEqual(guest.request('GET', state['nextPageUrl'])[0], 403)
        self.assertEqual(self.effects(), self.before)

    def test_three_wrong_codes_fail_closed_and_correct_code_cannot_unlock(self):
        state = self.start()
        self.assertEqual(self.verify(state, '111111')[0], 422)
        self.assertEqual(self.verify(state, '111111')[0], 422)
        self.assertEqual(self.verify(state, '111111')[0], 423)
        self.assertEqual(self.client.request('GET', '/checkout/status')[1]['status'], 'failed')
        self.assertEqual(self.verify(state)[0], 409)
        self.assertEqual(self.invoke()[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_expired_flow_is_rejected(self):
        state = self.start()
        with sqlite3.connect(self.database) as connection:
            connection.execute('UPDATE pilot_checkouts SET expires_at=0 WHERE id=?', (state['id'],))
        self.assertEqual(self.verify(state)[0], 409)
        self.assertEqual(self.client.request('GET', '/checkout/status')[1]['status'], 'expired')
        self.assertEqual(self.invoke()[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_real_receipt_expiry_rejects_completion_even_when_flow_expiry_is_extended(self):
        state = self.start()
        self.assertEqual(self.verify(state)[0], 200)
        ttl = max(5, min(120, int(os.environ.get('PILOT_CONFIRMATION_TTL', '60'))))
        with sqlite3.connect(self.database) as connection:
            connection.execute('UPDATE pilot_checkouts SET expires_at=? WHERE id=?', (int(time.time()) + ttl + 120, state['id']))
        time.sleep(ttl + 1)
        result = self.invoke()
        self.assertEqual(result[0], 200)
        self.assertEqual(result[1]['status'], 'confirmation_required')
        self.assertEqual(self.client.request('GET', '/checkout/status')[1]['status'], 'expired')
        self.assertEqual(self.effects(), self.before)

    def test_concurrent_completion_uses_distinct_workers_and_one_effect(self):
        state = self.start()
        self.assertEqual(self.verify(state)[0], 200)
        cookie = '; '.join(f'{item.name}={item.value}' for item in self.client.cookies)
        headers = {'Content-Type': 'application/json', 'Accept': 'application/json',
            'Cookie': cookie, 'X-CSRF-TOKEN': self.client.csrf, 'X-Acceptance-Barrier': uuid.uuid4().hex}
        barrier = threading.Barrier(2)
        payload = json.dumps({'bindingId': self.binding, 'input': {'method': 'test-card'}}).encode()
        def complete(port):
            barrier.wait(timeout=5)
            request = urllib.request.Request(f'http://127.0.0.1:{port}/checkout/invoke', data=payload, headers=headers)
            with urllib.request.urlopen(request, timeout=20) as response:
                return json.loads(response.read()), response.headers['X-Worker-Pid']
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            responses = list(pool.map(complete, [8000, 8001]))
        self.assertEqual(len({row[1] for row in responses}), 2)
        self.assertEqual([row[0]['status'] for row in responses], ['succeeded', 'succeeded'])
        self.assertEqual(responses[0][0]['data'], responses[1][0]['data'])
        self.assertEqual(self.effects(), self.before + 1)

    def test_cross_session_and_tenant_cannot_verify_or_complete(self):
        state = self.start()
        other = Client(self.url)
        other.login()
        self.assertEqual(self.verify(state, client=other)[0], 403)
        self.assertEqual(other.request('GET', '/checkout/status')[1]['status'], 'none')
        self.client.request('POST', '/tenant', {'tenantId': 'tenant-b'})
        self.assertEqual(self.verify(state)[0], 403)
        self.assertEqual(self.invoke()[0], 403)
        self.assertEqual(self.effects(), self.before)

    def test_changed_record_and_expired_binding_reject_verified_receipt(self):
        state = self.start()
        self.assertEqual(self.verify(state)[0], 200)
        self.client.request('POST', '/context', {'recordId': 102, 'selection': [102]})
        self.assertEqual(self.invoke()[0], 403)
        self.client.request('POST', '/context', {'recordId': 101, 'selection': [101]})
        self.client.request('POST', '/binding', {'operation': 'expire'})
        self.assertEqual(self.invoke()[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_tool_input_cannot_bypass_code_or_confirmation_and_denied_actor_cannot_start(self):
        for extra in ({'receipt': 'forged'}, {'confirmed': True},
                      {'input': {'method': 'test-card', 'code': '123456'}},
                      {'input': {'method': 'test-card', 'tenantId': 'tenant-b'}}):
            self.assertEqual(self.invoke(**extra)[0], 422)
        denied = Client(self.url)
        denied.login('denied')
        binding = denied.request('GET', '/session')[1]['bindingId']
        result = self.invoke(client=denied, bindingId=binding)
        self.assertEqual(result[0], 200)
        self.assertEqual(result[1]['status'], 'rejected')
        self.assertEqual(result[1]['error']['code'], 'authorization_denied')
        self.assertEqual(self.effects(), self.before)

    def test_duplicate_verification_does_not_issue_another_receipt_or_effect(self):
        state = self.start()
        self.assertEqual(self.verify(state)[0], 200)
        self.assertEqual(self.verify(state)[0], 409)
        self.assertEqual(self.invoke()[1]['status'], 'succeeded')
        self.assertEqual(self.verify(state)[0], 409)
        self.assertEqual(self.effects(), self.before + 1)

    def test_public_results_status_and_audits_do_not_expose_code_or_receipt(self):
        state = self.start()
        approved = self.verify(state)[1]
        result = self.invoke()[1]
        status = self.client.request('GET', '/checkout/status')[1]
        evidence = self.client.evidence()
        serialized = json.dumps([approved, result, status, evidence])
        self.assertNotIn('123456', serialized)
        self.assertNotIn('111111', serialized)
        self.assertNotIn('receipt_ciphertext', serialized)
        self.assertNotIn('receipt', approved)
        self.assertNotIn('receipt', status)
        self.assertNotIn('review', result)

    def test_stale_expiry_observer_cannot_overwrite_completed_transition(self):
        state = self.start()
        # Laravel's query listener runs after SELECT fetched its stale row and
        # before owned() evaluates expiry. It deterministically models another
        # worker committing completed in that interval, without a production hook.
        script = r'''
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::login(App\User::find(1));
$id = $argv[1];
$flow = Illuminate\Support\Facades\DB::table('pilot_checkouts')->where('id', $id)->first();
session()->put(['checkout_id' => $id, 'tenant' => $flow->tenant_id,
    'record' => $flow->record_id, 'selection' => [101],
    'binding' => ['id' => $flow->binding_id, 'driver' => 'acceptance.http',
        'session' => session()->getId(), 'expires' => time() + 3600]]);
Illuminate\Support\Facades\DB::table('pilot_checkouts')->where('id', $id)->update([
    'session_hash' => hash('sha256', session()->getId()), 'expires_at' => 0]);
$transitioned = false;
Illuminate\Support\Facades\DB::listen(function ($query) use ($id, &$transitioned) {
    if (!$transitioned && str_starts_with(strtolower($query->sql), 'select')
        && str_contains($query->sql, 'pilot_checkouts')) {
        $transitioned = true;
        Illuminate\Support\Facades\DB::table('pilot_checkouts')->where('id', $id)
            ->update(['status' => 'completed', 'receipt_ciphertext' => null]);
    }
});
echo json_encode(App\CheckoutRuntime::status(), JSON_THROW_ON_ERROR);
'''
        process = subprocess.run(['php', '-r', script, state['id']], capture_output=True, text=True, check=True)
        self.assertEqual(json.loads(process.stdout)['status'], 'completed')
        with sqlite3.connect(self.database) as connection:
            self.assertEqual(connection.execute('SELECT status FROM pilot_checkouts WHERE id=?', (state['id'],)).fetchone()[0], 'completed')


if __name__ == '__main__':
    result = unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(CheckoutAcceptance))
    raise SystemExit(0 if result.wasSuccessful() else 1)
