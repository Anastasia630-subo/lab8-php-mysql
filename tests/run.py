"""Integration tests. Use ONLY a dedicated demo database populated by bin/seed.php.
PHP_BIN=php LAB8_TEST_CONFIG=/absolute/admin.php python tests/run.py http://127.0.0.1:8080
The admin config is used only by the CLI helper; the web app uses its limited account.
"""
import concurrent.futures
import http.cookiejar
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import unittest
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
BASE = sys.argv[1].rstrip('/') if len(sys.argv) > 1 else 'http://127.0.0.1:8080'
if not re.fullmatch(r'http://(?:127\.0\.0\.1|localhost)(?::\d+)?(?:/[^?#]*)?', BASE):
    raise SystemExit('Use a local dedicated test server')
C = json.loads((ROOT / 'var/demo_credentials.json').read_text())
D = C['devices']
PHP = os.environ.get('PHP_BIN', 'php')

def sql(text, params=()):
    p = subprocess.run([PHP, str(ROOT / 'tests/db_cli.php')],
        input=json.dumps({'sql': text, 'params': params}), text=True,
        capture_output=True, check=True)
    return json.loads(p.stdout)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
    def request(self, path, data=None, headers=None, method=None):
        if isinstance(data, dict):
            data = urllib.parse.urlencode(data).encode()
        req = urllib.request.Request(BASE + path, data=data, headers=headers or {}, method=method)
        try:
            r = self.opener.open(req, timeout=15)
        except urllib.error.HTTPError as e:
            r = e
        return r.code, r.read().decode(), dict(r.headers)
    def login(self, name='nastya', password=None):
        code, body, _ = self.request('/login.php')
        token = re.search(r'name="csrf" value="([0-9a-f]+)"', body)[1]
        return self.request('/login.php', {'username': name,
            'password': password or C['password'], 'csrf': token})
    def token(self):
        _, body, _ = self.request('/index.php')
        return re.search(r'name="csrf" value="([0-9a-f]+)"', body)[1]

def api(data=None, device=0, token=None, uid=None):
    d = D[device]
    return Client().request('/device_status.php', json.dumps(data or {}).encode(), {
        'Content-Type': 'application/json', 'X-Device-UID': uid or d['uid'],
        'Authorization': 'Bearer ' + (token or d['token'])})

def reset():
    sql('UPDATE device_access SET window_start=NULL, request_count=0, blocked_until=NULL')
    sql('UPDATE devices SET window_start=NULL, request_count=0, blocked_until=NULL,last_seen=NULL')
    sql('UPDATE device_state SET temperature=NULL,relay=NULL,command=NULL,temperature_at=NULL,relay_at=NULL,command_at=NULL')
    sql('DELETE FROM user_events')
    sql('DELETE FROM device_events')

