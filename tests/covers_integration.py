from studio_integration import upload

def run_covers(a, Client):
    Client(a.base).call('covers', code=401)
    image=upload(a)['media']['id']
    photo={'media_id':image,'position':18}
    a.call('cover-save',{'scope':'dnd',**photo},403,csrf='wrong')
    for data in [
        {'scope':'unknown',**photo}, {'scope':'dnd','media_id':'../photo','position':0},
        {'scope':'dnd','media_id':'a'*32,'position':50}, {'scope':'dnd',**photo,'position':101},
        {'scope':'dnd',**photo,'position':'20'}, {'scope':'dnd',**photo,'position':True},
    ]: a.call('cover-save',data,400)
    a.call('cover-save',{'scope':'page:'+'f'*32,**photo},404)
    p=a.call('create',{'title':'Kapaklı sayfa'},201)['page']
    for scope in ['dnd','home','favorites','trash','football','page:'+p['id']]:
        a.call('cover-save',{'scope':scope,**photo})
        assert a.call('covers')['covers'][scope]==photo
    assert a.call('football')['cover']==photo
    assert next(m for m in a.call('library')['media'] if m['id']==image)['used']
    a.call('remove-media',{'id':image},409)
    # Content saves cannot reset an independent cover; photo is included in page backup.
    a.call('save',p)
    backed=next(x for x in a.call('pages')['pages'] if x['id']==p['id'])
    assert backed['cover_photo']==photo
    a.call('validate-import',{'pages':[backed],'covers':{'dnd':photo}})
    a.call('import',{'pages':[backed],'covers':{'dnd':{'media_id':image,'position':80}}},201)
    copy=next(x for x in a.call('pages')['pages'] if x['title']==p['title'] and x['id']!=p['id'])
    assert copy['cover_photo']==photo
    assert a.call('covers')['covers']['dnd']==photo # Import does not overwrite an existing area cover.
    a.call('import',{'pages':[backed],'covers':{'unknown':photo}},400)
    a.call('trash',{'id':p['id']})
    a.call('cover-save',{'scope':'page:'+p['id'],**photo},409)
    a.call('remove-media',{'id':image},409)
    a.call('restore',{'id':p['id']})
    assert a.call('covers')['covers']['page:'+p['id']]==photo
    for scope in ['dnd','home','favorites','trash','football']:
        a.call('cover-save',{'scope':scope,'media_id':'','position':50})
    for item in [p,copy]:
        a.call('trash',{'id':item['id']});a.call('purge',{'id':item['id']})
        assert 'page:'+item['id'] not in a.call('covers')['covers']
    a.call('remove-media',{'id':image})
    print('PASS: private per-area/page photo covers, validation, CSRF, backup/import, trash/restore and media protection.')
