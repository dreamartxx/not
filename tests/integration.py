#!/usr/bin/env python3
"""HTTP integration tests with an isolated SQLite database; no production data."""
import http.cookiejar, json, os, pathlib, secrets, socket, subprocess, tempfile, time, urllib.request, urllib.error
ROOT = pathlib.Path(__file__).resolve().parents[1]
class Client:
    def __init__(self, base):
        self.base=base; self.csrf=''; self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def call(self, action, data=None, code=200, csrf=None):
        req=urllib.request.Request(self.base+'/api.php?action='+action, data=json.dumps(data).encode() if data is not None else None, headers={'Content-Type':'application/json','X-CSRF-Token': self.csrf if csrf is None else csrf})
        try:
            with self.opener.open(req) as r: status, body=r.status,json.load(r)
        except urllib.error.HTTPError as e: status,body=e.code,json.load(e)
        assert status==code,(action,status,body,code)
        return body
    def status(self):
        result=self.call('status'); self.csrf=result['csrf']; return result

def run():
  with tempfile.TemporaryDirectory(prefix='not-test-') as tmp:
    with socket.socket() as s: s.bind(('127.0.0.1',0));port=s.getsockname()[1]
    key=secrets.token_hex(32); password='Abc123!'
    config=pathlib.Path(tmp)/'config.php'
    if os.environ.get('NOT_TEST_MYSQL'):
      config.write_text("<?php return ['dsn'=>'mysql:host=127.0.0.1;port=3306;dbname=not_test;charset=utf8mb4','db_user'=>'root','db_password'=>'test-only-password','setup_key'=>'"+key+"','secure_cookie'=>false,'football_offline'=>true];")
    else: config.write_text("<?php return ['dsn'=>'sqlite:"+tmp+"/test.sqlite','setup_key'=>'"+key+"','secure_cookie'=>false,'football_offline'=>true];")
    log=open(pathlib.Path(tmp)/'server.log','w')
    env={**os.environ,'NOT_CONFIG':str(config)}
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(ROOT)],env=env,stdout=log,stderr=log)
    try:
      a=Client(f'http://127.0.0.1:{port}')
      for _ in range(50):
        try: a.status();break
        except urllib.error.URLError: time.sleep(.1)
      assert a.status()['setup']
      a.call('pages',code=401)
      a.call('football',code=401)
      a.call('setup',{'username':'Test','password':password,'setup_key':key},code=403,csrf='bad')
      a.call('setup',{'username':'Test','password':password,'setup_key':'wrong'},code=403)
      a.call('setup',{'username':'Test','password':'Abc12!','setup_key':key},code=400)
      a.call('setup',{'username':'Test','password':'şşşşşş','setup_key':key},code=400)
      result=a.call('setup',{'username':'Test','password':password,'setup_key':key});a.csrf=result['csrf']
      assert a.status()['authenticated']
      a.call('setup',{'username':'Test','password':password,'setup_key':key},code=403)
      b=Client(a.base);b.status();b.call('login',{'username':'Test','password':'incorrect'},code=401)
      b.csrf=b.call('login',{'username':'Test','password':password})['csrf']
      p=a.call('create',{'title':'Türkçe 🌿','blocks':[{'id':'b1','type':'todo','text':'Çalış','checked':False}]},201)['page']
      child=a.call('create',{'title':'Alt sayfa','parent_id':p['id']},201)['page']
      p['title']='Updated';p['blocks'][0]['checked']=True
      saved=a.call('save',p)['page'];assert saved['version']==2
      b.call('save',p,409)
      saved['parent_id']=child['id'];a.call('save',saved,400);saved['parent_id']=None
      malformed={**saved,'blocks':[{'id':'x','type':'script','text':'test'}]};a.call('save',malformed,400)
      a.call('purge',{'id':p['id']},400)
      a.call('trash',{'id':p['id']});assert all(n['deleted_at'] for n in a.call('pages')['pages'])
      a.call('save',saved,409)
      a.call('restore',{'id':p['id']});assert all(not n['deleted_at'] for n in a.call('pages')['pages'])
      backup=a.call('pages')['pages'];assert a.call('import',{'pages':backup},201)['count']==2
      assert len(a.call('pages')['pages'])==4
      count=len(a.call('pages')['pages']);bad=[{**backup[0],'parent_id':backup[0]['id']}]
      a.call('import',{'pages':bad},400);assert len(a.call('pages')['pages'])==count
      a.call('trash',{'id':p['id']});a.call('purge',{'id':p['id']});assert len(a.call('pages')['pages'])==2
      fixtures=a.call('football');assert len(fixtures['teams'])==8 and len(fixtures['matches'])>100
      match=fixtures['matches'][0]['id']
      a.call('football-video',{'match_id':match,'url':'https://youtu.be/dQw4w9WgXcQ'},403,csrf='wrong')
      a.call('football-video',{'match_id':match,'url':'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ'},400)
      a.call('football-video',{'match_id':'99999999999999999999','url':'https://youtu.be/dQw4w9WgXcQ'},404)
      video=a.call('football-video',{'match_id':match,'url':'https://youtu.be/dQw4w9WgXcQ'})
      assert video['url']=='https://www.youtube.com/watch?v=dQw4w9WgXcQ'
      assert any(v['match_id']==match and v['url']==video['url'] for v in b.call('football')['videos'])
      a.call('football-refresh',{})
      assert any(v['match_id']==match and v['url']==video['url'] for v in a.call('football')['videos'])
      a.call('football-video',{'match_id':match,'url':''})
      assert not any(v['match_id']==match and v['url'] for v in a.call('football')['videos'])
      a.call('football-history&season=1999',code=400)
      a.call('football-standings&league=../../etc',code=400)
      a.call('football-live');a.call('football-history');a.call('football-standings')
      a.call('football-cover',{'media_id':'','position':50},403,csrf='bad')
      a.call('football-cover',{'media_id':'a'*32,'position':50},400)
      a.call('football-cover',{'media_id':'','position':101},400)
      # A video attached to an archive-only match survives the next application load.
      archived={**fixtures['matches'][0],'id':'900000000000001','date':'2025-01-01T18:00:00+00:00','state':'post'}
      archive_file=pathlib.Path(tmp)/'archive.json';archive_file.write_text(json.dumps({'matches':[archived]}))
      seed_code="$c=require $argv[1];$d=new PDO($c['dsn'],$c['db_user']??null,$c['db_password']??null);$s=$d->prepare('INSERT INTO football_extra_cache VALUES(?,?,?,?)');$s->execute(['history-test',file_get_contents($argv[2]),time(),time()]);"
      subprocess.run(['php','-r',seed_code,str(config),str(archive_file)],check=True)
      a.call('football-video',{'match_id':archived['id'],'url':'https://youtu.be/dQw4w9WgXcQ'})
      assert any(m['id']==archived['id'] for m in b.call('football')['matches'])
      a.call('football-video',{'match_id':archived['id'],'url':''})
      from studio_integration import upload
      cover=upload(a)['media']
      a.call('football-cover',{'media_id':cover['id'],'position':25})
      assert b.call('football')['cover']=={'media_id':cover['id'],'position':25}
      assert next(m for m in a.call('library')['media'] if m['id']==cover['id'])['used']
      a.call('remove-media',{'id':cover['id']},409)
      a.call('football-cover',{'media_id':'','position':50})
      a.call('remove-media',{'id':cover['id']})
      print('PASS: football archive/standings validation, CSRF, cover persistence and in-use image protection.')
      from studio_integration import run_studio
      run_studio(a,Client)
      a.call('password',{'current_password':'wrong','new_password':'New123!'},403)
      a.call('password',{'current_password':password,'new_password':'Abc12!'},400)
      a.call('password',{'current_password':password,'new_password':'şşşşşş'},400)
      changed=a.call('password',{'current_password':password,'new_password':'New123!'})
      a.csrf=changed['csrf'];password='New123!'
      b.call('pages',code=401)
      a.call('logout',{});a.call('pages',code=401)
      c=Client(a.base);c.status()
      for _ in range(15): c.call('login',{'username':'Test','password':'bad'},401)
      c.call('login',{'username':'Test','password':password},429)
      print('PASS: authentication, CSRF, single-owner setup, CRUD, Unicode, optimistic concurrency, tree-cycle prevention, block validation, recursive trash/restore/purge, atomic backup import, password change and session revocation, logout, rate limiting')
    finally:
      proc.terminate();proc.wait();log.close()
if __name__=='__main__':run()
