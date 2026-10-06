"""Real signed Livewire HTTP acceptance against installed registry packages."""
import html
import http.cookiejar
import json
import os
import re
import sqlite3
import subprocess
import hashlib
import time
import unittest
import urllib.request
import urllib.error


class Browser:
    def __init__(self):
        self.url = os.environ.get('PILOT_URL', 'http://127.0.0.1:8000')
        self.cookies = http.cookiejar.CookieJar()
        self.http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        self.csrf = ''
        self.csrf = self.request('/session')[1]['csrfToken']
        self.snapshot = None

    def request(self, path, payload=None):
        request = urllib.request.Request(self.url + path, data=None if payload is None else json.dumps(payload).encode(),
            headers={'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': self.csrf, 'X-Livewire': 'true'})
        try:
            response = self.http.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            raw = response.read().decode()
            body = json.loads(raw) if 'application/json' in response.headers.get('Content-Type', '') else raw
            if isinstance(body, dict) and 'csrfToken' in body:
                self.csrf = body['csrfToken']
            return response.status, body

    def login(self, user='owner'):
        return self.request('/login', {'email': user + '@example.test', 'password': 'acceptance-password'})

    def mount(self):
        status, body = self.request('/')
        assert status == 200, (status, body)
        match = re.search(r'wire:snapshot="([^"]+)"', body)
        assert match, 'Actual mounted Livewire signed snapshot is missing'
        self.snapshot = html.unescape(match.group(1))
        token = re.search(r'name="csrf-token" content="([^"]+)"', body)
        assert token, 'Actual page CSRF token is missing'
        self.csrf = html.unescape(token.group(1))
        return body

    def call(self, method, *params, snapshot=None, updates=None):
        status, body = self.request('/livewire/update', {'_token': self.csrf, 'components': [{
            'snapshot': snapshot or self.snapshot, 'updates': updates or {},
            'calls': [{'path': '', 'method': method, 'params': list(params)}]}]})
        if status == 200:
            component = body['components'][0]
            self.snapshot = component['snapshot']
            return status, component['effects']['returns'][0], body
        return status, body, body


