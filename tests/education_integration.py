from copy import deepcopy
def run_education(a,Client):
    Client(a.base).call('education',code=401);Client(a.base).call('education-book',code=401)
    book=a.call('education-book');assert len(book['pages'])==307 and len(book['chapters'])==5
    assert book['pages'][11]['label']=='3' and book['pages'][306]['label']=='298'
    s=a.call('education');d=s['data'];d.update(page=92,read=[12,13,92],bookmarks=[92])
    d['notes']=[{'id':'test-note-123','page':92,'quote':'Tarafsızlık','text':'Kişisel çalışma notu 🧠 <script>'}]
    d['cards']=[{'id':'test-card-123','page':92,'front':'Etik ilkeler?','back':'Kitapla karşılaştır.','level':1,'due':'2026-10-19'}]
    a.call('education-save',{'data':d,'version':s['version']},403,csrf='bad')
    saved=a.call('education-save',{'data':d,'version':s['version']});assert a.call('education')['data']==d
    a.call('education-save',{'data':d,'version':s['version']},409)
    for key,value in [('page',0),('page',308),('font',40),('read',[12,12]),('bookmarks',[308]),('notes',[d['notes'][0],d['notes'][0]])]:
        invalid={**d,key:value};a.call('education-save',{'data':invalid,'version':saved['version']},400)
    for key,value in [('due','2026-02-30'),('level',7),('page',-1),('front',''),('id','../../file')]:
        invalid=deepcopy(d);invalid['cards'][0][key]=value;a.call('education-save',{'data':invalid,'version':saved['version']},400)
    assert a.call('education')['version']==saved['version']
    current=a.call('education');ld=deepcopy(current['data']);lesson=book['course']['lessons'][0]
    ld['learning']={'selected':lesson['id'],'completed':[],'answers':{lesson['quiz'][0]['id']:lesson['quiz'][0]['correct']}}
    r=a.call('education-save',{'data':ld,'version':current['version']})
    for bad in [{'selected':'unknown','completed':[],'answers':{}},{'selected':lesson['id'],'completed':[lesson['id']],'answers':{}},{'selected':lesson['id'],'completed':[],'answers':{'fake':0}},{'selected':lesson['id'],'completed':[],'answers':{lesson['quiz'][0]['id']:99}}]:
        a.call('education-save',{'data':{**ld,'learning':bad},'version':r['version']},400)
    ld['learning']['answers']={q['id']:q['correct'] for q in lesson['quiz']};ld['learning']['completed']=[lesson['id']]
    r=a.call('education-save',{'data':ld,'version':r['version']});assert a.call('education')['data']['learning']==ld['learning']
    legacy={k:v for k,v in ld.items() if k!='learning'}
    r=a.call('education-save',{'data':legacy,'version':r['version']});assert a.call('education')['data']['learning']==ld['learning']
    saved=r;d=a.call('education')['data']
    # Backup/import uses the same schema and optimistic concurrency, no partial write.
    imported=a.call('education-save',{'data':deepcopy(d),'version':saved['version']});assert a.call('education')['data']==d
    a.call('cover-save',{'scope':'education','media_id':'','position':50});assert 'education' not in a.call('covers')['covers'] or a.call('covers')['covers']['education']['media_id']==''
    print('PASS: private education/catalog, Unicode notes/cards, CSRF, stale versions, source bounds and atomic backup validation.')
