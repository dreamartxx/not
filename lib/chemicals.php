<?php
declare(strict_types=1);
require_once __DIR__.'/chemical-translations.php';
require_once __DIR__.'/chemical-turkish.php';

// Fixed upstream hosts only. User input never controls a host or a URL.
function chemicalCAS(string $value): bool {
 if(!preg_match('/^[1-9][0-9]{1,6}-[0-9]{2}-[0-9]$/D',$value))return false;
 $digits=str_replace('-','',$value);$sum=0;$body=strrev(substr($digits,0,-1));
 for($i=0;$i<strlen($body);$i++)$sum+=(int)$body[$i]*($i+1);
 return $sum%10===(int)substr($digits,-1);
}
function chemicalQuery(mixed $value): string {
 if(!is_string($value))throw new InvalidArgumentException('Kimyasal adı veya CAS numarası yaz.');
 $value=trim($value);
 if(mb_strlen($value)<2||mb_strlen($value)>160||preg_match('/[\x00-\x1F\x7F]/',$value))throw new InvalidArgumentException('Arama 2–160 karakter olmalı.');
 if(preg_match('/^[0-9 -]+$/D',$value)&&!chemicalCAS($value))throw new InvalidArgumentException('CAS numarasını kontrol et. Örnek: 64-17-5.');
 return $value;
}
function chemicalNameKey(string $value): string {
 // Normalize only for dictionary lookup; unknown names/formulas go upstream unchanged.
 $value=strtr(mb_strtolower(strtr($value,['İ'=>'i','I'=>'i'])),['ı'=>'i','ş'=>'s','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c',"\u{0307}"=>'']);
 return preg_replace('/[\s\p{Z}-]+/u','',$value);
}
function chemicalNames(): array {
 return [
  ['Karbon monoksit','carbon monoxide',['karbonmoksit','karbon monoksid','karbonmonoxid','carbon monoksit']],
  ['Karbon dioksit','carbon dioxide',['karbondioksid']],
  ['Etanol','ethanol',['etil alkol']],['Metanol','methanol',['metil alkol']],
  ['Aseton','acetone',[]],['Benzen','benzene',[]],['Toluen','toluene',[]],
  ['Sülfürik asit','sulfuric acid',[]],['Hidroklorik asit','hydrochloric acid',[]],
  ['Nitrik asit','nitric acid',[]],['Fosforik asit','phosphoric acid',[]],
  ['Sodyum hidroksit','sodium hydroxide',[]],['Potasyum hidroksit','potassium hydroxide',[]],
  ['Sodyum klorür','sodium chloride',[]],['Sodyum hipoklorit','sodium hypochlorite',[]],
  ['Sodyum klorit','sodium chlorite',[]],['Sodyum karbonat','sodium carbonate',[]],
  ['Sodyum bikarbonat','sodium bicarbonate',[]],['Kalsiyum karbonat','calcium carbonate',[]],
  ['Hidrojen peroksit','hydrogen peroxide',[]],['Amonyak','ammonia',[]],
  ['İzopropil alkol','isopropyl alcohol',['izopropanol']],['Asetik asit','acetic acid',[]],
  ['Su','water',[]],['Oksijen','oxygen',[]],['Hidrojen','hydrogen',[]],['Azot','nitrogen',[]],
  ['Klor','chlorine',[]],['Metan','methane',[]],['Propan','propane',[]],['Bütan','butane',[]],
  ['Hidrojen sülfür','hydrogen sulfide',[]],['Kükürt dioksit','sulfur dioxide',[]],
  ['Azot dioksit','nitrogen dioxide',[]],['Azot monoksit','nitric oxide',[]]
 ];
}
function chemicalResolve(string $query): array {
 if(!chemicalCAS($query))foreach(chemicalNames() as [$label,$term,$aliases]){
  foreach([$label,$term,...$aliases] as $name)if(chemicalNameKey($query)===chemicalNameKey($name))return ['term'=>$term,'label'=>$label];
 }
 return ['term'=>$query,'label'=>''];
}
function chemicalSuggestions(string $query): array {
 $key=chemicalNameKey($query);
 // Never correct identifiers, formulas or unknown substances automatically.
 if(strlen($key)<5||!preg_match('/^[a-z]+$/D',$key))return [];
 $matches=[];$limit=strlen($key)>=9?2:1;
 foreach(chemicalNames() as [$label,$term,$aliases]){
  $distance=min(array_map(fn($name)=>levenshtein($key,chemicalNameKey($name)),[$label,$term,...$aliases]));
  if($distance>0&&$distance<=$limit)$matches[]=['label'=>$label,'term'=>$term,'distance'=>$distance];
 }
 usort($matches,fn($a,$b)=>$a['distance']<=>$b['distance']);
 return array_map(fn($v)=>['label'=>$v['label'],'term'=>$v['term']],array_slice($matches,0,3));
}
function chemicalURL(mixed $value): string {
 if(!is_string($value)||!filter_var($value,FILTER_VALIDATE_URL))return '';
 return in_array(strtolower(parse_url($value,PHP_URL_SCHEME)??''),['http','https'],true)?$value:'';
}
function chemicalSection(array $path): int {
 $leaf=end($path);$root=$path[0]??'';
 if($leaf==='Disposal Methods')return 13;
 if(in_array($leaf,['OSHA Standards','NIOSH Recommendations'],true))return 8;
 if(in_array($leaf,['Flammable Limits','Lower Explosive Limit (LEL)','Upper Explosive Limit (UEL)','Critical Temperature and Pressure'],true))return 9;
 if($leaf==='Stability/Shelf Life'||$leaf==='Toxic Combustion Products')return 10;
 if($root==='Names and Identifiers')return in_array($path[1]??'',['Create Date','Modify Date'],true)?16:1;
 if($root==='Use and Manufacturing')return in_array('Impurities',$path,true)||in_array('Formulations/Preparations',$path,true)?3:(in_array('Uses',$path,true)?1:0);
 if($root==='Chemical and Physical Properties'&&in_array($path[1]??'',['Computed Properties','Experimental Properties'],true))return 9;
 if($root==='Safety and Hazards'){
  return ['Hazards Identification'=>2,'Safety and Hazard Properties'=>2,'First Aid Measures'=>4,'Fire Fighting'=>5,'Accidental Release Measures'=>6,'Handling and Storage'=>7,'Exposure Control and Personal Protection'=>8,'Stability and Reactivity'=>10,'Transport Information'=>14,'Regulatory Information'=>15,'Other Safety Information'=>16][$path[1]??'']??0;
 }
 if($root==='Toxicity')return ($path[1]??'')==='Ecological Information'?12:(($path[1]??'')==='Toxicological Information'?11:0);
 return 0;
}
function chemicalNormalize(array $record,int $cid): array {
 if(($record['RecordType']??'')!=='CID'||(int)($record['RecordNumber']??0)!==$cid)throw new RuntimeException('PubChem kimliği doğrulanamadı.',502);
 $refs=[];foreach($record['Reference']??[] as $r)$refs[$r['ReferenceNumber']]=['name'=>$r['SourceName']??'PubChem','url'=>chemicalURL($r['URL']??''),'license'=>$r['LicenseNote']??'','license_url'=>chemicalURL($r['LicenseURL']??'')];
 $sections=array_fill(1,16,[]);$cas=[];$formula='';$mass='';$modified='';$seen=[];
 $walk=function(array $nodes,array $parents=[])use(&$walk,&$sections,&$cas,&$formula,&$mass,&$modified,&$seen,$refs){
  foreach($nodes as $node){
   $heading=(string)($node['TOCHeading']??'');$path=[...$parents,$heading];$section=chemicalSection($path);
   foreach($node['Information']??[] as $info){
    if(!$section)continue;
    $value=$info['Value']??[];$text=[];$icons=[];
    foreach($value['StringWithMarkup']??[] as $s){if(trim($s['String']??'')!=='')$text[]=$s['String'];foreach($s['Markup']??[] as $m)if(($m['Type']??'')==='Icon'&&preg_match('~^https://pubchem\.ncbi\.nlm\.nih\.gov/images/ghs/GHS0[1-9]\.svg$~D',$m['URL']??''))$icons[]=['url'=>$m['URL'],'label'=>$m['Extra']??'GHS'];}
    if(isset($value['Number']))$text[]=implode(', ',array_map('strval',$value['Number'])).(isset($value['Unit'])?' '.$value['Unit']:'');
    $text=implode("\n\n",$text);if($text===''&&!$icons)continue;
    if($heading==='CAS'&&chemicalCAS(trim($text)))$cas[]=trim($text);
    if($heading==='Molecular Formula'&&$formula==='')$formula=$text;
    if($heading==='Molecular Weight'&&$mass==='')$mass=$text;
    if($heading==='Modify Date'&&$modified==='')$modified=$text;
    $ref=$refs[$info['ReferenceNumber']??0]??['name'=>'PubChem','url'=>'','license'=>'','license_url'=>''];
    $entry=['heading'=>$heading,'name'=>(string)($info['Name']??''),'path'=>$path,'text'=>$text,'icons'=>$icons,'source'=>$ref,'citations'=>array_values(array_filter($info['Reference']??[],'is_string')),'url'=>chemicalURL($info['URL']??'')];
    $key=hash('sha256',json_encode([$section,$heading,$entry['name'],$text,$icons,$ref,$entry['citations']]));
    if(!isset($seen[$key])){$sections[$section][]=$entry;$seen[$key]=true;}
   }
   $walk($node['Section']??[],$path);
  }
 };
 $walk($record['Section']??[]);
 return ['cid'=>$cid,'title'=>$record['RecordTitle']??'Kimyasal','cas'=>array_values(array_unique($cas)),'formula'=>$formula,'mass'=>$mass,'modified'=>$modified,'sections'=>$sections,'source'=>'https://pubchem.ncbi.nlm.nih.gov/compound/'.$cid];
}
function chemicalCached(PDO $db,string $key,callable $loader): array {
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_cache (cache_key VARCHAR(80) PRIMARY KEY,payload MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_guard (id INTEGER PRIMARY KEY,attempted_at BIGINT NOT NULL)');
 $db->exec($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO chemical_guard VALUES(1,0)':'INSERT IGNORE INTO chemical_guard VALUES(1,0)');
 $q=$db->prepare('SELECT * FROM chemical_cache WHERE cache_key=?');$q->execute([$key]);$old=$q->fetch();$now=time();
 if($old&&$now-(int)$old['updated_at']<86400)return [...json_decode($old['payload'],true),'cached'=>true,'stale'=>false];
 $guard=$db->prepare('UPDATE chemical_guard SET attempted_at=? WHERE id=1 AND attempted_at<=?');$guard->execute([$now,$now-1]);
 if(!$guard->rowCount())throw new RuntimeException('Aramalar arasında birkaç saniye bekle.',429);
 try{
  $data=$loader();$data['fetched_at']=gmdate('c');
  $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO chemical_cache VALUES(?,?,?) ON CONFLICT(cache_key) DO UPDATE SET payload=excluded.payload,updated_at=excluded.updated_at':'INSERT INTO chemical_cache VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),updated_at=VALUES(updated_at)';
  $db->prepare($sql)->execute([$key,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$now]);
  $db->prepare('DELETE FROM chemical_cache WHERE updated_at<?')->execute([$now-30*86400]);
  return [...$data,'cached'=>false,'stale'=>false];
 }catch(Throwable $e){if($old&&$e->getCode()!==404)return [...json_decode($old['payload'],true),'cached'=>true,'stale'=>true,'warning'=>'EPA CompTox yenilenemedi. Son alınan kayıt gösteriliyor.'];throw $e;}
}
function chemicalsAPI(PDO $db,string $action,string $method,array $config=[]): never {
 require_once __DIR__.'/comptox.php';
 comptoxAPI($db,$action,$method,$config);
}