class Integration(unittest.TestCase):
    def setUp(self):
        reset()
        self.c = Client()
        self.assertEqual(self.c.login()[0], 303)

    def test_01_multiple_devices_and_owner(self):
        code, body, _ = self.c.request('/index.php')
        self.assertEqual(code, 200)
        self.assertIn(D[0]['uid'], body)
        self.assertIn(D[1]['uid'], body)
        self.assertNotIn(D[2]['uid'], body)
        self.assertEqual(self.c.request('/history.php?uid=' + D[2]['uid'])[0], 404)
        csrf = self.c.token()
        self.assertEqual(self.c.request('/control.php', {'uid':D[2]['uid'], 'csrf':csrf, 'command':'1'})[0], 404)

    def test_02_protocol_and_command(self):
        self.assertEqual(api({'Term':216,'Rele':0})[:2], (200,'COMMAND ? EOC'))
        csrf = self.c.token()
        self.assertEqual(self.c.request('/control.php', {'uid':D[0]['uid'], 'csrf':csrf, 'command':'1'})[0],303)
        row = sql('SELECT relay,command FROM device_state WHERE device_id=?',[D[0]['id']])[0]
        self.assertEqual((row['relay'],row['command']), (0,1))
        self.assertEqual(api({'Rele':1})[:2], (200,'COMMAND 1 EOC'))
        row = sql('SELECT temperature,relay FROM device_state WHERE device_id=?',[D[0]['id']])[0]
        self.assertEqual(float(row['temperature']),21.6)
        self.assertEqual(row['relay'],1)

    def test_03_history(self):
        csrf = self.c.token()
        self.c.request('/control.php', {'uid':D[0]['uid'],'csrf':csrf,'command':'0'})
        code,body,_ = self.c.request('/history.php?uid='+D[0]['uid'])
        self.assertEqual(code,200)
        self.assertIn('Выключить',body)
        self.assertIn('nastya',body)

    def test_04_user_trigger_boundary(self):
        csrf = self.c.token()  # First view counts as request 1.
        for _ in range(9):
            self.assertEqual(self.c.request('/control.php',{'uid':D[0]['uid'],'csrf':csrf,'command':'1'})[0],303)
        self.assertEqual(self.c.request('/control.php',{'uid':D[0]['uid'],'csrf':csrf,'command':'0'})[0],429)
        self.assertEqual(sql('SELECT command FROM device_state WHERE device_id=?',[D[0]['id']])[0]['command'],1)
        row = sql('SELECT request_count,blocked_until FROM device_access WHERE user_id=? AND device_id=?',[C['users'][0]['id'],D[0]['id']])[0]
        self.assertEqual(row['request_count'],11)
        self.assertIsNotNone(row['blocked_until'])
        _,body,_ = self.c.request('/index.php')
        card = re.search(r'<article[^>]+data-device="'+D[0]['uid']+r'".*?</article>',body,re.S)[0]
        self.assertIn('временно заблокирован',card)
        self.assertNotIn('name="command"',card)
        self.assertNotIn('Фактическое реле',card)
        self.assertIn(D[1]['uid'],body)
        self.assertEqual(self.c.request('/history.php?uid='+D[0]['uid'])[0],429)
        viewer = Client(); viewer.login('viewer')
        self.assertIn('Фактическое реле',viewer.request('/index.php')[1])

    def test_05_device_trigger_boundary(self):
        for _ in range(30):
            self.assertEqual(api({'Term':200,'Rele':0})[0],200)
        self.assertEqual(api({'Term':999,'Rele':1})[0],429)
        self.assertEqual(float(sql('SELECT temperature FROM device_state WHERE device_id=?',[D[0]['id']])[0]['temperature']),20)
        self.assertEqual(sql('SELECT request_count FROM devices WHERE id=?',[D[0]['id']])[0]['request_count'],31)
        _,body,_ = self.c.request('/index.php')
        self.assertIn('Подозрительное поведение устройства',body)
        self.assertEqual(api(device=1)[0],200)

    def test_06_expiry(self):
        sql("UPDATE device_access SET window_start=UTC_TIMESTAMP(6)-INTERVAL 120 SECOND,request_count=11,blocked_until=UTC_TIMESTAMP(6)-INTERVAL 1 SECOND")
        sql("UPDATE devices SET window_start=UTC_TIMESTAMP(6)-INTERVAL 120 SECOND,request_count=31,blocked_until=UTC_TIMESTAMP(6)-INTERVAL 1 SECOND")
        self.assertEqual(api()[0],200)
        self.assertIn('Фактическое реле',self.c.request('/index.php')[1])
        self.assertEqual(sql('SELECT request_count FROM devices WHERE id=?',[D[0]['id']])[0]['request_count'],1)

    def test_07_uid_and_authentication(self):
        self.assertEqual(api(token='0'*64)[0],401)
        self.assertEqual(api(uid='0'*32)[0],401)
        self.assertEqual(Client().request('/device_status.php?ID=1')[0],405)
        self.assertEqual(len(sql('SELECT * FROM device_events')),0)

    def test_08_input_validation(self):
        for data in [{'Term':"1' OR '1'='1"},{'Rele':'0 OR 1=1'}, {'Term':1251},{'Term':1.5},{'Rele':True},{'extra':1}]:
            self.assertEqual(api(data)[0],400)
        self.assertEqual(api({'Term':None,'Rele':0})[0],200)
        self.assertIsNone(sql('SELECT temperature FROM device_state WHERE device_id=?',[D[0]['id']])[0]['temperature'])

    def test_09_injection(self):
        # Isolated demonstration of unsafe concatenation; never a public endpoint.
        self.assertEqual(len(sql("SELECT id FROM devices WHERE id='1' OR '1'='1'")),3)
        self.assertEqual(len(sql('SELECT id FROM devices WHERE uid=?',["1' OR '1'='1"])),0)
        attacker = Client()
        self.assertEqual(attacker.login("' OR '1'='1' -- ")[0],401)
        self.assertEqual(api(uid="1' OR '1'='1")[0],400)
        self.assertEqual(self.c.request('/history.php?uid='+urllib.parse.quote("1' UNION SELECT 1 -- "))[0],400)
        csrf=self.c.token()
        self.assertEqual(self.c.request('/control.php', {'uid':D[0]['uid'],'command':'1; DROP TABLE users','csrf':csrf})[0],400)
        self.assertEqual(len(sql('SELECT id FROM users')),3)

    def test_13_heartbeat_and_invalid_json(self):
        api({'Term':-55,'Rele':1})
        before=sql('SELECT temperature,relay FROM device_state WHERE device_id=?',[D[0]['id']])[0]
        self.assertEqual(api()[0],200)
        self.assertEqual(sql('SELECT temperature,relay FROM device_state WHERE device_id=?',[D[0]['id']])[0],before)
        for body in [b'{',b'[]',b'{"Term":NaN}',b'x'*4097]:
            code,_,_=Client().request('/device_status.php',body,{
                'X-Device-UID':D[0]['uid'],'Authorization':'Bearer '+D[0]['token']})
            self.assertIn(code,[400,413])

    def test_14_html_escaping(self):
        name=D[0]['name']
        try:
            sql('UPDATE devices SET name=? WHERE id=?',['<script>alert(1)</script>',D[0]['id']])
            _,body,_=self.c.request('/index.php')
            self.assertIn('&lt;script&gt;alert(1)&lt;/script&gt;',body)
            self.assertNotIn('<script>alert(1)</script>',body)
        finally: sql('UPDATE devices SET name=? WHERE id=?',[name,D[0]['id']])

    def test_10_csrf_and_role(self):
        self.assertEqual(self.c.request('/control.php',{'uid':D[0]['uid'],'command':'1','csrf':'bad'})[0],403)
        self.assertEqual(self.c.request('/control.php')[0],405)
        viewer=Client(); viewer.login('viewer'); csrf=viewer.token()
        self.assertEqual(viewer.request('/control.php',{'uid':D[0]['uid'],'command':'1','csrf':csrf})[0],403)
        self.assertNotIn('name="command"',viewer.request('/index.php')[1])

    def test_11_constraints_and_limited_db_account(self):
        for statement,params in [
            ('UPDATE device_state SET relay=3 WHERE device_id=?',[D[0]['id']]),
            ('INSERT INTO device_state (device_id) VALUES (?)',[999999]),
            ("INSERT INTO device_access (user_id,device_id,role) VALUES (?,?,'admin')",[C['users'][1]['id'],D[1]['id']]),
        ]:
            with self.assertRaises(subprocess.CalledProcessError): sql(statement,params)
        saved=os.environ['LAB8_TEST_CONFIG']
        try:
            os.environ['LAB8_TEST_CONFIG']=str(ROOT/'config/local.php')
            with self.assertRaises(subprocess.CalledProcessError): sql('DELETE FROM user_events')
            with self.assertRaises(subprocess.CalledProcessError): sql('UPDATE devices SET blocked_until=NULL')
        finally: os.environ['LAB8_TEST_CONFIG']=saved

    def test_12_serialization(self):
        # Independent database sessions call the real trigger concurrently.
        def insert(_):
            sql("INSERT INTO user_events(user_id,device_id,action) VALUES (?,?,'view')",[C['users'][0]['id'],D[0]['id']])
        with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
            list(pool.map(insert,range(15)))
        rows=sql('SELECT outcome,COUNT(*) AS n FROM user_events GROUP BY outcome')
        self.assertEqual({r['outcome']:r['n'] for r in rows},{'allowed':10,'user_blocked':5})

if __name__=='__main__':
    suite=unittest.defaultTestLoader.loadTestsFromTestCase(Integration)
    result=unittest.TextTestRunner(verbosity=2).run(suite)
    if result.wasSuccessful(): reset()
    sys.exit(not result.wasSuccessful())
