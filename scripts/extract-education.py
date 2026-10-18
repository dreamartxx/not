"""Run from repository root: python3 scripts/extract-education.py SOURCE.pdf (requires pdfplumber)."""
import pdfplumber,json,re,unicodedata,hashlib,sys
from pathlib import Path
src=Path(sys.argv[1])
book=pdfplumber.open(src)
assert len(book.pages)==307, 'Expected the May 2025, 307-page edition'
Path('assets/data/education').mkdir(parents=True,exist_ok=True)
out=[]
for n,p in enumerate(book.pages,1):
 lines=p.extract_text_lines(layout=False,return_chars=False)
 parts=[];buf='';last=None
 for l in lines:
  t=unicodedata.normalize('NFKC',l['text']).strip()
  if l['top']>p.height*.94 and re.fullmatch(r'[IVXLC\d]+',t):continue
  gap=l['top']-(last['bottom'] if last else 0)
  if buf and gap>7:
   parts.append(buf);buf=''
  if buf.endswith('-') and t and t[0].islower():buf=buf[:-1]+t
  else:buf+=((' ' if buf else '')+t)
  last=l
 if buf:parts.append(buf)
 out.append({'number':n,'label':str(n-9) if n>=10 else ['Kapak','Künye','Yazarlar','İçindekiler I','İçindekiler II','İçindekiler III','İçindekiler IV','İçindekiler V','İçindekiler VI'][n-1],'paragraphs':parts,'visual':len(' '.join(parts))<120 or 251<=n<=278 or 284<=n<=297})
for start in range(0,307,40):
 Path(f'assets/data/education/pages-{start//40+1}.json').write_text(json.dumps(out[start:start+40],ensure_ascii=False,separators=(',',':')))

print('Extracted',len(out),'pages; paragraphs',sum(len(x['paragraphs']) for x in out),'sha256',hashlib.sha256(src.read_bytes()).hexdigest())
print('Page 12',out[11])
