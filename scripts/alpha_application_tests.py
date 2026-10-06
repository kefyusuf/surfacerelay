"""HTTP acceptance checks against an isolated, installed Laravel application."""
import concurrent.futures
import http.cookiejar
import json
import sqlite3
import threading
import time
import unittest
import urllib.error
import urllib.request
import uuid


class Client:
    def __init__(self, url):
        self.url = url
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        self.csrf = self.request("GET", "/session")[1]["csrfToken"]

    def request(self, method, path, payload=None, headers=None):
        request = urllib.request.Request(self.url + path, method=method,
            data=None if payload is None else json.dumps(payload).encode(),
            headers={"Accept": "application/json", "Content-Type": "application/json",
                     "X-CSRF-TOKEN": getattr(self, "csrf", ""), **(headers or {})})
        try:
            response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            body = json.loads(response.read())
            if "csrfToken" in body:
                self.csrf = body["csrfToken"]
            return response.status, body, response.headers.get("X-Worker-Pid")

    def login(self, user="owner"):
        return self.request("POST", "/login", {"email": user + "@example.test", "password": "acceptance-password"})

    def invoke(self, key=None, receipt=None, **extra):
        payload = {"input": {"reason": "customer-request"}, **extra}
        if key is not None:
            payload["idempotencyKey"] = key
        if receipt is not None:
            payload["receipt"] = receipt
        return self.request("POST", "/invoke", payload)

    def evidence(self):
        return self.request("GET", "/evidence")[1]


