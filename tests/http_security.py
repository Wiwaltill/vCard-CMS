#!/usr/bin/env python3
"""Run with python3 tests/http_security.py; requires php on PATH.
Starts an isolated PHP server; never reads or modifies live JSON data.
"""
import concurrent.futures
import hashlib
import http.cookiejar
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import secrets
import shutil
import signal
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
checks = 0


def check(condition, message):
    global checks
    if not condition:
        raise AssertionError(message)
    checks += 1


with tempfile.TemporaryDirectory(prefix="vcard-http-") as temporary:
    fixture = Path(temporary)
    shutil.copytree(ROOT / "public", fixture / "public", ignore=shutil.ignore_patterns("uploads"))
    (fixture / "public/uploads").mkdir()
    (fixture / "data").mkdir()
    for name in ("config.sample.json", "contacts.sample.json"):
        shutil.copy(ROOT / "data" / name, fixture / "data" / name)
    config = json.loads((fixture / "data/config.sample.json").read_text())
    config.update(installed=True, admin_user="admin", api_token=secrets.token_hex(24))
    config["admin_password_hash"] = subprocess.check_output(
        ["php", "-r", "echo password_hash('test-password', PASSWORD_DEFAULT);"], text=True
    )
    (fixture / "data/config.json").write_text(json.dumps(config))
    (fixture / "data/contacts.json").write_text('[{"id":"ab","vorname":"Erika","nachname":"Mustermann"}]')
    with socket.socket() as probe:
        probe.bind(("127.0.0.1", 0))
        port = probe.getsockname()[1]
    base = f"http://127.0.0.1:{port}"
    environment = dict(os.environ, PHP_CLI_SERVER_WORKERS="4")
    server = subprocess.Popen(
        ["php", "-S", f"127.0.0.1:{port}", "-t", str(fixture / "public")],
        env=environment, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
    )
    try:
        for attempt in range(100):
            if server.poll() is not None:
                raise RuntimeError("PHP test server failed to start")
            try:
                with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                    break
            except OSError:
                time.sleep(0.05)
        else:
            raise RuntimeError("PHP test server startup timed out")
        jar = http.cookiejar.CookieJar()

        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self, *args):
                return None

        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())

        def request(path, method="GET", data=None, headers=None):
            headers = dict(headers or {})
            if isinstance(data, dict):
                data = urllib.parse.urlencode(data).encode()
                headers["Content-Type"] = "application/x-www-form-urlencoded"
            elif isinstance(data, str):
                data = data.encode()
            req = urllib.request.Request(base + path, data=data, headers=headers, method=method)
            try:
                response = opener.open(req, timeout=15)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                return response.status, response.read().decode(), response.headers

        import re

        def csrf(body):
            return re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)[1]

        forged = hashlib.sha256(b"kb-events-admin").hexdigest()
        status, _, _ = request("/admin/index.php", headers={"Cookie": "kb_admin_login=" + forged})
        check(status == 302, "Forged legacy cookie accepted")
        status, body, _ = request("/admin/login.php")
        check(status == 200, "Login form failed")
        token = csrf(body)
        original_session = next(c.value for c in jar if c.name == "vcard_admin_session")
        check(request("/admin/login.php", "POST", {"username": "admin", "password": "test-password"})[0] == 403, "Login without CSRF accepted")
        check(request("/admin/login.php", "POST", {"username": "admin", "password": "wrong", "csrf_token": token})[0] == 200, "Wrong-password flow failed")
        check(request("/admin/login.php", "POST", {"username": "admin", "password": "test-password", "csrf_token": token, "remember": "1"})[0] == 302, "Login failed")
        check(next(c.value for c in jar if c.name == "vcard_admin_session") != original_session, "Session ID not regenerated")
        remembered = next(c.value for c in jar if c.name == "vcard_remember")
        status, body, headers = request("/admin/index.php")
        check(status == 200 and "Mustermann" in body, "Admin page failed")
        check(headers.get("Cache-Control") == "no-store", "Admin response cacheable")
        token = csrf(body)
        class FormTokens(HTMLParser):
            def __init__(self):
                super().__init__()
                self.forms = []
                self.current = None

            def handle_starttag(self, tag, attrs):
                attrs = dict(attrs)
                if tag == "form":
                    self.current = attrs.get("method", "get").lower()
                    if self.current == "post":
                        self.forms.append(False)
                elif tag == "input" and self.current == "post" and attrs.get("name") == "csrf_token":
                    self.forms[-1] = True

            def handle_endtag(self, tag):
                if tag == "form":
                    self.current = None

        for page in ("new.php", "edit.php?id=ab", "settings.php", "datatypes.php", "backup.php", "api.php", "import_export.php"):
            status, rendered, _ = request("/admin/" + page)
            check(status == 200, "Admin form page failed: " + page)
            parser = FormTokens()
            parser.feed(rendered)
            check(parser.forms and all(parser.forms), "Missing form CSRF field: " + page)
        check(request("/admin/delete.php?id=ab")[0] == 405, "GET deletion accepted")
        check(request("/admin/delete.php", "POST", {"id": "ab"})[0] == 403, "Deletion without CSRF accepted")
        check(request("/admin/delete.php", "POST", {"id": "ab", "csrf_token": token})[0] == 302, "POST deletion failed")
        auth = {"X-API-Token": config["api_token"], "Content-Type": "application/json"}
        status, body, headers = request("/api.php", headers=auth)
        check(status == 200 and body == "[]", "Contact deletion failed")
        check(headers.get("Cache-Control") == "no-store", "API response cacheable")
        check(request("/api.php?token=" + config["api_token"])[0] == 401, "Query token accepted")
        check(request("/api.php", "POST", "{bad", auth)[0] == 400, "Malformed JSON accepted")
        check(request("/api.php", "POST", '{"id":"bad/slash"}', auth)[0] == 422, "Invalid ID accepted")
        check(request("/api.php", "POST", '{"id":"abcdefgh","vorname":"Erika"}', auth)[0] == 201, "API create failed")
        check(request("/api.php", "POST", '{"id":"abcdefgh"}', auth)[0] == 409, "Duplicate ID accepted")
        check(request("/api.php?id=abcdefgh", "PUT", '{"position":"Design"}', auth)[0] == 200, "API update failed")
        check(request("/api.php?id=abcdefgh", "DELETE", headers=auth)[0] == 200, "API deletion failed")
        # Independent API clients ensure PHP session locking cannot mask storage races.
        def create_contact(index):
            req = urllib.request.Request(base + "/api.php", method="POST", headers=auth,
                                         data=json.dumps({"id": f"parallel{index}", "vorname": "Test"}).encode())
            with urllib.request.urlopen(req, timeout=30) as response:
                return response.status

        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            statuses = list(pool.map(create_contact, range(20)))
        check(all(status == 201 for status in statuses), "Concurrent API write failed")
        check(len(json.loads(request("/api.php", headers=auth)[1])) == 20, "Concurrent writes lost contacts")
        check(request("/admin/logout.php")[0] == 405, "GET logout accepted")
        check(request("/admin/logout.php", "POST", {"csrf_token": token})[0] == 302, "Logout failed")
        check(request("/admin/index.php", headers={"Cookie": "vcard_remember=" + remembered})[0] == 302, "Revoked token accepted")
        print(f"OK: {checks} HTTP security checks passed, including 20 concurrent writes.")
    finally:
        os.killpg(server.pid, signal.SIGTERM)
        server.wait(timeout=10)
