import assert from 'node:assert/strict';
import {sdsSections,validCAS,matchesEntry,safeURL,recordText} from '../assets/chemicals-model.js';
assert.equal(sdsSections.length,16);
for(const cas of ['64-17-5','67-64-1','1310-73-2','71-43-2'])assert.ok(validCAS(cas),cas);
for(const cas of ['64-17-6','123','064-17-5','javascript:1','64-17-5\n'])assert.ok(!validCAS(cas),cas);
assert.equal(safeURL('javascript:alert(1)'),'');assert.equal(safeURL('data:text/html,test'),'');
const entry={heading:'Boiling Point',name:'',path:['Chemical and Physical Properties','Experimental Properties'],text:'78 °C at 760 mm Hg',source:{name:'NIOSH',url:'https://www.cdc.gov/niosh/'},icons:[],citations:[]};
assert.ok(matchesEntry(entry,'KAYNAMA'));assert.ok(matchesEntry(entry,'78 NIOSH'));assert.ok(!matchesEntry(entry,'kaynama absent'));
const text=recordText({cid:702,title:'Ethanol',cas:['64-17-5'],fetched_at:'2026-11-07',source:'https://pubchem.ncbi.nlm.nih.gov/compound/702',sections:{9:[entry]}});
assert.match(text,/Resmî GBF değildir/);assert.match(text,/760 mm Hg/);assert.match(text,/NIOSH/);assert.match(text,/16\. DİĞER BİLGİLER/);assert.match(text,/verisi bulunamadı/);
console.log('PASS: chemical CAS checksum, Turkish multiword search, safe links and 16-section export.');
import {buildChemicalForm,chemicalReportDocument} from '../assets/chemical-form.js';
const make=(heading,text,path=[],source='NIOSH')=>({heading,text,path,name:'',icons:[],citations:['Source revision 2024'],source:{name:source,url:'https://www.cdc.gov/niosh/'}});
const sample={cid:281,title:'Carbon Monoxide',title_tr:'Karbon monoksit',cas:['630-08-0'],formula:'CO',mass:'28.01 g/mol',fetched_at:'2026-11-07',source:'https://pubchem.ncbi.nlm.nih.gov/compound/281',sections:{
 1:[make('CAS','630-08-0'),make('Industry Uses','Industrial source text',['Use and Manufacturing','Uses'])],
 4:[make('First Aid','Source-specific inhalation advice',['Safety and Hazards','First Aid Measures','Inhalation First Aid'])],
 8:[make('NIOSH Recommendations','REL: TWA 35 ppm; ceiling 200 ppm',['Safety and Hazards','Exposure Control and Personal Protection']),make('Protective Equipment and Clothing','Selected PPE source',['Safety and Hazards','Exposure Control and Personal Protection'])],
 9:[make('Boiling Point','-191.5 °C at 760 mm Hg',['Chemical and Physical Properties','Experimental Properties']),make('XLogP3','0.5',['Chemical and Physical Properties','Computed Properties'])],
 10:[make('Hazardous Reactivities and Incompatibilities','Incompatible materials')],
 11:[make('Exposure Routes','Inhalation'),make('Toxicity Data','LC50: species, route, dose and duration')]
}};
const groups=buildChemicalForm(sample),get=id=>groups.flatMap(g=>g.fields).find(f=>f.id===id);
assert.equal(groups.length,8);
assert.equal(get('uses').entries.length,1);assert.equal(get('uses').entries[0].index,1);
assert.equal(get('ppe').entries.length,1,'Exposure Control parent must not turn limits into PPE');
assert.equal(get('limits').entries.length,1);assert.equal(get('limits').entries[0].entry.text,'REL: TWA 35 ppm; ceiling 200 ppm');
assert.equal(get('inhalation').entries.length,1);assert.equal(get('generalAid').entries.length,0);
assert.equal(get('skin').entries.length,0);assert.equal(get('composition').entries.length,0);
assert.equal(get('routes').entries.length,1);assert.equal(get('toxicity').entries.length,1);
assert.equal(get('boiling').entries[0].key,'281:9:0');assert.equal(get('otherProperties').entries[0].key,'281:9:1');
const exportText=chemicalReportDocument(sample,new Map([['281:4:0',{done:true,text:'Türkçe kaynak açıklaması'}]]));
for(const g of groups)assert.ok(exportText.includes(g.title));
assert.match(exportText,/630-08-0/);assert.match(exportText,/Türkçe kaynak açıklaması/);assert.match(exportText,/Source-specific inhalation advice/);
assert.match(exportText,/REL: TWA 35 ppm; ceiling 200 ppm/);assert.match(exportText,/-191.5 °C at 760 mm Hg/);
assert.match(exportText,/Hesaplanan değer/);assert.match(exportText,/türkiye/i);
assert.match(exportText,/alan için veri bulunamadı/);assert.ok(!exportText.includes('%100'));
const unsafe=chemicalReportDocument({...sample,title_tr:'<script>alert(1)</script>',sections:{1:[make('Uses','<img src=x onerror=alert(1)>')]}});
assert.ok(!unsafe.includes('<script>'));assert.match(unsafe,/&lt;img/);
const empty=buildChemicalForm({cid:99,sections:{}});assert.equal(empty.length,8);assert.ok(empty.every(g=>g.count===0));
console.log('PASS: ready form source mapping, PPE/limit separation, missing mixture data, exposure routes, exact unit/context preservation, translation fallback and safe standalone report.');
