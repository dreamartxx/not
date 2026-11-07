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

$original='Limit: 35 ppm at -20 °C.';$native='Sınır: 35 ppm, -20 °C.';
chemicalLocalTranslationValidate($original,$native);
foreach(['Sınır: 350 ppm, -20 °C.','Sınır: 35 ppb, -20 °C.','Sınır: 35 ppm, 20 °C.',$original] as $bad){try{chemicalLocalTranslationValidate($original,$bad);throw new LogicException('invalid local translation accepted');}catch(InvalidArgumentException){}}
$db->prepare('INSERT INTO chemical_cache VALUES(?,?,?)')->execute(['compound-v1:281',json_encode(['sections'=>[8=>[['text'=>$original]]]]),time()]);
$save=['cid'=>281,'section'=>8,'entry'=>0,'source_hash'=>hash('sha256',$original),'translated'=>$native];
check(chemicalLocalTranslationSave($db,$save)['ok'],'save source-bound local translation');
$saved=chemicalLocalStored($db,['sections'=>[8=>[['text'=>$original]]]]);check($saved['sections'][8][0]['text_tr']===$native,'read saved translation on another device');
foreach([array_replace($save,['source_hash'=>str_repeat('0',64)]),array_replace($save,['entry'=>999]),array_replace($save,['translated'=>'Wrong 350 ppm'])] as $bad){try{chemicalLocalTranslationSave($db,$bad);throw new LogicException('invalid save accepted');}catch(InvalidArgumentException){}}
echo "PASS: source-bound on-device translation persistence and server-side unit/sign validation.\n";
require_once __DIR__.'/../lib/comptox.php';
$id='DTXSID1020160';
foreach(['281','DTXSID1','DTXSID1020160/../x',"DTXSID1020160\n",12] as $bad){try{comptoxId($bad);throw new LogicException('bad ID');}catch(InvalidArgumentException){}}
check(comptoxKey([])==='','key absence is explicit');
try{comptoxKey(['comptox_api_key'=>"test\r\nInjected: yes"]);throw new LogicException('header injection');}catch(RuntimeException){}
$rows=comptoxSearchRows([['dtxsid'=>$id,'preferredName'=>'Carbon monoxide','casrn'=>'630-08-0'],['dtxsid'=>$id,'preferredName'=>'Carbon monoxide','casrn'=>'630-08-0']]);
check(count($rows)===1&&$rows[0]['Title_tr']==='Karbon monoksit','deduplicate IDs and resolve Turkish label');
$detail=['dtxsid'=>$id,'preferredName'=>'Carbon monoxide','casrn'=>'630-08-0','molFormula'=>'CO','monoisotopicMass'=>27.9949];
$sets=['experimental'=>[['dtxsid'=>$id,'propName'=>'Boiling Point','propValue'=>-191.5,'propUnit'=>'°C','expDetailsPressureMmhg'=>760,'lsCitation'=>'Fixture 2026','publicSourceUrl'=>'javascript:bad']],'predicted'=>[['dtxsid'=>$id,'propName'=>'Boiling Point','propValue'=>-190,'propUnit'=>'°C','adConclusion'=>'Outside applicability domain','modelName'=>'Fixture model']],'uses'=>[['dtxsid'=>$id,'category'=>'Fixture use','definition'=>'Fixture definition']],'toxval'=>[['dtxsid'=>$id,'toxvalType'=>'Exposure Limit','toxvalNumeric'=>5,'toxvalUnits'=>'mg/L','exposureRoute'=>'oral','speciesCommon'=>'rat','studyDurationValue'=>28,'studyDurationUnits'=>'days','qualifier'=>'<','riskAssessmentClass'=>'water','toxicologicalEffect'=>'Fixture effect','longRef'=>'Fixture study']]];
$r=comptoxNormalize($id,$detail,$sets);
check($r['dtxsid']===$id&&$r['mass']===''&&$r['formula']==='CO','monoisotopic mass never used as molecular weight');
check(count($r['sections'][4])===0&&count($r['sections'][8])===0,'no invented first aid, PPE or occupational limits');
check(str_contains($r['sections'][9][1]['text'],'760')&&$r['sections'][9][1]['url']==='','measurement conditions and safe links');
check(in_array('Computed Properties',$r['sections'][9][2]['path'],true)&&str_contains($r['sections'][9][2]['text'],'Outside applicability'),'prediction provenance and uncertainty');
check(str_contains($r['sections'][11][0]['text'],'rat')&&str_contains($r['sections'][11][0]['text'],'28')&&str_contains($r['sections'][11][0]['text'],'Qualifier: <'),'toxicity study context and qualifiers');
try{comptoxNormalize($id,['dtxsid'=>'DTXSID9020584'],[]);throw new LogicException('wrong detail');}catch(RuntimeException){}
try{comptoxNormalize($id,$detail,['uses'=>[['dtxsid'=>'DTXSID9020584','category'=>'wrong']]]);throw new LogicException('wrong subrecord');}catch(RuntimeException){}
$fetch=function($path)use($detail){if(str_contains($path,'/detail/'))return $detail;throw new RuntimeException('temporary',503);};
try{comptoxLoad($id,'test-key',$fetch);throw new LogicException('partial response accepted');}catch(RuntimeException $e){check($e->getCode()===503,'partial failures not cached as absent data');}
$missing=function($path)use($detail){if(str_contains($path,'/detail/'))return $detail;throw new RuntimeException('absent',404);};
check(count(comptoxLoad($id,'test-key',$missing)['sections'][11])===0,'confirmed absent endpoint stays absent');
try{chemicalLocalTranslationSave($db,array_replace($save,['cid'=>702]));throw new LogicException('source crossover');}catch(InvalidArgumentException){}
echo "PASS: EPA identity boundaries, schema mapping, experimental/predicted distinction, source context, missing API key and partial failure handling.\n";
