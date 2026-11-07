import assert from 'node:assert/strict';
import {legalDocuments,legalTopics} from '../assets/isg-data.js';
import {medicalTopics,medicalCategories} from '../assets/firstaid-data.js';
import {searchTopics} from '../assets/knowledge-model.js';
import {teachingVisual} from '../assets/knowledge-visuals.js';
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
assert.equal(new Set([...legalTopics,...medicalTopics].map(t=>t.id)).size,legalTopics.length+medicalTopics.length);
assert.equal(medicalTopics.filter(t=>t.image).length,12);
for(const t of medicalTopics){assert.ok(t.title&&t.intro&&t.pitfall&&t.steps.length>=2&&t.source.startsWith('https://'));assert.ok(medicalCategories.some(c=>c[0]===t.category));if(t.category==='als')assert.ok(t.professional&&t.source.includes('/2025-Algorithms/'));if(t.image)assert.ok(t.license==='CC BY-NC-SA 4.0'&&t.image.startsWith('https://litfl.com/'));if(t.visual)assert.match(teachingVisual(t.visual),/role="img" aria-label=/);}
const brady=medicalTopics.find(t=>t.id==='brady');assert.match(brady.steps.flat().join(' '),/1 mg IV/);assert.match(brady.steps.flat().join(' '),/3 mg/);
assert.ok(searchTopics(medicalTopics,'ATRİYAL FİBRİLASYON').some(t=>t.id==='af'));
console.log(`PASS: ${legalDocuments.length} legislative snapshots, original 225 article starts plus source counts for 30 added regulations, Turkish search, ${medicalTopics.length} medical topics, 12 attributed ECGs and professional algorithm separation.`);

assert.equal(medicalTopics.filter(t=>t.category==='aid').length,0);
assert.equal(medicalTopics.length,20);
assert.ok(legalTopics.filter(t=>t.kind==='annex').length>=30);
assert.ok(legalTopics.filter(t=>t.kind==='external').length>=10);
assert.ok(searchTopics(legalTopics,'biyolojik etken','reg13').length);
assert.ok(searchTopics(legalTopics,'EL KOL TİTREŞİMİ','reg12').some(t=>t.kind==='annex'));
assert.ok(legalTopics.some(t=>t.category==='reg15'&&t.kind==='external'));
for(const t of legalTopics){if(t.kind==='external')assert.ok(t.externalURL.startsWith('https://'));if(t.kind==='annex')assert.ok(!t.body.includes('için tıklayınız'));}
import {clinicalAlgorithms,advanceClinical} from '../assets/clinical-algorithms.js';
for(const [id,a] of Object.entries(clinicalAlgorithms)){
 const reached=new Set(),todo=[a.start];while(todo.length){const key=todo.shift();if(reached.has(key))continue;reached.add(key);const n=a.nodes[key];assert.ok(n,id);for(const [,target]of n.choices){assert.ok(a.nodes[target],id+': '+target);todo.push(target);}}
 assert.equal(reached.size,Object.keys(a.nodes).length,id+' unreachable branch');
 const path=[a.start];assert.deepEqual(advanceClinical(id,path,'invalid'),path);
}
const arrest=clinicalAlgorithms.arrest.nodes;
assert.equal(arrest.start.choices[1][1],'pea');assert.match(arrest.pea.title,/Şok yok/);
assert.match(arrest.shock2.text,/1 mg IV\/IO/);assert.match(arrest.shock3.text,/Amiodaron veya lidokain/);
assert.equal(arrest.pea.choices[1][1],'shock2');
const br=clinicalAlgorithms.brady.nodes;assert.match(br.atropine.text,/3 mg/);assert.match(br.pacing.text,/5–20 mikrogram\/kg\/dk/);assert.match(br.pacing.text,/2–10 mikrogram\/dk/);
const ta=clinicalAlgorithms.tachy.nodes;assert.equal(ta.wide.choices[1][1],'irregularWide');assert.match(ta.irregularWide.title,/Adenosin verme/);
const cv=clinicalAlgorithms.cardioversion.nodes;assert.match(cv.af.title,/200 J/);assert.match(cv.flutter.title,/200 J/);assert.match(cv.poly.text,/Senkronize olmayan/);
console.log('PASS: cardiac-only scope, reachable manual clinical paths, shock/PEA separation, dose units, contraindication branch and searchable annexes.');
