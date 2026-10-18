"""Persistence/security checks using the existing isolated SQLite/MySQL HTTP suite."""
def run_rich_reminders(a):
    block={'id':'formatted','type':'text','text':'Merhaba 🧙 dünya','runs':[{'text':'Merhaba 🧙 ','bold':True,'color':'#8954e8'},{'text':'dünya','italic':True,'highlight':'#fff0a3','size':20,'font':'serif','link':'https://example.com/'}]}
    reminder={'id':'reminder-test','label':'Oturuma hazırlan','at':'2026-10-18T10:15:00Z','done':False,'lead':30,'color':'pink'}
    p=a.call('create',{'title':'Biçim ve zaman','blocks':[block],'reminders':[reminder]},201)['page']
    assert p['blocks'][0]['runs']==block['runs'] and p['reminders']==[reminder]
    p['reminders'][0]['done']=True;p=a.call('save',p)['page'];assert p['reminders'][0]['done']
    stale={**p,'version':p['version']-1,'reminders':[]};a.call('save',stale,409)
    current=next(x for x in a.call('pages')['pages'] if x['id']==p['id']);assert current['reminders'][0]['done']
    for key,value in [('color','url(https://evil)'),('link','javascript:alert(1)'),('link','https://user:secret@example.com'),('font','evil'),('size',999),('bold','yes'),('onclick','alert(1)')]:
        bad={**block,'runs':[{**block['runs'][0],key:value},block['runs'][1]]};a.call('save',{**p,'blocks':[bad]},400)
    a.call('save',{**p,'blocks':[{**block,'text':'Mismatch'}]},400)
    for key,value in [('at','2026-02-30T10:15:00Z'),('at','2026-10-18T25:00:00Z'),('lead',-1),('color','script'),('done','false'),('id','../bad')]:
        a.call('save',{**p,'reminders':[{**reminder,key:value}]},400)
    a.call('save',{**p,'reminders':[reminder,reminder]},400)
    a.call('validate-import',{'pages':[p]});a.call('import',{'pages':[p]},201)
    copy=next(x for x in a.call('pages')['pages'] if x['id']!=p['id'] and x['title']==p['title']);assert copy['reminders']==p['reminders'] and copy['blocks'][0]['runs']==block['runs']
    legacy={k:v for k,v in p.items() if k!='reminders'};p=a.call('save',legacy)['page'];assert p['reminders'][0]['done']
    a.call('trash',{'id':p['id']});a.call('restore',{'id':p['id']});assert next(x for x in a.call('pages')['pages'] if x['id']==p['id'])['reminders']==p['reminders']
    for x in [p,copy]:a.call('trash',{'id':x['id']});a.call('purge',{'id':x['id']})
    print('PASS: rich text/reminder roundtrip, malicious input rejection, atomic version conflicts, backup/import, legacy saves and trash/restore.')
