from copy import deepcopy

def run_page_tree(a,Client):
    def pages(): return a.call('pages')['pages']
    def current(id): return next(p for p in pages() if p['id']==id)
    def request(id,target=None,placement='root'):
        ps=pages()
        return {'id':id,'target_id':target,'placement':placement,'version':next(p['version'] for p in ps if p['id']==id),'tree':[{'id':p['id'],'parent_id':p['parent_id']} for p in ps if not p['deleted_at']]}
    def siblings(parent): return [p['id'] for p in pages() if p['parent_id']==parent and not p['deleted_at']]
    def create(title,parent=None): return a.call('create',{'title':title,'parent_id':parent,'blocks':[{'id':'body','type':'text','text':'Keep '+title}]},201)['page']['id']
    first=create('Tree A'); second=create('Tree B'); one=create('Tree A1',first); two=create('Tree A2',first); grand=create('Tree grandchild',one)
    before=current(two);payload=request(two,one,'before')
    Client(a.base).call('page-move',payload,403,csrf='bad')
    a.call('page-move',payload,403,csrf='bad')
    a.call('page-move',payload)
    assert siblings(first)==[two,one]
    a.call('page-move',payload,409) # stale structural snapshot
    assert current(two)['blocks']==before['blocks']
    a.call('save',before,409) # old tab cannot overwrite the new parent/order version
    a.call('page-move',request(two,one,'after'));assert siblings(first)==[one,two]
    a.call('page-move',request(one,second,'inside'))
    assert current(one)['parent_id']==second and current(grand)['parent_id']==one
    for target,where in [(grand,'inside'),(grand,'before'),(one,'inside')]:
        before_state=pages();a.call('page-move',request(one,target,where),400);assert pages()==before_state
    a.call('page-move',request(one));assert current(one)['parent_id'] is None
    assert siblings(None)[-1]==one
    a.call('page-move',request(second,first,'before'));roots=siblings(None);assert roots.index(second)<roots.index(first)
    # Another edit cannot be silently overwritten by a reorder with stale note data.
    stale=request(two,second,'inside');edited=current(two);edited['title']='Edited elsewhere';a.call('save',edited)
    a.call('page-move',stale,409);assert current(two)['title']=='Edited elsewhere'
    a.call('page-move',{**request(two),'target_id':'missing','placement':'inside'},404)
    a.call('page-move',{**request(two),'placement':'unexpected'},400)
    a.call('trash',{'id':second})
    a.call('page-move',request(two,second,'inside'),404)
    a.call('restore',{'id':second})
    # Full exports preserve sibling order on re-import, including nested relationships.
    export=[p for p in pages() if p['id'] in [first,second,one,two,grand]]
    old=set(p['id'] for p in pages());a.call('import',{'pages':export},201)
    copies=[p for p in pages() if p['id'] not in old]
    assert [p['title'] for p in copies]==[p['title'] for p in export]
    copy_by_title={p['title']:p for p in copies}
    assert copy_by_title['Tree grandchild']['parent_id']==copy_by_title['Tree A1']['id']
    for id in [first,second,one]+[p['id'] for p in copies if p['parent_id'] is None]:
        a.call('trash',{'id':id});a.call('purge',{'id':id})
    print('PASS: persistent sidebar ordering/reparenting, descendants, root moves, cycle/CSRF/stale-tree/stale-note rejection, and backup order.')
