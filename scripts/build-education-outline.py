"""Build the source-ordered lesson index. Does not modify extracted source text."""
import json,re,hashlib
from pathlib import Path
root=Path('assets/data/education')
pages=sum([json.loads((root/f'pages-{i}.json').read_text()) for i in range(1,9)],[])
chapters=json.loads((root/'catalog.json').read_text())['chapters']
chapter_titles=['Yargılama ve İspat Hukukuna İlişkin Temel Kavramlar','Bilirkişilik','Bilirkişi İncelemesinin Usul ve Esasları','Rapor Yazımı Usul ve Esasları','UYAP Bilirkişi Portal Bilgi Sistemi ve Örnek Uygulamalar']
starts=[]
def add(page,index,title,level,lesson):
 starts.append(dict(id=f'book-{page}-{index}',page=page,index=index,title=title,level=level,lesson=lesson))
for c,title in zip(chapters,chapter_titles):
 add(c['start'],0,title,0,c['id'])
 for p in pages[c['start']-1:c['end']]:
  for i,raw in enumerate(p['paragraphs']):
   if p['number']==c['start'] and i==0:continue
   t=re.sub(r'^Ø\s*','',raw).strip()
   level=None
   if re.match(r'^§\d+\.',t):level=1
   elif re.match(r'^[IVX]+\s*\.(?:-|\s)',t):level=2
   elif re.match(r'^[A-ZÇİÖŞÜ]\.(?:-|\s)',t):level=3
   elif re.match(r'^\d+\.-',t):level=4
   elif re.match(r'^[a-zçöşü]\.-',t):level=5
   if p['number']==262 and t.startswith('I.-'):level=3
   if t=='GİRİŞ':level=1
   if p['number'] in [284,287,290,294,298] and i==0:level=2
   if p['number']==299 and t in ['Analiz','Bilirkişi Raporu','Bilirkişi Raporunu Teslim EDikten Sonra']:level=3
   if level is not None and len(t)<250:
    title=re.sub(r'(?<=[A-Za-zÇçĞğİıÖöŞşÜü])\d+$','',t)
    add(p['number'],i,title,level,c['id'])
# Front matter and bibliography remain part of the ordered source route.
add(10,0,'Sunuş',0,'yargilama');add(300,0,'Kaynakça',0,'uyap')
starts.sort(key=lambda x:(x['page'],x['index']))
flat=[{'page':p['number'],'index':i} for p in pages[9:] for i,_ in enumerate(p['paragraphs'])]
pos={(x['page'],x['index']):i for i,x in enumerate(flat)}
stack=[]
for i,n in enumerate(starts):
 while stack and (stack[-1]['level']>=n['level'] or stack[-1]['lesson']!=n['lesson']):stack.pop()
 n['parent']=stack[-1]['id'] if stack else None;stack.append(n)
 a=pos[(n['page'],n['index'])];b=pos[(starts[i+1]['page'],starts[i+1]['index'])] if i+1<len(starts) else len(flat)
 n['refs']=flat[a:b]
 n['endPage']=starts[i+1]['page'] if i+1<len(starts) else 307
 n['figurePages']=[p for p in range(n['page'],n['endPage']+1) if (251<=p<=278 or 284<=p<=297) and (p<n['endPage'] or i+1==len(starts) or starts[i+1]['index']>0)]
 # Heading-only sections precede their children; they still have a source address.
 n['words']=sum(len(pages[x['page']-1]['paragraphs'][x['index']].split()) for x in n['refs'])
# Full subtree bounds for breadcrumbs, source context and progress.
for i,n in enumerate(starts):
 children=[]
 for later in starts[i+1:]:
  if later['lesson']!=n['lesson'] or later['level']<=n['level']:break
  children.append(later)
 n['lastPage']=max([n['page']]+[r['page'] for x in [n]+children for r in x['refs']])
# The TOC itself is retained verbatim for an independently auditable coverage inventory.
toc=[]
import unicodedata,difflib
def key(s):
 s=re.sub(r'^(?:§?\d+|[IVX]+|[A-ZÇİÖŞÜ])\s*[.\-]+\s*','',s)
 return re.sub(r'[^a-z0-9]','',unicodedata.normalize('NFKD',s.lower().replace('ı','i')).encode('ascii','ignore').decode())
for p in pages[3:9]:
 text=' '.join(p['paragraphs'])
 for match in re.finditer(r'(.+?)\.{3,}\s*(\d+)',text):
  title=match[1].strip();printed=int(match[2]);target=printed+9
  if title.endswith('SUNUŞ'):title='Sunuş'
  elif title.endswith('GİRİŞ'):title='GİRİŞ'
  else:title=re.sub(r'^(?:[IVX]+ )?İçindekiler(?: [IVX]+)?\s*','',title)
  if 'BEŞİNCİ BÖLÜM' in title:title=title[title.index('I.-'):]
  candidates=[n for n in starts if abs(n['page']-target)<=2]
  best=max(candidates,key=lambda n:difflib.SequenceMatcher(None,key(title),key(n['title'])).ratio())
  if title.startswith('II.- Bilirkişinin Hukuki ve Cezai'):best=next(n for n in starts if n['id']=='book-132-2')
  ratio=difflib.SequenceMatcher(None,key(title),key(best['title'])).ratio()
  toc.append({'title':title,'printedPage':printed,'tocPage':p['number'],'node':best['id'],'match':round(ratio,3)})
  if ratio<.65:print('REVIEW TOC',title,'=>',best['title'],ratio)
outline={'edition':'Mayıs 2025','sourceSHA256':'a686e2b3d609d42173db3009c82ac0ee6040bc7240e41b8d1d6ba2880b5d6e04','chapters':[{'id':c['id'],'title':t} for c,t in zip(chapters,chapter_titles)],'nodes':starts,'sourceParagraphs':len(flat),'figurePages':list(range(251,279))+list(range(284,298)),'toc':toc}
(root/'outline.json').write_text(json.dumps(outline,ensure_ascii=False,separators=(',',':'))+'\n')
print(len(starts),'source headings;',len(flat),'paragraphs;',len(toc),'TOC entries;',len(outline['figurePages']),'original visual pages')
