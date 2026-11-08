import assert from 'node:assert/strict';
import {legalDocuments,legalTopics} from '../assets/isg-data.js';
import {searchTopics} from '../assets/knowledge-model.js';
assert.equal(legalDocuments.length,38);
const expected=[52,20,25,16,30,17,32,33];
for(const [i,d] of legalDocuments.entries()){
 const topics=legalTopics.filter(t=>t.category===d.id),body=topics.map(t=>t.body).join('\n');
 assert.equal([...body.matchAll(/^(?:GEÇİCİ |EK )?MADDE\s+[\d/A-Z]+/gm)].length,i<8?expected[i]:d.articleStarts,d.title+' legislative article coverage');
 assert.ok(d.snapshot&&(d.source.startsWith('https://mevzuat.wiki/')||d.id==='reg15'));
 assert.ok(!body.includes('Türk mevzuatının tam metni, içtihat arşivi'));
 assert.ok(!body.includes('【')&&!body.includes('\u00ad'));
}
assert.ok(searchTopics(legalTopics,'çalışmaktan kaçınma','law6331').length);
assert.ok(searchTopics(legalTopics,'CALISMAKTAN KACINMA','law6331').length);
assert.ok(searchTopics(legalTopics,'biyolojik sınır').some(t=>t.category==='reg5'));
assert.equal(searchTopics(legalTopics,'qzyvwxnotfound').length,0);

assert.ok(legalTopics.filter(t=>t.kind==='annex').length>=30);
assert.ok(legalTopics.filter(t=>t.kind==='external').length>=10);
assert.ok(searchTopics(legalTopics,'biyolojik etken','reg13').length);
assert.ok(searchTopics(legalTopics,'EL KOL TİTREŞİMİ','reg12').some(t=>t.kind==='annex'));
assert.ok(legalTopics.some(t=>t.category==='reg15'&&t.kind==='external'));
for(const t of legalTopics){if(t.kind==='external')assert.ok(t.externalURL.startsWith('https://'));if(t.kind==='annex')assert.ok(!t.body.includes('için tıklayınız'));}

console.log(`PASS: ${legalDocuments.length} legislative snapshots, Turkish search and searchable annexes.`);
