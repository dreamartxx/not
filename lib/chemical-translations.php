<?php
declare(strict_types=1);

// Only public PubChem text selected from the server cache can reach this translator.
function chemicalTranslationParts(string $text): array {
 $parts=[];
 foreach(preg_split('/(\n\s*\n)/u',$text,-1,PREG_SPLIT_DELIM_CAPTURE) as $paragraph){
  while(strlen($paragraph)>450){
   $chunk=mb_strcut($paragraph,0,450,'UTF-8');
   $break=strrpos($chunk,' ');if($break!==false&&$break>100)$chunk=substr($chunk,0,$break+1);
   $parts[]=$chunk;$paragraph=substr($paragraph,strlen($chunk));
  }
  if($paragraph!=='')$parts[]=$paragraph;
 }
 return $parts?:[''];
}
function chemicalTranslationNumbers(string $text): array {
 preg_match_all('/\d+(?:[.,]\d+)*/u',$text,$m);
 return array_map(fn($n)=>str_replace(',','.',$n),$m[0]);
}
function chemicalTranslationResult(array $data,string $source): string {
 if(!empty($data['quotaFinished'])||(int)($data['responseStatus']??0)===429)throw new RuntimeException('Günlük Türkçe çeviri kotası doldu. Daha önce çevrilen bilgiler kullanılabilir; yeni metinleri daha sonra tekrar dene.',429);
 if((int)($data['responseStatus']??0)!==200)throw new RuntimeException('Türkçe çeviri hizmeti şu anda yanıt vermiyor.',503);
 // Fuzzy translation-memory matches can omit a negation or a hazard. Reject them.
 $translated='';
 foreach($data['matches']??[] as $match){
  if(trim((string)($match['segment']??''))===trim($source)&&str_starts_with((string)($match['target']??''),'tr')&&is_string($match['translation']??null)){
   $translated=html_entity_decode($match['translation'],ENT_QUOTES|ENT_HTML5,'UTF-8');break;
  }
 }
 if(trim($translated)==='')throw new RuntimeException('Bu metin için tam Türkçe çeviri alınamadı. Özgün metni inceleyebilirsin.',502);
 if(chemicalTranslationNumbers($source)!==chemicalTranslationNumbers($translated))throw new RuntimeException('Çeviride sayısal değerler doğrulanamadı. Özgün metni incele.',502);
 preg_match_all('/\b(?:H|P|EUH)\d{3}\b|\b\d{2,7}-\d{2}-\d\b|(?:°[CF]|\b(?:ppm|ppb|mg\/m3|mg\/m³|g\/mol|mmHg)\b)/u',$source,$tokens);
 foreach($tokens[0] as $token)if(!str_contains($translated,$token))throw new RuntimeException('Çeviride kod veya birim doğrulanamadı. Özgün metni incele.',502);
 preg_match('/^\s*/u',$source,$leading);preg_match('/\s*$/u',$source,$trailing);
 return $leading[0].trim($translated).$trailing[0];
}
function chemicalTranslate(PDO $db,string $source): string {
 if(trim($source)===''||!preg_match('/[a-z]{3}/i',$source))return $source;
 if(strlen($source)>500)throw new InvalidArgumentException('Çeviri metni sınırı aşıldı.');
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_translations (text_hash VARCHAR(64) PRIMARY KEY,translated TEXT NOT NULL,updated_at BIGINT NOT NULL)');
 $key=hash('sha256','en-tr-v1:'.$source);$q=$db->prepare('SELECT translated FROM chemical_translations WHERE text_hash=?');$q->execute([$key]);
 if(($old=$q->fetchColumn())!==false)return $old;
 $url='https://api.mymemory.translated.net/get?'.http_build_query(['q'=>$source,'langpair'=>'en|tr','mt'=>1]);
 $body='';$ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'NotPersonalReference/1.0',CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>100000)return 0;$body.=$chunk;return strlen($chunk);}]);
 $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);unset($ch);
 if($status===429)throw new RuntimeException('Çeviri hizmetinin kullanım sınırına ulaşıldı. Daha sonra tekrar dene.',429);
 if(!$ok||$status!==200)throw new RuntimeException('Türkçe çeviri bağlantısı kurulamadı. Daha sonra tekrar dene.',503);
 $data=json_decode($body,true);if(!is_array($data))throw new RuntimeException('Çeviri yanıtı okunamadı.',502);
 $translated=chemicalTranslationResult($data,$source);
 $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO chemical_translations VALUES(?,?,?) ON CONFLICT(text_hash) DO NOTHING':'INSERT IGNORE INTO chemical_translations VALUES(?,?,?)';
 $db->prepare($sql)->execute([$key,$translated,time()]);
 return $translated;
}
function chemicalTurkishName(string $title): string {
 foreach(chemicalNames() as [$label,$term,$aliases])foreach([$label,$term,...$aliases] as $name)if(chemicalNameKey($name)===chemicalNameKey($title))return $label;
 return '';
}
function chemicalTranslationSource(array $record,int $section,int $entry): string {
 $item=$record['sections'][$section][$entry]??null;
 if(!is_array($item))throw new InvalidArgumentException('Çevrilecek kaynak kaydı bulunamadı.');
 // Identifiers and synonym lists are chemical identities, not prose to translate.
 if(in_array($item['heading']??'',['CAS','Molecular Formula','Molecular Weight','IUPAC Name','InChI','InChI Key','SMILES','Canonical SMILES','Isomeric SMILES','Synonyms','Depositor-Supplied Synonyms'],true))return '';
 return (string)($item['text']??'');
}
function chemicalTranslationAPI(PDO $db): never {
 $cid=filter_var($_GET['cid']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>9999999999]]);
 $section=filter_var($_GET['section']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>16]]);
 $entry=filter_var($_GET['entry']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
 $part=filter_var($_GET['part']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
 if(in_array(false,[$cid,$section,$entry,$part],true))throw new InvalidArgumentException('Geçerli bir kaynak bölümü seç.');
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_cache (cache_key VARCHAR(80) PRIMARY KEY,payload MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');
 $q=$db->prepare('SELECT payload FROM chemical_cache WHERE cache_key=?');$q->execute(['compound-v1:'.$cid]);$raw=$q->fetchColumn();
 if($raw===false)throw new InvalidArgumentException('Önce kimyasal kaydını aç.');
 $record=json_decode($raw,true);$source=chemicalTranslationSource($record,$section,$entry);$parts=chemicalTranslationParts($source);
 if(!isset($parts[$part]))throw new InvalidArgumentException('Geçerli bir metin parçası seç.');
 session_write_close();
 respond(['cid'=>$cid,'section'=>$section,'entry'=>$entry,'part'=>$part,'total'=>count($parts),'text'=>chemicalTranslate($db,$parts[$part]),'provider'=>'MyMemory','machine_translation'=>true]);
}
