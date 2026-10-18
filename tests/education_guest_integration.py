import json, urllib.request, urllib.error
from copy import deepcopy

def run_education_guest(owner, Client):
    class Guest(Client):
        def call(self, action, data=None, code=200, csrf=None):
            req=urllib.request.Request(self.base+'/guest-api.php?action='+action,data=json.dumps(data).encode() if data is not None else None,headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf if csrf is None else csrf})
            try:
                with self.opener.open(req) as r: status,body=r.status,json.load(r)
            except urllib.error.HTTPError as e: status,body=e.code,json.load(e)
            assert status==code,(action,status,body,code)
            return body
    before=owner.call('education')
    pages=owner.call('pages')
    visitor=Guest(owner.base);visitor.status()
    visitor.call('education-book',code=401)
    Client(owner.base).call('education-guest-access',code=401)
    owner.call('education-guest-access',{'username':'visitor','password':'Guest123!','enabled':True},403,csrf='bad')
    owner.call('education-guest-access',{'username':'visitor','password':'short','enabled':True},400)
    owner.call('education-guest-access',{'username':'visitor','password':'Guest123!','enabled':True})
    visitor.call('login',{'username':'visitor','password':'wrong'},401)
    visitor.call('login',{'username':'visitor','password':'Guest123!'},403,csrf='bad')
    visitor.csrf=visitor.call('login',{'username':'visitor','password':'Guest123!'})['csrf']
    assert visitor.status()['authenticated']
    for action in ['pages','dnd','football','covers','media','education-guest-access']:
        visitor.call(action,code=403)
    for action in ['save','upload','password','setup','import','education-guest-access']:
        visitor.call(action,{},403)
    # A guest cookie cannot authenticate the owner API, even in the same browser.
    try: visitor.opener.open(owner.base+'/api.php?action=education')
    except urllib.error.HTTPError as e: assert e.code==401
    else: raise AssertionError('Guest reached owner education')
    catalog=visitor.call('education-book');assert len(catalog['pages'])==307
    d=visitor.call('education');assert d['data']['notes']==[] and d['data']['cards']==[]
    assert d['data']['learning']['completed']==[]
    d['data']['read']=[12];d['data']['learning']['book']={'current':'book-12-3','visited':['book-12-3'],'notes':{}}
    saved=visitor.call('education-save',d);assert visitor.call('education')['data']['read']==[12]
    visitor.call('education-save',d,409)
    for kind in ['notes','cards','book','study']:
        bad=deepcopy(d['data'])
        if kind=='notes': bad['notes']=[{'id':'guest-note-123','page':12,'quote':'x','text':'Forbidden'}]
        elif kind=='cards': bad['cards']=[{'id':'guest-card-123','page':12,'front':'x','back':'y','level':0,'due':'2026-10-18'}]
        elif kind=='book': bad['learning']['book']['notes']={'book-12-3':'Forbidden'}
        else: bad['learning']['study']={'current':'','answers':{},'notes':{catalog['course']['tutorials']['units'][0]['id']:'Forbidden'}}
        visitor.call('education-save',{'data':bad,'version':saved['version']},403)
    with visitor.opener.open(owner.base+'/guest-api.php?action=education-figure&page=252') as r:
        assert r.read()[:3]==b'\xff\xd8\xff' and r.headers['Cache-Control']=='no-store'
    other=Guest(owner.base);other.status();other.csrf=other.call('login',{'username':'visitor','password':'Guest123!'})['csrf']
    assert other.call('education')['data']['read']==[]
    assert owner.call('education')==before and owner.call('pages')==pages
    # Rotation and disable revoke existing sessions, including already-open tabs.
    owner.call('education-guest-access',{'username':'visitor','password':'NewGuest123!','enabled':True})
    visitor.call('education-book',code=401);other.call('education-book',code=401)
    visitor.csrf=visitor.call('login',{'username':'visitor','password':'NewGuest123!'})['csrf']
    owner.call('education-guest-access',{'username':'visitor','password':'','enabled':False})
    visitor.call('education-book',code=401);visitor.call('login',{'username':'visitor','password':'NewGuest123!'},401)
    owner.call('education-guest-access',{'username':'visitor','password':'','enabled':True})
    visitor.csrf=visitor.call('login',{'username':'visitor','password':'NewGuest123!'})['csrf']
    visitor.call('logout',{});visitor.call('education',code=401);visitor.status()
    for _ in range(30):
        # Exactly 30 guesses permitted in the guest bucket after successful login reset.
        visitor.call('login',{'username':'visitor','password':'wrong'},401)
    visitor.call('login',{'username':'visitor','password':'wrong'},429)
    assert owner.status()['authenticated']
    assert owner.call('education')==before
    print('PASS: isolated guest cookies/progress, education allowlist, denied private data/note writes, CSRF, images, credential rotation/revocation and independent rate limit.')