class ApplicationAcceptance(unittest.TestCase):
    url = None
    database = None
    concurrency_url = None

    def setUp(self):
        self.client = Client(self.url)
        self.assertEqual(self.client.login()[0], 200)
        self.before = len(self.client.evidence()["effects"])

    def key(self):
        return "acceptance-" + uuid.uuid4().hex

    def approve(self, key=None, client=None):
        client = client or self.client
        key = key or self.key()
        status, result, _ = client.invoke(key)
        self.assertEqual(status, 200)
        self.assertEqual(result["status"], "confirmation_required")
        status, result, _ = client.request("POST", "/approve", {"challengeId": result["confirmation"]["challengeId"]})
        self.assertEqual(status, 200)
        return key, result["receipt"]

    def unchanged(self):
        self.assertEqual(len(self.client.evidence()["effects"]), self.before)

    def blocked(self, response):
        status, body, _ = response
        self.assertLess(status, 500)
        self.assertTrue(status >= 400 or body["status"] in ("rejected", "confirmation_required"))

    def rejected(self, response, code):
        self.assertEqual(response[0], 200)
        self.assertEqual(response[1]["status"], "rejected")
        self.assertEqual(response[1]["error"]["code"], code)

    def test_authenticated_effect_output_and_audit(self):
        key, receipt = self.approve()
        status, result, _ = self.client.invoke(key, receipt)
        self.assertEqual(status, 200)
        self.assertEqual(result["status"], "succeeded")
        self.assertEqual(result["data"], {"orderIds": [101], "refundedCount": 1})
        evidence = self.client.evidence()
        self.assertEqual(len(evidence["effects"]), self.before + 1)
        effect = evidence["effects"][-1]
        self.assertEqual((effect["actor_id"], effect["tenant_id"], effect["order_ids"]), (1, "tenant-a", [101]))
        self.assertTrue(evidence["audits"])
        matching = [row for row in evidence["audits"] if row["correlation_id"] == result["correlationId"]]
        self.assertEqual(len(matching), 1)
        self.assertEqual(matching[0]["outcome_kind"], "completed")
        self.assertTrue(matching[0]["human_confirmation_present"])
        audits = json.dumps(evidence["audits"])
        for raw in (key, receipt, "customer-request", "ACCEPTANCE_RAW_OUTPUT_SECRET", "acceptance-password"):
            self.assertNotIn(raw, audits)

    def test_guest_denied_actor_and_forged_authority(self):
        guest = Client(self.url)
        self.blocked(guest.invoke(self.key(), metadata={"actorId": 1, "tenantId": "tenant-a", "confirmed": True}))
        denied = Client(self.url)
        self.assertEqual(denied.login("denied")[0], 200)
        denied_result = denied.invoke(self.key(), metadata={"permissions": ["acceptance.refund"], "actorId": 1})
        self.assertEqual(denied_result[0], 200)
        self.assertEqual(denied_result[1]["status"], "rejected")
        self.assertNotIn("confirmation", denied_result[1])
        self.rejected(denied_result, "authorization_denied")
        audit = [row for row in denied.evidence()["audits"] if row["correlation_id"] == denied_result[1]["correlationId"]]
        self.assertEqual(len(audit), 1)
        self.assertEqual(audit[0]["halted_at"], "authorization")
        self.blocked(self.client.invoke(self.key(), input={"reason": "customer-request", "tenantId": "tenant-b", "orderId": 201}))
        self.assertEqual(self.client.request("POST", "/context", {"recordId": 201, "selection": [201]})[0], 403)
        self.unchanged()

    def test_required_key_and_invalid_input(self):
        self.rejected(self.client.invoke(), "idempotency_key_required")
        self.rejected(self.client.invoke(""), "idempotency_key_required")
        self.rejected(self.client.invoke("x" * 241), "idempotency_key_invalid")
        self.rejected(self.client.invoke(self.key(), input={"reason": "untrusted"}), "input_validation_failed")
        self.unchanged()

    def test_actor_and_tenant_switch_receipt_scope(self):
        key, receipt = self.approve()
        self.assertEqual(self.client.login("peer")[0], 200)
        self.blocked(self.client.invoke(key, receipt))
        self.assertEqual(self.client.login()[0], 200)
        key, receipt = self.approve()
        self.assertEqual(self.client.request("POST", "/tenant", {"tenantId": "tenant-b"})[0], 200)
        self.blocked(self.client.invoke(key, receipt))
        self.unchanged()

    def test_input_record_selection_and_session_receipt_scope(self):
        key, receipt = self.approve()
        self.blocked(self.client.invoke(key, receipt, input={"reason": "duplicate-order"}))
        key, receipt = self.approve()
        self.client.request("POST", "/context", {"recordId": 102, "selection": [101]})
        self.blocked(self.client.invoke(key, receipt))
        key, receipt = self.approve()
        self.client.request("POST", "/context", {"recordId": 102, "selection": [102]})
        self.blocked(self.client.invoke(key, receipt))
        other = Client(self.url)
        other.login()
        self.blocked(other.invoke(key, receipt))
        self.unchanged()

    def test_receipt_expiry_and_single_use(self):
        key, receipt = self.approve()
        time.sleep(6)
        self.blocked(self.client.invoke(key, receipt))
        self.unchanged()
        key, receipt = self.approve()
        self.assertEqual(self.client.invoke(key, receipt)[1]["status"], "succeeded")
        self.blocked(self.client.invoke(self.key(), receipt))
        self.assertEqual(len(self.client.evidence()["effects"]), self.before + 1)

    def test_idempotency_replay_conflict_and_current_authorization(self):
        key, receipt = self.approve()
        self.assertEqual(self.client.invoke(key, receipt)[1]["status"], "succeeded")
        replay = self.client.invoke(key)[1]
        self.assertEqual(replay["status"], "succeeded")
        self.assertEqual(replay["data"], {"orderIds": [101], "refundedCount": 1})
        self.rejected(self.client.invoke(key, input={"reason": "duplicate-order"}), "idempotency_conflict")
        with sqlite3.connect(self.database) as connection:
            connection.execute("UPDATE memberships SET can_refund=0 WHERE user_id=1 AND tenant_id='tenant-a'")
        try:
            self.rejected(self.client.invoke(key), "authorization_denied")
        finally:
            with sqlite3.connect(self.database) as connection:
                connection.execute("UPDATE memberships SET can_refund=1 WHERE user_id=1 AND tenant_id='tenant-a'")
        self.assertEqual(len(self.client.evidence()["effects"]), self.before + 1)

    def test_binding_unknown_expired_unsupported_and_missing(self):
        key, receipt = self.approve()
        self.assertEqual(self.client.invoke(key, receipt, bindingId="unknown-binding")[0], 409)
        for operation in ("expire", "unsupported", "remove"):
            client = Client(self.url)
            client.login()
            key, receipt = self.approve(client=client)
            self.assertEqual(client.request("POST", "/binding", {"operation": operation})[0], 200)
            self.assertEqual(client.invoke(key, receipt)[0], 409)
        self.unchanged()

    def test_approval_session_csrf_and_login_boundaries(self):
        guest = Client(self.url)
        self.assertEqual(guest.request("POST", "/login", {"email": "owner@example.test", "password": "wrong"})[0], 401)
        self.assertIsNone(guest.request("GET", "/session")[1]["actorId"])
        key = self.key()
        challenge = self.client.invoke(key)[1]["confirmation"]["challengeId"]
        other = Client(self.url)
        other.login()
        self.assertEqual(other.request("POST", "/approve", {"challengeId": challenge})[0], 403)
        self.assertEqual(self.client.request("POST", "/approve", {"challengeId": challenge}, {"X-CSRF-TOKEN": "forged"})[0], 419)
        self.unchanged()

    def concurrent(self, payloads):
        cookie = "; ".join(f"{cookie.name}={cookie.value}" for cookie in self.client.cookies)
        csrf = self.client.csrf
        barrier = threading.Barrier(2)
        process_barrier = uuid.uuid4().hex

        def invoke(index_payload):
            index, payload = index_payload
            url = self.url if index == 0 else self.concurrency_url
            request = urllib.request.Request(url + "/invoke", data=json.dumps(payload).encode(),
                headers={"Content-Type": "application/json", "Accept": "application/json", "Cookie": cookie,
                         "X-CSRF-TOKEN": csrf, "X-Acceptance-Barrier": process_barrier})
            barrier.wait(timeout=5)
            start = time.monotonic()
            try:
                response = urllib.request.urlopen(request, timeout=20)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                return response.status, json.loads(response.read()), response.headers.get("X-Worker-Pid"), start, time.monotonic()

        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            responses = list(pool.map(invoke, enumerate(payloads)))
        self.assertEqual([result[0] for result in responses], [200, 200])
        self.assertTrue(all(result[1]["status"] in ("succeeded", "rejected", "confirmation_required") for result in responses))
        self.assertTrue(all(result[2] for result in responses))
        self.assertEqual(len({result[2] for result in responses}), 2, "Concurrency requires distinct PHP workers")
        self.assertLess(max(result[3] for result in responses), min(result[4] for result in responses))
        self.assertEqual(len(self.client.evidence()["effects"]), self.before + 1)
        return responses

    def test_concurrent_receipt_with_different_keys(self):
        _, receipt = self.approve()
        payloads = [{"input": {"reason": "customer-request"}, "receipt": receipt, "idempotencyKey": self.key()} for _ in range(2)]
        responses = self.concurrent(payloads)
        self.assertEqual(sum(result[1]["status"] == "succeeded" for result in responses), 1)

    def test_concurrent_key_with_different_receipts(self):
        key = self.key()
        receipts = [self.approve(key)[1] for _ in range(2)]
        self.assertEqual(len(set(receipts)), 2)
        self.concurrent([{"input": {"reason": "customer-request"}, "receipt": receipt, "idempotencyKey": key} for receipt in receipts])
        self.assertEqual(self.client.invoke(key)[1]["status"], "succeeded")
        self.assertEqual(len(self.client.evidence()["effects"]), self.before + 1)


def run_acceptance(url, database, method=None):
    ApplicationAcceptance.url = url
    ApplicationAcceptance.database = database
    suite = (unittest.TestSuite([ApplicationAcceptance(method)]) if method else
             unittest.defaultTestLoader.loadTestsFromTestCase(ApplicationAcceptance))
    return unittest.TextTestRunner(verbosity=2).run(suite)
