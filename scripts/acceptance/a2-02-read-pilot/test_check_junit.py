"""Synthetic receipt parser tests; not evidence for Laravel runtime acceptance."""
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest
from xml.etree.ElementTree import Element, SubElement, ElementTree
from check_junit import EXPECTED, summarize


class JunitReceiptTests(unittest.TestCase):
    def write_xml(self, path, *, omitted=None, mark=None, extra=None):
        root = Element('testsuites')
        suite = SubElement(root, 'testsuite')
        for name in sorted(EXPECTED):
            if name == omitted:
                continue
            tc = SubElement(suite, 'testcase', {'name': name})
            if name == sorted(EXPECTED)[0] and mark:
                SubElement(tc, mark)
        if extra:
            SubElement(suite, 'testcase', {'name': extra})
        ElementTree(root).write(path)

    def test_complete_twelve_cases_are_server_only(self):
        with TemporaryDirectory() as tmp:
            f = Path(tmp) / 'junit.xml'
            self.write_xml(f)
            proof = summarize(f)
            self.assertEqual(proof['tests'], 12)
            self.assertEqual(proof['result'], 'PASS_SERVER_READ_ONLY')
            self.assertIn('NOT RUN', proof['scope'])

    def test_missing_case_cannot_pass(self):
        with TemporaryDirectory() as tmp:
            f = Path(tmp) / 'junit.xml'
            self.write_xml(f, omitted=sorted(EXPECTED)[0])
            with self.assertRaisesRegex(ValueError, 'identity or count'):
                summarize(f)

    def test_skipped_case_cannot_pass(self):
        with TemporaryDirectory() as tmp:
            f = Path(tmp) / 'junit.xml'
            self.write_xml(f, mark='skipped')
            with self.assertRaisesRegex(ValueError, 'skipped'):
                summarize(f)

    def test_failed_case_cannot_pass(self):
        with TemporaryDirectory() as tmp:
            f = Path(tmp) / 'junit.xml'
            self.write_xml(f, mark='failure')
            with self.assertRaisesRegex(ValueError, 'Failed'):
                summarize(f)

    def test_unexpected_extra_case_cannot_pass(self):
        with TemporaryDirectory() as tmp:
            f = Path(tmp) / 'junit.xml'
            self.write_xml(f, extra='unreviewed_test')
            with self.assertRaisesRegex(ValueError, 'identity or count'):
                summarize(f)


if __name__ == '__main__':
    unittest.main()
