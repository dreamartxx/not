import assert from 'node:assert/strict';
import {legalDocuments,legalTopics} from '../assets/isg-data.js';
import {medicalTopics,medicalCategories} from '../assets/firstaid-data.js';
import {searchTopics} from '../assets/knowledge-model.js';
import {teachingVisual} from '../assets/knowledge-visuals.js';
assert.equal(legalDocuments.length,8);
const expected=[52,20,25,16,30,17,32,33];
for(const [i,d] of legalDocuments.entries()){
 const topics=legalTopics.filter(t=>t.category===d.id),body=topics.map(t=>t.body).join('\n');
 assert.equal([...body.matchAll(/^(?:GEÇİCİ |EK )?MADDE\s+[\d/A-Z]+/gm)].length,expected[i],d.title+' legislative article coverage');
 assert.ok(d.snapshot&&d.source.startsWith('https://mevzuat.wiki/'));
 assert.ok(!body.includes('Türk mevzuatının tam metni, içtihat arşivi'));
 assert.ok(!body.includes('【')&&!body.includes('\u00ad'));
}
assert.ok(searchTopics(legalTopics,'çalışmaktan kaçınma','law6331').length);
assert.ok(searchTopics(legalTopics,'CALISMAKTAN KACINMA','law6331').length);
assert.ok(searchTopics(legalTopics,'biyolojik sınır').some(t=>t.category==='reg5'));
assert.equal(searchTopics(legalTopics,'qzyvwxnotfound').length,0);
assert.equal(new Set([...legalTopics,...medicalTopics].map(t=>t.id)).size,legalTopics.length+medicalTopics.length);
assert.equal(medicalTopics.filter(t=>t.image).length,12);
for(const t of medicalTopics){assert.ok(t.title&&t.intro&&t.pitfall&&t.steps.length>=2&&t.source.startsWith('https://'));assert.ok(medicalCategories.some(c=>c[0]===t.category));if(t.category==='als')assert.ok(t.professional&&t.source.includes('/2025-Algorithms/'));if(t.image)assert.ok(t.license==='CC BY-NC-SA 4.0'&&t.image.startsWith('https://litfl.com/'));if(t.visual)assert.match(teachingVisual(t.visual),/role="img" aria-label=/);}
const brady=medicalTopics.find(t=>t.id==='brady');assert.match(brady.steps.flat().join(' '),/1 mg IV/);assert.match(brady.steps.flat().join(' '),/3 mg/);
assert.ok(searchTopics(medicalTopics,'ATRİYAL FİBRİLASYON').some(t=>t.id==='af'));
console.log(`PASS: ${legalDocuments.length} legislative snapshots, all 225 article starts, Turkish search, ${medicalTopics.length} medical topics, 12 attributed ECGs and professional algorithm separation.`);
