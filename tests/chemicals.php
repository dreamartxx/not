<?php
require __DIR__.'/../lib/chemicals.php';
function check($v,$message){if(!$v)throw new RuntimeException($message);}
foreach(['64-17-5','67-64-1','1310-73-2','71-43-2'] as $v)check(chemicalCAS($v),'valid CAS');
foreach(['64-17-6','123','064-17-5',"64-17-5\n"] as $v)check(!chemicalCAS($v),'invalid CAS');
foreach([null,[],"ab\0c",'64-17-6',str_repeat('a',161)] as $v){try{chemicalQuery($v);throw new RuntimeException('validation missed');}catch(InvalidArgumentException){}}
check(chemicalURL('javascript:alert(1)')==='','unsafe source');
foreach(['karbonmoksit','karbonmonoksit','karbon monoksit','KARBON MONOKSİT','  karbon  monoksit  ','karbon-monoksit'] as $q)check(chemicalResolve($q)['term']==='carbon monoxide','carbon monoxide spelling: '.$q);
foreach(['karbondioksit','KARBON DİOKSİT','carbon dioxide'] as $q)check(chemicalResolve($q)['term']==='carbon dioxide','CO2 stays distinct');
foreach(['SULFURIK ASIT','SÜLFÜRİK ASİT'] as $q)check(chemicalResolve($q)['term']==='sulfuric acid','Turkish keyboard variants');
check(chemicalResolve('sodyum klorit')['term']==='sodium chlorite','chlorite is not chloride');
check(chemicalResolve('sodyum klorür')['term']==='sodium chloride','chloride is not chlorite');
foreach(['630-08-0','CO','Co','CO2','CuSO4','1,2-dichloroethane','unlisted chemical'] as $q)check(chemicalResolve($q)['term']===$q,'preserve unknown names and identifiers');
check(chemicalResolve('hidrojen peroksitt')['term']==='hidrojen peroksitt','do not silently guess unknown typos');
check(in_array('Hidrojen peroksit',array_column(chemicalSuggestions('hidrojen peroksitt'),'label'),true),'offer nearby spelling');
check(chemicalSuggestions('630-08-0')===[]&&chemicalSuggestions('CO')===[]&&chemicalSuggestions('unlisted chemical')===[],'no speculative identifier matches');
check(chemicalTurkishName('Carbon Monoxide')==='Karbon monoksit','Turkish result name');
check(chemicalTurkishName('Carbon Dioxide')==='Karbon dioksit','distinct Turkish result name');
$long=str_repeat('Temperature 24 °C. This is a source paragraph. ',40)."\n\n".str_repeat('Birim ve değer: 3 µg/m³. ',30);
$parts=chemicalTranslationParts($long);check(implode('',$parts)===$long,'lossless Unicode translation chunks');
foreach($parts as $part)check(strlen($part)<=450&&mb_check_encoding($part,'UTF-8'),'provider byte bound and valid UTF-8');
$translation=['responseStatus'=>200,'matches'=>[
 ['segment'=>'Carbon monoxide is a gas.','translation'=>'Karbon monoksit bir gazdır.','target'=>'tr-TR'],
 ['segment'=>'Carbon monoxide is odorless. ','translation'=>'Karbon monoksit kokusuzdur.','target'=>'tr-TR']
]];
check(chemicalTranslationResult($translation,'Carbon monoxide is odorless. ')==='Karbon monoksit kokusuzdur. ','exact segment only; preserve spacing');
foreach([
 [['responseStatus'=>200,'matches'=>[$translation['matches'][0]]],'Carbon monoxide is NOT a gas.'],
 [['responseStatus'=>200,'matches'=>[['segment'=>'H220 at 24 °C','translation'=>'H220: 25 °C','target'=>'tr-TR']]],'H220 at 24 °C'],
 [['responseStatus'=>200,'matches'=>[['segment'=>'24 °C','translation'=>'24 °F','target'=>'tr-TR']]],'24 °C'],
 [['responseStatus'=>200,'quotaFinished'=>true],'Gas']
] as [$data,$source]){try{chemicalTranslationResult($data,$source);throw new LogicException('unsafe translation accepted');}catch(RuntimeException){}}
check(chemicalTranslationSource(['sections'=>[1=>[['heading'=>'CAS','text'=>'630-08-0']]]],1,0)==='','identifiers never translated');

