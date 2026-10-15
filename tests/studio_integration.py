"""Feature assertions invoked inside the isolated HTTP integration suite."""
import base64,json,urllib.request,urllib.error
PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGFAAAAAASUVORK5CYII=')
def upload(a,content=PNG,name='photo.png',mime='image/png',code=201,csrf=None):
    boundary='NotTestBoundary'
    body=('--'+boundary+'\r\nContent-Disposition: form-data; name="file"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+content+('\r\n--'+boundary+'--\r\n').encode()
    req=urllib.request.Request(a.base+'/api.php?action=upload',data=body,headers={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':a.csrf if csrf is None else csrf})
    try:
        with a.opener.open(req) as r: status,result=r.status,json.load(r)
    except urllib.error.HTTPError as e:status,result=e.code,json.load(e)
    assert status==code,(status,result,code)
    return result
def run_studio(a,Client):
    anonymous=Client(a.base);anonymous.status()
    anonymous.call('library',code=401);upload(anonymous,code=401)
    upload(a,code=403,csrf='bad');upload(a,b'<svg onload="alert(1)"></svg>','attack.svg','image/svg+xml',400)
    m=upload(a)['media'];assert m['mime']=='image/png'
    with a.opener.open(a.base+'/api.php?action=media&id='+m['id']) as r:
        assert r.read()==PNG and r.headers.get('Content-Type')=='image/png'
        assert 'no-store' in r.headers.get('Cache-Control')
    anonymous.call('media&id='+m['id'],code=401)
    blocks=[
      {'id':'table','type':'table','text':'','rows':[['Konu','Durum'],['Çizim','Hazır']],'header':True,'width':'half'},
      {'id':'image','type':'image','text':'Görsel','media':m['id'],'caption':'Özel fotoğraf','size':75,'align':'center'},
      {'id':'video','type':'video','text':'Video','url':'https://youtu.be/dQw4w9WgXcQ?t=90'},
      {'id':'toggle','type':'toggle','text':'Ayrıntı','details':'Gizlenebilir metin'},
      {'id':'board','type':'board','text':'','columns':[{'title':'Plan','cards':['İlk iş']},{'title':'Bitti','cards':[]}]},
      {'id':'diagram','type':'diagram','text':'','objects':[
        {'id':'a','kind':'rect','x':100,'y':50,'w':120,'h':60,'text':'Başla','fill':'#ffffff'},
        {'id':'b','kind':'rect','x':400,'y':50,'w':120,'h':60,'text':'Bitir'},
        {'id':'edge','kind':'arrow','from':'a','to':'b','x':0,'y':0,'w':0,'h':0},
        {'id':'stroke','kind':'pen','points':[[1,2],[30,45]],'color':'#123456'}]},
      {'id':'toc','type':'toc','text':''}
    ]
    p=a.call('create',{'title':'Studio test','blocks':blocks},201)['page']
    assert p['blocks'][0]['rows'][1][0]=='Çizim';assert p['blocks'][1]['media']==m['id'];assert p['blocks'][5]['objects'][2]['from']=='a'
    a.call('remove-media',{'id':m['id']},409)
    a.call('validate-import',{'pages':[p]})
    p['blocks'][0]['rows'][1][1]='Güncellendi';p=a.call('save',p)['page']
    assert p['blocks'][0]['rows'][1][1]=='Güncellendi'
    for bad in [
      {'id':'bad','type':'image','url':'javascript:alert(1)'},
      {'id':'bad','type':'image','url':'https://user:password@example.com/'},
      {'id':'bad','type':'image','media':'../secret'},
      {'id':'bad','type':'table','rows':[['a'],['b','c']]},
      {'id':'bad','type':'drawing','objects':[{'id':'a','kind':'script'}]},
      {'id':'bad','type':'drawing','objects':[{'id':'a','kind':'rect','color':'url(https://evil)'}]},
      {'id':'bad','type':'drawing','objects':[{'id':'a','kind':'pen','points':[[float('inf'),0]]}]},
      {'id':'bad','type':'board','columns':[{'title':'a','cards':'not an array'}]}
    ]:a.call('save',{**p,'blocks':[bad]},400)
    count=len(a.call('pages')['pages']);result=a.call('import',{'pages':[p]},201);assert result['count']==1
    copies=a.call('pages')['pages'];assert len(copies)==count+1;copy=next(x for x in copies if x['id']!=p['id'] and x['title']=='Studio test')
    assert copy['blocks'][5]['objects'][3]['points']==[[1,2],[30,45]]
    for item in [p,copy]:a.call('trash',{'id':item['id']});a.call('purge',{'id':item['id']})
    a.call('remove-media',{'id':m['id']});a.call('media&id='+m['id'],code=404)
    print('PASS: private media upload/read, MIME rejection, CSRF, tables/media/canvas/board persistence, invalid widget rejection, backward-compatible import.')
