"""Exercise real PHP lock controllers with an isolated SQLite settings/users fixture.

Run: python3 c69t/tests/site_lock_test.py
Requires PHP with pdo_sqlite. Production credentials/database are never used.
"""
import http.cookiejar
import re
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


with tempfile.TemporaryDirectory() as directory:
    root = Path(directory)
    source = Path(__file__).resolve().parents[1]
    for file in source.glob('*.php'):
        shutil.copy(file, root / file.name)
    config = (root / 'config.php').read_text()
    start = config.index('$host =')
    end = config.index("require_once __DIR__ . '/site_lock.php';")
    config = config[:start] + "$pdo = new PDO('sqlite:' . __DIR__ . '/test.sqlite');\n" + config[end:]
    (root / 'config.php').write_text(config)
    # Protected probe exercises the same bootstrap without unrelated dashboard tables.
    (root / 'probe.php').write_text("<?php require 'config.php'; requireLogin(); echo 'protected';")
    password = subprocess.check_output(
        ['php', '-r', "echo password_hash('test-password', PASSWORD_DEFAULT);"], text=True
    )
    db = sqlite3.connect(root / 'test.sqlite')
    db.executescript('''
        CREATE TABLE settings (setting_key TEXT PRIMARY KEY, setting_value TEXT);
        INSERT INTO settings VALUES ('site_locked', '1');
        CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, role TEXT, password TEXT);
    ''')
    db.executemany('INSERT INTO users VALUES (?, ?, ?, ?)', [
        (1, 'admin', 'admin', password), (2, 'operator', 'operator', password),
    ])
    db.commit()
    # SQLite equivalent of the existing production MySQL settings upsert.
    config = config.replace('ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                            'ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value')
    (root / 'config.php').write_text(config)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root)],
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    client = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(page, data=None):
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = client.open(f'http://127.0.0.1:{port}/{page}', encoded)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read().decode()

    def token():
        return re.search(r'name="csrf" value="([^"]+)"', request('site_lock_access.php')[2])[1]

    def post(action, **extra):
        return request('site_lock_access.php', dict(csrf=token(), action=action,
                                                   username='admin', password='test-password', **extra))

    def locked():
        return db.execute("SELECT setting_value FROM settings WHERE setting_key='site_locked'").fetchone()[0]

    try:
        for attempt in range(40):
            try:
                request('shutdown.php')
                break
            except urllib.error.URLError:
                time.sleep(0.05)
        for file in source.glob('*.php'):
            if file.name not in ['shutdown.php', 'site_lock_access.php', 'logout.php',
                                  'upload_hmi_sections.php', 'nav.php', 'site_lock.php']:
                status, headers, _ = request(file.name)
                assert status == 303 and headers['Location'] == '/shutdown.php', file.name
        body = request('shutdown.php')[2]
        assert 'Logging System Has Been Shutdown' in body and 'Contact A Site Admin' in body
        assert request('upload_hmi_sections.php')[0] == 405
        assert 'session expired' in request('site_lock_access.php', {'action': 'unlock'})[2]
        assert locked() == '1'
        for username, password in [('operator', 'test-password'), ('admin', 'wrong')]:
            body = request('site_lock_access.php', dict(csrf=token(), action='unlock',
                           username=username, password=password))[2]
            assert 'Invalid administrator credentials' in body and locked() == '1'
        assert post('access')[0] == 303 and locked() == '1'
        assert request('probe.php')[2] == 'protected'
        db.execute("UPDATE users SET role='operator' WHERE id=1")
        db.commit()
        assert request('probe.php')[0] == 303  # Session role alone cannot bypass.
        db.execute("UPDATE users SET role='admin' WHERE id=1")
        db.commit()
        assert post('unlock')[0] == 303 and locked() == '0'
        assert 'Invalid administrator credentials' in request('site_lock_access.php',
               dict(csrf=token(), action='lock', password='wrong'))[2]
        assert locked() == '0'
        assert post('lock')[0] == 303 and locked() == '1'
        assert request('probe.php')[2] == 'protected'
        request('logout.php')
        assert request('probe.php')[0] == 303
        assert post('unlock')[0] == 303 and locked() == '0'
        request('logout.php')
        assert request('login.php')[0] == 200
        request('login.php', {'username': 'operator', 'password': 'test-password'})
        db.execute("UPDATE settings SET setting_value='1' WHERE setting_key='site_locked'")
        db.commit()
        assert request('probe.php')[0] == 303  # Already signed-in operators are blocked.
        print('PASS: redirects, shutdown copy, HMI exception, CSRF, credentials, admin bypass, demotion, lock/unlock, existing operator sessions')
    finally:
        server.terminate()
        server.wait()
        db.close()