class LivewireAcceptance(unittest.TestCase):
    database = os.environ.get('PILOT_DATABASE', '/tmp/pilot/database/acceptance.sqlite')

    def setUp(self):
        self.browser = Browser()
        self.assertEqual(self.browser.login()[0], 200)
        self.browser.mount()
        self.before = self.effects()

    def effects(self):
        with sqlite3.connect(self.database) as connection:
            return connection.execute('SELECT COUNT(*) FROM effects').fetchone()[0]

    def pending(self):
        status, result, _ = self.browser.call('holdOrder', 'customer-request')
        self.assertEqual(status, 200)
        self.assertEqual(result['status'], 'confirmation_required')
        self.assertEqual(self.effects(), self.before)
        return result

    def approved(self):
        self.pending()
        response = self.browser.call('approveHold')
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1], {'status': 'approved'})
        self.assertEqual(self.effects(), self.before)

    def test_confirmation_approval_retry_and_replay_execute_once(self):
        self.approved()
        result = self.browser.call('holdOrder', 'customer-request')[1]
        self.assertEqual(result['status'], 'succeeded')
        self.assertEqual(result['data'], {'orderId': 101, 'held': True})
        self.assertEqual(self.effects(), self.before + 1)
        self.assertEqual(self.browser.call('holdOrder', 'customer-request')[1]['data'], result['data'])
        self.assertEqual(self.effects(), self.before + 1)

    def test_changed_record_rejects_old_signed_snapshot_authority(self):
        self.approved()
        approved_snapshot = self.browser.snapshot
        self.assertEqual(self.browser.call('selectOrder', 102)[0], 200)
        stale = self.browser.call('holdOrder', 'customer-request', snapshot=approved_snapshot)
        self.assertEqual(stale[0], 409)
        self.assertEqual(self.effects(), self.before)
        current = self.browser.call('holdOrder', 'customer-request')
        self.assertEqual(current[0], 200)
        self.assertEqual(current[1]['status'], 'confirmation_required')
        self.assertEqual(json.loads(self.browser.snapshot)['data']['recordId'], 102)
        self.assertEqual(self.effects(), self.before)

    def test_current_permission_revocation_denies_an_approved_call(self):
        self.approved()
        with sqlite3.connect(self.database) as connection:
            connection.execute("UPDATE memberships SET can_hold=0 WHERE user_id=1 AND tenant_id='tenant-a'")
        try:
            response = self.browser.call('holdOrder', 'customer-request')
            self.assertEqual(response[0], 200)
            self.assertEqual(response[1]['status'], 'rejected')
            self.assertEqual(response[1]['error']['code'], 'authorization_denied')
        finally:
            with sqlite3.connect(self.database) as connection:
                connection.execute("UPDATE memberships SET can_hold=1 WHERE user_id=1 AND tenant_id='tenant-a'")
        self.assertEqual(self.effects(), self.before)

    def test_caller_flags_receipts_and_locked_record_updates_cannot_bypass(self):
        response = self.browser.call('holdOrder', {'reason': 'customer-request', 'confirmed': True, 'receipt': 'forged'})
        self.assertEqual(response[0], 422)
        response = self.browser.call('holdOrder', 'customer-request', updates={'recordId': 201})
        self.assertGreaterEqual(response[0], 400)
        self.assertEqual(self.effects(), self.before)
        self.pending()

    def test_replaced_component_rejects_old_signed_snapshot(self):
        self.approved()
        old = self.browser.snapshot
        self.browser.mount()
        self.assertEqual(self.browser.call('holdOrder', 'customer-request', snapshot=old)[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_signed_snapshot_from_other_session_cannot_execute_or_approve(self):
        self.pending()
        old = self.browser.snapshot
        other = Browser(); other.login(); other.mount()
        self.assertEqual(other.call('approveHold', snapshot=old)[0], 409)
        self.assertEqual(other.call('holdOrder', 'customer-request', snapshot=old)[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_receipt_is_absent_from_snapshot_html_returns_and_audit(self):
        self.pending()
        approved = self.browser.call('approveHold')
        self.assertEqual(approved[0], 200)
        binding = json.loads(self.browser.snapshot)['data']['bindingId']
        script = r'''
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$record = Illuminate\Support\Facades\DB::table('pilot_livewire_bindings')->where('binding_id', $argv[1])->first();
$receipt = Illuminate\Support\Facades\Crypt::decryptString($record->receipt_ciphertext);
if (strlen($receipt) !== 43) { exit(2); }
echo hash('sha256', $receipt);
'''
        local = subprocess.run(['php', '-r', script, binding], capture_output=True, text=True, check=True)
        receipt_hash = local.stdout.strip()
        self.assertRegex(receipt_hash, r'^[a-f0-9]{64}$')
        result = self.browser.call('holdOrder', 'customer-request')
        self.assertEqual(result[1]['status'], 'succeeded')
        with sqlite3.connect(self.database) as connection:
            audits = connection.execute('SELECT trusted_context_manifest FROM surfacerelay_audit_events').fetchall()
        public = json.dumps([approved[2], result[2], audits])
        self.assertNotIn('HOLD_RAW_OUTPUT_SECRET', public)
        self.assertNotIn('acceptance-password', public)
        self.assertNotIn('confirmationReceipt', public)
        self.assertNotIn('receipt_ciphertext', public)
        candidates = re.findall(r'(?<![A-Za-z0-9_-])[A-Za-z0-9_-]{43}(?![A-Za-z0-9_-])', public)
        self.assertNotIn(receipt_hash, {hashlib.sha256(token.encode()).hexdigest() for token in candidates})

    def test_changed_input_cannot_spend_previous_confirmation(self):
        self.approved()
        response = self.browser.call('holdOrder', 'duplicate-order')
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1]['status'], 'confirmation_required')
        self.assertEqual(self.effects(), self.before)

    def test_extra_positional_authority_is_rejected(self):
        response = self.browser.call('holdOrder', 'customer-request', {'confirmed': True, 'receipt': 'forged'})
        self.assertEqual(response[0], 422)
        self.assertEqual(self.effects(), self.before)

    def test_old_displayed_challenge_cannot_approve_a_new_reason(self):
        first = self.pending()
        old_display = self.browser.snapshot
        next_result = self.browser.call('holdOrder', 'duplicate-order')
        self.assertEqual(next_result[0], 200)
        self.assertEqual(next_result[1]['status'], 'confirmation_required')
        self.assertNotEqual(first['confirmation']['challengeId'], next_result[1]['confirmation']['challengeId'])
        self.assertEqual(self.browser.call('approveHold', snapshot=old_display)[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_guest_and_denied_actor_cannot_discover_or_invoke_hold(self):
        for actor in [None, 'denied']:
            browser = Browser()
            if actor: browser.login(actor)
            page = browser.mount()
            self.assertNotIn('data-surfacerelay', page)
            response = browser.call('holdOrder', 'customer-request')
            self.assertEqual(response[0], 200)
            self.assertEqual(response[1]['status'], 'rejected')
            self.assertNotIn('confirmation', response[1])
        self.assertEqual(self.effects(), self.before)

    def test_actor_switch_rejects_previous_valid_signed_snapshot(self):
        self.approved()
        old = self.browser.snapshot
        self.browser.login('peer')
        self.assertEqual(self.browser.call('holdOrder', 'customer-request', snapshot=old)[0], 409)
        self.assertEqual(self.effects(), self.before)

    def test_foreign_record_selection_does_not_become_current_authority(self):
        self.assertEqual(self.browser.call('selectOrder', 201)[0], 403)
        self.assertEqual(self.effects(), self.before)
        self.pending()

    def test_tenant_switch_rejects_old_snapshot_and_new_mount_uses_current_tenant(self):
        self.approved()
        old = self.browser.snapshot
        self.assertEqual(self.browser.request('/tenant', {'tenantId': 'tenant-b'})[0], 200)
        self.assertEqual(self.browser.call('holdOrder', 'customer-request', snapshot=old)[0], 409)
        self.browser.mount()
        current = self.browser.call('holdOrder', 'customer-request')
        self.assertEqual(current[0], 200)
        self.assertEqual(current[1]['status'], 'confirmation_required')
        self.assertEqual(json.loads(self.browser.snapshot)['data']['recordId'], 201)
        peer = Browser(); peer.login('peer')
        self.assertEqual(peer.request('/tenant', {'tenantId': 'tenant-b'})[0], 403)
        self.assertEqual(self.effects(), self.before)

    def test_expired_server_receipt_requires_new_confirmation_without_effect(self):
        self.approved()
        ttl = max(5, min(120, int(os.environ.get('PILOT_CONFIRMATION_TTL', '60'))))
        time.sleep(ttl + 1)
        response = self.browser.call('holdOrder', 'customer-request')
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1]['status'], 'confirmation_required')
        self.assertEqual(self.effects(), self.before)


if __name__ == '__main__':
    result = unittest.TextTestRunner(verbosity=2).run(unittest.defaultTestLoader.loadTestsFromTestCase(LivewireAcceptance))
    raise SystemExit(0 if result.wasSuccessful() else 1)
