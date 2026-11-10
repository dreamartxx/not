<?php
declare(strict_types=1);
require __DIR__.'/../lib/chemicals.php';
require __DIR__.'/../lib/chemical-google.php';
function verify(bool $yes,string $message): void {if(!$yes)throw new RuntimeException($message);}
function rejects(callable $fn,int $code): void {try{$fn();}catch(Throwable $e){verify($e->getCode()===$code,'Unexpected error '.$e->getCode().': '.$e->getMessage());return;}throw new RuntimeException('Expected rejection');}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
chemicalGoogleTables($db);
$db->exec('CREATE TABLE chemical_cache (cache_key VARCHAR(80) PRIMARY KEY,payload MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');
$sources=['Definition: Exposure to ethanol.\nValue: 35\nUnit: ppm\nExposure route: oral','Definition: A different study.','Definition: Another study.','Definition: Toxic at 20 ppm.'];
$sources=array_map(fn($s)=>str_replace('\\n',"\n",$s),$sources);
$record=['sections'=>[11=>array_map(fn($text)=>['text'=>$text],$sources)]];
$db->prepare('INSERT INTO chemical_cache VALUES(?,?,?)')->execute(['comptox-v1:DTXSID9020584',json_encode($record),time()]);
$input=fn($i)=>['dtxsid'=>'DTXSID9020584','section'=>11,'entry'=>$i,'source_hash'=>hash('sha256',$sources[$i])];
$config=['google_translation_api_key'=>str_repeat('a',39),'google_translation_monthly_limit'=>450000];
verify(!chemicalGoogleStatus($db,[])['configured'],'Missing key');
verify(!chemicalGoogleStatus($db,['google_translation_api_key'=>"bad\r\nkey"])['configured'],'Header injection rejected');
rejects(fn()=>chemicalGoogleTranslate($db,[],$input(0)),503);
rejects(fn()=>chemicalGoogleTranslate($db,$config,[...$input(0),'source_hash'=>str_repeat('0',64)]),0);
rejects(fn()=>chemicalGoogleTranslate($db,$config,[...$input(0),'dtxsid'=>'DTXSID1020160']),0);
verify(chemicalGoogleStatus($db,$config)['used']===0,'Invalid requests not billed');
$plan=chemicalGooglePlan($sources[0]);verify($plan['queries']===['Exposure to ethanol.'],'Only free text sent');
$count=0;
$result=chemicalGoogleTranslate($db,$config,$input(0),function($q,$key)use(&$count,$db,$config,$input){
 $count++;rejects(fn()=>chemicalGoogleTranslate($db,$config,$input(0),fn()=>throw new LogicException('Duplicate request')),409);
 return ['Etanole maruziyet.'];
});
verify(str_contains($result['translated'],'Maruziyet yolu: Oral (ağız yoluyla)'),'Protected route');
verify(str_contains($result['translated'],"Değer: 35\nBirim: ppm"),'Exact quantity');
verify($result['usage']['used']===$plan['characters'],'Actual sent characters counted');
$cached=chemicalGoogleTranslate($db,[],$input(0),fn()=>throw new LogicException('Cache not used'));
verify($cached['cached']&&$count===1,'Persistent cache works without key');
$overlay=chemicalGoogleStored($db,$record);verify($overlay['sections'][11][0]['translation_provider']==='Google Cloud Translation','Correct provider stored');
$limited=[...$config,'google_translation_monthly_limit'=>$plan['characters']];
rejects(fn()=>chemicalGoogleTranslate($db,$limited,$input(1),fn()=>throw new LogicException('Over quota sent')),429);
verify(chemicalGoogleStatus($db,$config)['used']===$plan['characters'],'Over quota request not sent');
$before=chemicalGoogleStatus($db,$config)['used'];
rejects(fn()=>chemicalGoogleTranslate($db,$config,$input(1),fn()=>throw new RuntimeException('Timeout',502)),502);
verify(chemicalGoogleStatus($db,$config)['used']===$before+chemicalGooglePlan($sources[1])['characters'],'Timeout reservation retained');
rejects(fn()=>chemicalGoogleTranslate($db,$config,$input(3),fn()=>['200 ppm düzeyinde toksik.']),0);
$q=$db->prepare('SELECT state FROM chemical_google_translations WHERE source_hash=?');$q->execute([chemicalGoogleHash($sources[3])]);verify($q->fetchColumn()==='failed','Altered number never cached');
$month=chemicalGoogleStatus($db,$config)['month'];$db->prepare('INSERT INTO chemical_translation_usage VALUES(?,?)')->execute(['2000-01',450000]);
verify(chemicalGoogleStatus($db,$config)['month']===$month&&chemicalGoogleStatus($db,$config)['used']<450000,'Monthly accounting isolated');
verify(chemicalGoogleStatus($db,['google_translation_monthly_limit'=>999999])['limit']===450000,'Limit cannot exceed safe default');
$unicode=chemicalGooglePlan('Definition: Cafés and vapours.');verify($unicode['characters']===mb_strlen('Cafés and vapours.'),'Unicode code points');
echo "PASS: Google translation source binding, cache, provider, concurrent duplicate lock, atomic quota, failure reservation, Unicode billing and number/route preservation.\n";

$db->prepare('INSERT INTO chemical_cache VALUES(?,?,?)')->execute(['pubchem-v2:702',json_encode(['sections'=>[4=>[['text'=>'Fresh air and rest.']]]]),time()]);
$pub=['cid'=>702,'section'=>4,'entry'=>0,'source_hash'=>hash('sha256','Fresh air and rest.')];
$tr=chemicalGoogleTranslate($db,$config,$pub,fn()=>['Temiz hava ve dinlenme.']);
verify($tr['translated']==='Temiz hava ve dinlenme.','PubChem translation');
rejects(fn()=>chemicalGoogleTranslate($db,$config,[...$pub,'cid'=>281]),0);
rejects(fn()=>chemicalGoogleTranslate($db,$config,[...$pub,'source_hash'=>hash('sha256','Different text.')]),0);
verify(chemicalGoogleTranslate($db,[],$pub)['cached'],'PubChem saved translation independent of key');
echo "PASS: PubChem Google translation, CID isolation, source hash and persistent cache.\n";
