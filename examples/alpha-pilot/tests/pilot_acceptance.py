"""Local pilot HTTP checks, using only registry-installed runtime packages."""
import json
import os
import sqlite3
import urllib.request
import unittest
from application_acceptance import ApplicationAcceptance, Client


class PilotAcceptance(ApplicationAcceptance):
    def setUp(self):
        super().setUp()
        with sqlite3.connect(self.database) as connection:
            self.global_before = connection.execute('SELECT COUNT(*) FROM effects').fetchone()[0]

    def unchanged(self):
        with sqlite3.connect(self.database) as connection:
            self.assertEqual(connection.execute('SELECT COUNT(*) FROM effects').fetchone()[0], self.global_before)

    def test_guest_orders_and_evidence_are_denied(self):
        guest = Client(self.url)
        self.assertEqual(guest.request('GET', '/orders')[0], 403)
        self.assertEqual(guest.request('GET', '/evidence')[0], 403)

    def test_human_page_and_built_browser_assets_are_served(self):
        with urllib.request.urlopen(self.url + '/') as response:
            self.assertEqual(response.status, 200)
            self.assertIn('text/html', response.headers['Content-Type'])
            self.assertIn('SurfaceRelay order desk', response.read().decode())
        with urllib.request.urlopen(self.url + '/assets/pilot.js') as response:
            self.assertEqual(response.status, 200)
            self.assertIn('Published browser runtime loaded', response.read().decode())

    def test_order_listing_does_not_cross_tenant(self):
        self.assertEqual([row['id'] for row in self.client.request('GET', '/orders')[1]['orders']], [101, 102])
        peer = Client(self.url)
        peer.login('peer')
        self.assertEqual(peer.request('POST', '/tenant', {'tenantId': 'tenant-b'})[0], 403)
        self.assertEqual(self.client.request('POST', '/tenant', {'tenantId': 'tenant-b'})[0], 200)
        self.assertEqual([row['id'] for row in self.client.request('GET', '/orders')[1]['orders']], [201])

    def test_effect_and_audit_observer_is_session_scoped(self):
        key, receipt = self.approve()
        self.assertEqual(self.client.invoke(key, receipt)[1]['status'], 'succeeded')
        other = Client(self.url)
        other.login()
        self.assertEqual(other.evidence(), {'effects': [], 'audits': []})
        serialized = json.dumps(self.client.evidence())
        self.assertNotIn(receipt, serialized)
        self.assertNotIn(key, serialized)

    def test_new_challenge_review_matches_its_server_context(self):
        key = self.key()
        first = self.client.invoke(key)[1]
        first_review = self.client.request('POST', '/review', {'challengeId': first['confirmation']['challengeId']})[1]
        self.assertEqual(first_review, {'tenantId': 'tenant-a', 'orderIds': [101], 'reason': 'customer-request'})
        self.client.request('POST', '/context', {'recordId': 102, 'selection': [102]})
        second = self.client.invoke(self.key())[1]
        self.assertEqual(second['status'], 'confirmation_required')
        second_review = self.client.request('POST', '/review', {'challengeId': second['confirmation']['challengeId']})[1]
        self.assertEqual(second_review, {'tenantId': 'tenant-a', 'orderIds': [102], 'reason': 'customer-request'})
        self.assertNotEqual(first['confirmation']['challengeId'], second['confirmation']['challengeId'])
        self.unchanged()

    def test_review_is_not_exposed_to_other_sessions_or_tenants(self):
        result = self.client.invoke(self.key())[1]
        challenge = result['confirmation']['challengeId']
        guest = Client(self.url)
        other = Client(self.url)
        other.login()
        self.assertEqual(guest.request('POST', '/review', {'challengeId': challenge})[0], 403)
        self.assertEqual(other.request('POST', '/review', {'challengeId': challenge})[0], 403)
        self.client.request('POST', '/tenant', {'tenantId': 'tenant-b'})
        self.assertEqual(self.client.request('POST', '/review', {'challengeId': challenge})[0], 403)
        self.unchanged()

    def test_direct_login_clears_prior_actor_observer(self):
        key, receipt = self.approve()
        self.assertEqual(self.client.invoke(key, receipt)[1]['status'], 'succeeded')
        self.client.login('peer')
        self.assertEqual(self.client.evidence(), {'effects': [], 'audits': []})

    def test_discovery_denial_and_exact_server_binding(self):
        guest = Client(self.url)
        self.assertEqual(guest.request('GET', '/surface')[0], 403)
        denied = Client(self.url)
        denied.login('denied')
        self.assertEqual(denied.request('GET', '/surface')[0], 403)
        surface = self.client.request('GET', '/surface')[1]
        self.assertEqual(surface['definition']['id'], surface['binding']['action']['id'])
        self.assertEqual(surface['binding']['bindingId'], self.client.request('GET', '/session')[1]['bindingId'])


if __name__ == '__main__':
    PilotAcceptance.url = 'http://127.0.0.1:8000'
    PilotAcceptance.concurrency_url = 'http://127.0.0.1:8001'
    PilotAcceptance.database = os.environ.get('PILOT_DATABASE', '/tmp/pilot/database/acceptance.sqlite')
    suite = unittest.defaultTestLoader.loadTestsFromTestCase(PilotAcceptance)
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    raise SystemExit(0 if result.wasSuccessful() else 1)