$info=fn($text,$ref=1)=>['ReferenceNumber'=>$ref,'Value'=>['StringWithMarkup'=>[['String'=>$text]]]];
$record=['RecordType'=>'CID','RecordNumber'=>702,'RecordTitle'=>'Fixture','Reference'=>[['ReferenceNumber'=>1,'SourceName'=>'Source A','URL'=>'https://example.org/','LicenseNote'=>'Test license']],'Section'=>[
 ['TOCHeading'=>'Names and Identifiers','Section'=>[['TOCHeading'=>'Other Identifiers','Section'=>[['TOCHeading'=>'CAS','Information'=>[$info('64-17-5')]]]]]],
 ['TOCHeading'=>'Safety and Hazards','Section'=>[
  ['TOCHeading'=>'Accidental Release Measures','Section'=>[['TOCHeading'=>'Disposal Methods','Information'=>[$info('Dispose per source')]]]],
  ['TOCHeading'=>'First Aid Measures','Information'=>[$info('Full first aid text'),$info('Full first aid text'),$info('Alternate first aid text',2)]],
  ['TOCHeading'=>'Hazards Identification','Section'=>[['TOCHeading'=>'GHS Classification','Information'=>[['ReferenceNumber'=>1,'Name'=>'Pictogram(s)','Value'=>['StringWithMarkup'=>[['String'=>' ','Markup'=>[['Type'=>'Icon','URL'=>'https://pubchem.ncbi.nlm.nih.gov/images/ghs/GHS02.svg','Extra'=>'Flammable'],['Type'=>'Icon','URL'=>'javascript:alert(1)']]]]]]]]]]
 ]],
 ['TOCHeading'=>'Chemical and Physical Properties','Section'=>[['TOCHeading'=>'Experimental Properties','Section'=>[['TOCHeading'=>'Boiling Point','Information'=>[['ReferenceNumber'=>1,'Value'=>['Number'=>[78],'Unit'=>'°C']]]]]]]],
 ['TOCHeading'=>'Toxicity','Section'=>[['TOCHeading'=>'Ecological Information','Information'=>[$info('Ecological evidence')]]]],
 ['TOCHeading'=>'Literature','Information'=>[$info('Unrelated data')]]
]];
$r=chemicalNormalize($record,702);
check($r['cas']===['64-17-5'],'CAS identity');check(count($r['sections'])===16,'all sections');
check(count($r['sections'][4])===2,'deduplicate without dropping conflicting sources');
check(count($r['sections'][13])===1&&count($r['sections'][6])===0,'disposal routing');
check($r['sections'][9][0]['text']==='78 °C','preserve unit');
check(count($r['sections'][2][0]['icons'])===1,'safe GHS icons');
check($r['sections'][4][0]['source']['license']==='Test license','source/license preserved');
check(count($r['sections'][12])===1,'ecology routing');
try{chemicalNormalize($record,999);throw new LogicException('wrong CID accepted');}catch(RuntimeException $e){check($e->getCode()===502,'CID verification');}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$r=chemicalCached($db,'test',fn()=>['value'=>'verified']);check($r['cached']===false,'fresh');
$r=chemicalCached($db,'test',fn()=>throw new RuntimeException('no network expected'));check($r['cached']===true&&$r['stale']===false,'fresh cache');
$db->exec('UPDATE chemical_cache SET updated_at=1');$db->exec('UPDATE chemical_guard SET attempted_at=0');
$r=chemicalCached($db,'test',fn()=>throw new RuntimeException('upstream unavailable',503));check($r['stale']===true&&$r['value']==='verified','stale explicitly marked');
$db->exec('UPDATE chemical_guard SET attempted_at=0');
try{chemicalCached($db,'test',fn()=>throw new RuntimeException('removed',404));throw new LogicException('removed record served stale');}catch(RuntimeException $e){check($e->getCode()===404,'404 not hidden');}
echo "PASS: chemical normalization, provenance, safe URLs, identity, missing data and cache failure handling.\n";
