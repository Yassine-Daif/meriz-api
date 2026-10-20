import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location('traffic', Path(__file__).with_name('traffic-report.py'))
traffic = importlib.util.module_from_spec(spec)
spec.loader.exec_module(traffic)


class TrafficPrivacyTest(unittest.TestCase):
    def line(self, path):
        return '203.0.113.12 - - [20/Oct/2026:12:00:00 +0000] "GET '+path+' HTTP/2.0" 200 123 "https://example.com/private?token=referer-secret" "Mozilla/5.0"'

    def test_verification_links_and_referrer_tokens_are_removed(self):
        _, line = traffic.sanitized(self.line('/api/email/verify/123/secret-hash?signature=secret-query'))
        self.assertNotIn('secret', line)
        self.assertNotIn('/private', line)
        self.assertIn('https://example.com/', line)
        self.assertIn('/api/email/action', line)

    def test_personal_identifiers_are_not_exported_as_page_paths(self):
        _, line = traffic.sanitized(self.line('/api/users/alice%40example.com/documents/af201b19-2000-4000-8000-abcdef123456'))
        self.assertNotIn('alice', line)
        self.assertNotIn('af201b19', line)
        self.assertIn('/api/users/:id/documents/:id', line)

    def test_countries_are_ranked_across_continents(self):
        report = {'countries': {'data': [
            {'items': [{'data': 'FR France', 'hits': {'count': 2}}]},
            {'items': [{'data': 'US United States', 'hits': {'count': 8}}]},
        ]}}
        self.assertEqual(traffic.rows(report, 'countries', True)[0]['label'], 'US United States')

    def test_invalid_lines_do_not_become_visits(self):
        self.assertIsNone(traffic.sanitized('invalid log entry'))

    def test_midnight_uses_the_french_calendar_day(self):
        stamp, line = traffic.sanitized(self.line('/').replace('20/Oct/2026:12:00:00', '19/Oct/2026:23:30:00'))
        self.assertEqual(stamp.day, 20)
        self.assertIn('[20/Oct/2026:01:30:00 +0200]', line)


if __name__ == '__main__':
    unittest.main()
