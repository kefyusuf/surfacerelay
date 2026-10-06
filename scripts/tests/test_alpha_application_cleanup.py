"""A failed first server must not leave the second owned server running."""
import os
import subprocess
import sys
import unittest

from scripts.laravel_alpha_acceptance import stop_servers


@unittest.skipUnless(os.name == "posix", "HTTP process groups require Linux")
class ServerCleanupTest(unittest.TestCase):
    def test_already_exited_first_server_does_not_skip_second(self):
        first = subprocess.Popen([sys.executable, "-c", "pass"], start_new_session=True)
        second = subprocess.Popen([sys.executable, "-c", "import time; time.sleep(60)"], start_new_session=True)
        try:
            first.wait(timeout=5)
            stop_servers(first, second)
            self.assertIsNotNone(second.poll())
        finally:
            stop_servers(first, second)
