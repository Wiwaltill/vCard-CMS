#!/usr/bin/env python3
"""Offline tests for the vendor release checker."""
import importlib.util
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("vendor_updates", ROOT / "scripts/check_vendor_updates.py")
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)


class VendorUpdates(unittest.TestCase):
    packages = [{"name": "example/library", "repository": "example/library", "version": "5.9.0"}]

    def release(self, tag):
        return lambda repository: {"tag_name": tag, "draft": False, "prerelease": False}

    def test_numeric_comparison_and_major(self):
        result = checker.check_packages(self.packages, self.release("v5.10.0"))[0]
        self.assertTrue(result["update"])
        self.assertFalse(result["major"])
        result = checker.check_packages(self.packages, self.release("6.0.0"))[0]
        self.assertTrue(result["major"])

    def test_equal_and_older_do_not_notify(self):
        for tag in ["v5.9.0", "5.8.9"]:
            self.assertFalse(checker.check_packages(self.packages, self.release(tag))[0]["update"])

    def test_dependency_constraint_remains_visible(self):
        packages = [dict(self.packages[0], update_note="QR requires ^3.2.1 <script>")]
        result = checker.check_packages(packages, self.release("6.0.0"))[0]
        self.assertTrue(result["update"])
        self.assertIn("QR requires ^3.2.1 &lt;script&gt;", checker.report([result]))

    def test_api_error_is_not_reported_as_current(self):
        def broken(repository):
            raise RuntimeError("GitHub API: HTTP 403")
        result = checker.check_packages(self.packages, broken)[0]
        self.assertIn("403", result["error"])
        self.assertIn("Prüfung fehlgeschlagen", checker.report([result]))

    def test_unstable_release_rejected(self):
        result = checker.check_packages(self.packages, lambda repository: {"tag_name": "6.0.0", "prerelease": True})[0]
        self.assertIn("error", result)

    def test_checks_continue_after_one_api_failure(self):
        packages = self.packages + [{"name":"example/second", "repository":"example/second", "version":"1.0.0"}]
        def fetch(repository):
            if repository == "example/library":
                raise RuntimeError("Unavailable")
            return {"tag_name":"1.0.1"}
        results = checker.check_packages(packages, fetch)
        self.assertIn("error", results[0])
        self.assertTrue(results[1]["update"])

    def test_unsafe_repository_rejected_before_network_call(self):
        with self.assertRaises(ValueError):
            checker.check_packages([dict(self.packages[0], repository="example/library?token=secret")], self.release("6.0.0"))

    def test_diagnostic_text_cannot_inject_markup(self):
        result = dict(self.packages[0], error="<script> | bad\nmessage", update=False)
        summary = checker.report([result])
        self.assertNotIn("<script>", summary)
        self.assertIn("&lt;script&gt;", summary)
        self.assertIn("&#124;", summary)
        self.assertEqual(checker.annotation("bad%\ntext"), "bad%25%0Atext")


if __name__ == "__main__":
    unittest.main()
