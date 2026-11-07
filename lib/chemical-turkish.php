<?php
declare(strict_types=1);

// Read the published Turkish ILO/WHO card. Never translate or reinterpret its numbers.
function chemicalTurkishCardIds(array $record): array {
 $ids=[];
 foreach($record['sections']??[] as $entries)foreach($entries as $entry)foreach([$entry['source']['url']??'',$entry['url']??''] as $url){
  $host=strtolower((string)parse_url($url,PHP_URL_HOST));
  if($host!=='ilo.org'&&!str_ends_with($host,'.ilo.org'))continue;
  parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
  $id=$query['p_card_id']??'';
  if(is_string($id)&&preg_match('/^\d{1,4}$/D',$id))$ids[]=str_pad($id,4,'0',STR_PAD_LEFT);
 }
 return array_slice(array_values(array_unique($ids)),0,3);
}
function chemicalTurkishCardText(DOMNode $node): string {
 if($node instanceof DOMText)return $node->nodeValue;
 if($node instanceof DOMElement&&in_array(strtolower($node->tagName),['script','style','iframe'],true))return '';
 if($node instanceof DOMElement&&strtolower($node->tagName)==='br')return "\n";
 $s='';foreach($node->childNodes as $child)$s.=chemicalTurkishCardText($child);
 if($node instanceof DOMElement&&in_array(strtolower($node->tagName),['p','div'],true))$s.="\n";
 return $s;
}
function chemicalTurkishCardClean(DOMNode $node): string {
 $s=str_replace("\xc2\xa0",' ',chemicalTurkishCardText($node));
 $s=preg_replace('/[\t\r ]+/u',' ',$s);
 return trim(preg_replace('/ *\n */u',"\n",$s));
}
function chemicalTurkishCardParse(string $html,string $id,array $cas): array {
 $doc=new DOMDocument();$previous=libxml_use_internal_errors(true);
 $doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
 libxml_clear_errors();libxml_use_internal_errors($previous);$xp=new DOMXPath($doc);
 $all=chemicalTurkishCardClean($doc);
 if(!str_contains($all,'İLK YARDIM')||!str_contains($all,'MARUZ KALMA'))throw new RuntimeException('Kaynak Türkçe kart olarak doğrulanamadı.',502);
 if(!preg_match('/CAS\s*#?\s*:\s*(\d{2,7}-\d{2}-\d)/u',$all,$m)||!in_array($m[1],$cas,true))throw new RuntimeException('Türkçe kartın CAS numarası maddeyle eşleşmedi.',502);
 if(!preg_match('/ICSC\s*:\s*'.preg_quote($id,'/').'\b/u',$all))throw new RuntimeException('Türkçe kart numarası doğrulanamadı.',502);
 $url='https://chemicalsafety.ilo.org/dyn/icsc/showcard.display?p_card_id='.$id.'&p_lang=tr&p_version=2';
 $fields=[];$put=function(string $field,string $heading,string $text)use(&$fields,$id,$url){if(trim($text)==='')return;$fields[$field][]=['heading'=>$heading,'name'=>'','text'=>trim($text),'lang'=>'tr','path'=>['Türkçe ICSC kartı'],'icons'=>[],'citations'=>[],'url'=>$url,'source'=>['name'=>'ILO / WHO · Türkçe ICSC '.$id,'url'=>$url,'license'=>'© ILO / WHO. Türkçe sürüm: Başkent Üniversitesi. Kaynak metni ve kaynak bağlamı korunmuştur.','license_url'=>'']];};
 $table=function(string $title)use($xp):?DOMNode{foreach($xp->query('//table') as $t){foreach($xp->query('./tr/th | ./tbody/tr/th',$t) as $th)if(chemicalTurkishCardClean($th)===$title)return $t;}return null;};
 $rowTexts=function(DOMNode $t)use($xp):array{$a=[];foreach($xp->query('./tr/td | ./tbody/tr/td',$t) as $td)$a[]=chemicalTurkishCardClean($td);return $a;};
 $routeNames=['Solunum'=>['inhalation','Soluma'],'Deri'=>['skin','Cilt teması'],'Gözler'=>['eyes','Göz teması'],'Yutma'=>['ingestion','Yutma']];$ppe=[];$symptoms=[];
 foreach($xp->query('//tr') as $row){$ths=$xp->query('./th',$row);if($ths->length!==1)continue;$label=chemicalTurkishCardClean($ths->item(0));if(!isset($routeNames[$label]))continue;$cells=$xp->query('./td',$row);if($cells->length!==3)continue;[$field,$tr]=$routeNames[$label];$put($field,$tr.' · İlk yardım',chemicalTurkishCardClean($cells->item(2)));$pre=chemicalTurkishCardClean($cells->item(1));if($pre!=='')$ppe[]=$tr.': '.$pre;$sym=chemicalTurkishCardClean($cells->item(0));if($sym!=='')$symptoms[]=$tr.': '.$sym;}
 if($t=$table('DÖKÜNTÜ İMHA')){$cells=$rowTexts($t);if(!empty($cells[0]))$ppe[]='Dökülme / acil müdahale koşulları: '.$cells[0];}
 $put('ppe','Koruyucu önlemler ve KKD',implode("\n\n",$ppe));$put('symptoms','Belirtiler / maruziyet yolu',implode("\n\n",$symptoms));
 $boldMap=['Fiziksel Durum; Dış görünüş'=>['appearance','Fiziksel durum / görünüm'],'Fiziksel tehlikeler'=>['incompatible','Fiziksel tehlikeler'],'Kimyasal tehlikeler'=>['incompatible','Kimyasal tehlikeler'],'Maruz kalma yolları'=>['routes','Maruziyet yolları'],'Kısa süreli maruz kalma etkileri'=>['toxicity','Kısa süreli maruziyetin etkileri'],'Solunum riski'=>['toxicity','Solunum riski'],'Uzun süreli veya tekrarlanan maruziyetin etkileri'=>['toxicity','Uzun süreli / tekrarlanan maruziyetin etkileri']];
 foreach($xp->query('//p[b]') as $p){$b=$xp->query('./b',$p)->item(0);$heading=chemicalTurkishCardClean($b);if(!isset($boldMap[$heading]))continue;$text=chemicalTurkishCardClean($p);$text=trim(mb_substr($text,mb_strlen($heading)));[$field,$label]=$boldMap[$heading];$put($field,$label,$text);}
 if($t=$table('FİZİKSEL VE KİMYASAL BİLGİLER')){$cells=$rowTexts($t);foreach(explode("\n",$cells[1]??'') as $line){$line=trim($line);if($line===''||preg_match('/^(Formül|Moleküler kütle):/u',$line))continue;$field='otherProperties';foreach(['boiling'=>'/Kaynama/u','melting'=>'/Erime|Donma/u','flash'=>'/Parlama|tutuşma/u','density'=>'/yoğunlu/u','solubility'=>'/çözünür/u','vapor'=>'/Buhar basıncı/u','ph'=>'/^pH\b/u','explosive'=>'/Patlayıcı limit/u'] as $f=>$pattern)if(preg_match($pattern,$line)){$field=$f;break;}$put($field,'Fiziksel / kimyasal özellik',$line);}}
 if($t=$table('MESLEKİ MARUZ KALMA LİMİTLERİ'))$put('limits','Mesleki maruziyet sınırları',implode("\n",$rowTexts($t)));
 if($t=$table('NOTLAR'))$put('generalAid','Kaynağın özel notları',implode("\n",$rowTexts($t)));
 $title=$xp->query('//table/tr/td/b | //table/tbody/tr/td/b')->item(0);
 if(count($fields)<4)throw new RuntimeException('Türkçe kartın alanları okunamadı.',502);
 return ['id'=>$id,'cas'=>$m[1],'title'=>$title?chemicalTurkishCardClean($title):'','fields'=>$fields,'source'=>$url,'source_date'=>'Türkçe kartta güncelleme tarihi belirtilmemiş'];
}
function chemicalTurkishCardFetch(string $id): string {
 $body='';$ch=curl_init('https://chemicalsafety.ilo.org/dyn/icsc/showcard.display?p_card_id='.$id.'&p_lang=tr&p_version=2');
 curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_ENCODING=>'',CURLOPT_HTTPHEADER=>['Accept: text/html'],CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>750000)return 0;$body.=$chunk;return strlen($chunk);}]);
 $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);unset($ch);
 if(!$ok||$status!==200)throw new RuntimeException('Türkçe ICSC kaynağına şu anda ulaşılamıyor.',502);
 return $body;
}
function chemicalTurkishForm(PDO $db,array $record): array {
 $ids=chemicalTurkishCardIds($record);if(!$ids)return ['available'=>false,'message'=>'Bu PubChem kaydında Türkçe ICSC kartı bağlantısı bulunamadı.'];
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_turkish_cards (card_id VARCHAR(4) PRIMARY KEY,payload MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');
 foreach($ids as $id){$q=$db->prepare('SELECT payload,updated_at FROM chemical_turkish_cards WHERE card_id=?');$q->execute([$id]);$old=$q->fetch();$data=$old?json_decode($old['payload'],true):null;
  if($data&&!in_array($data['cas']??'',$record['cas']??[],true))$data=null;
  if($data&&time()-(int)$old['updated_at']<86400)return [...$data,'available'=>true,'cached'=>true,'stale'=>false];
  try{$new=chemicalTurkishCardParse(chemicalTurkishCardFetch($id),$id,$record['cas']??[]);$new['fetched_at']=gmdate('c');$sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO chemical_turkish_cards VALUES(?,?,?) ON CONFLICT(card_id) DO UPDATE SET payload=excluded.payload,updated_at=excluded.updated_at':'INSERT INTO chemical_turkish_cards VALUES(?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),updated_at=VALUES(updated_at)';$db->prepare($sql)->execute([$id,json_encode($new,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),time()]);return [...$new,'available'=>true,'cached'=>false,'stale'=>false];}
  catch(Throwable){if($data)return [...$data,'available'=>true,'cached'=>true,'stale'=>true,'message'=>'Türkçe kaynak yenilenemedi; son alınan kart gösteriliyor.'];}
 }
 return ['available'=>false,'message'=>'Bu madde için Türkçe ICSC kartı şu anda alınamadı.'];
}
// Reuse complete translations already stored locally; this path makes no translation-service request.
function chemicalStoredTurkish(PDO $db,array $record): array {
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_translations (text_hash VARCHAR(64) PRIMARY KEY,translated TEXT NOT NULL,updated_at BIGINT NOT NULL)');
 $keys=[];$partsByEntry=[];
 foreach($record['sections']??[] as $section=>$entries)foreach($entries as $i=>$e){$parts=chemicalTranslationParts((string)($e['text']??''));$partsByEntry[$section][$i]=$parts;foreach($parts as $part)if(trim($part)!==''&&preg_match('/[a-z]{3}/i',$part))$keys[hash('sha256','en-tr-v1:'.$part)]=true;}
 $found=[];foreach(array_chunk(array_keys($keys),300) as $batch){$q=$db->prepare('SELECT text_hash,translated FROM chemical_translations WHERE text_hash IN ('.implode(',',array_fill(0,count($batch),'?')).')');$q->execute($batch);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$found[$row['text_hash']]=$row['translated'];}
 foreach($partsByEntry as $section=>$entries)foreach($entries as $i=>$parts){$tr='';$complete=true;foreach($parts as $part){if(trim($part)===''||!preg_match('/[a-z]{3}/i',$part)){$tr.=$part;continue;}$value=$found[hash('sha256','en-tr-v1:'.$part)]??null;if($value===null){$complete=false;break;}$tr.=$value;}if($complete&&$tr!==($record['sections'][$section][$i]['text']??'')){$record['sections'][$section][$i]['text_tr']=$tr;$record['sections'][$section][$i]['translation_provider']='MyMemory · kayıtlı çeviri';}}
 return $record;
}
function chemicalLocalTranslationValidate(string $source,string $translated): void {
 if(trim($translated)===''||strlen($translated)>500000||!mb_check_encoding($translated,'UTF-8'))throw new InvalidArgumentException('Geçerli Türkçe çeviri alınamadı.');
 $tokens=function(string $text):array{preg_match_all('/-?\d+(?:[.,]\d+)*/u',$text,$n);preg_match_all('/\b(?:H|P|EUH)\d{3}\b|°\s*[CF]|\b(?:ppm|ppb|mg\/m[3³]|g\/mol|mm\s?Hg|kPa|mPa|LD50|LC50)\b/u',$text,$u);$units=array_map(fn($v)=>preg_replace('/\s/u','',$v),$u[0]);sort($units);return [array_map(fn($v)=>str_replace(',','.',$v),$n[0]),$units];};
 if($tokens($source)!==$tokens($translated))throw new InvalidArgumentException('Çeviride sayı veya birim değişti; kayıt yapılmadı.');
 if(trim($source)===trim($translated)&&preg_match('/[a-z]{3}/i',$source))throw new InvalidArgumentException('Metin Türkçeye çevrilmedi.');
}
function chemicalLocalTable(PDO $db): void {$db->exec('CREATE TABLE IF NOT EXISTS chemical_local_translations (source_hash VARCHAR(64) PRIMARY KEY,translated MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');}
function chemicalLocalTranslationSave(PDO $db,array $input): array {
 foreach(['cid','section','entry'] as $field)if(!isset($input[$field])||!is_int($input[$field]))throw new InvalidArgumentException('Kaynak kimliği geçersiz.');
 if($input['cid']<1||$input['section']<1||$input['section']>16||$input['entry']<0)throw new InvalidArgumentException('Kaynak kimliği geçersiz.');
 $q=$db->prepare('SELECT payload FROM chemical_cache WHERE cache_key=?');$q->execute(['compound-v1:'.$input['cid']]);$raw=$q->fetchColumn();if(!$raw)throw new InvalidArgumentException('Önce kimyasal kaydını aç.');
 $record=json_decode($raw,true);$source=$record['sections'][$input['section']][$input['entry']]['text']??null;
 if(!is_string($source)||!is_string($input['source_hash']??null)||!hash_equals(hash('sha256',$source),$input['source_hash']))throw new InvalidArgumentException('Kaynak değişti. Kaydı yeniden aç.');
 if(!is_string($input['translated']??null))throw new InvalidArgumentException('Türkçe çeviri geçersiz.');chemicalLocalTranslationValidate($source,$input['translated']);chemicalLocalTable($db);
 $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO chemical_local_translations VALUES(?,?,?) ON CONFLICT(source_hash) DO UPDATE SET translated=excluded.translated,updated_at=excluded.updated_at':'INSERT INTO chemical_local_translations VALUES(?,?,?) ON DUPLICATE KEY UPDATE translated=VALUES(translated),updated_at=VALUES(updated_at)';
 $db->prepare($sql)->execute([$input['source_hash'],$input['translated'],time()]);return ['ok'=>true];
}
function chemicalLocalStored(PDO $db,array $record): array {
 chemicalLocalTable($db);$keys=[];foreach($record['sections']??[] as $entries)foreach($entries as $e)$keys[hash('sha256',$e['text']??'')]=true;$found=[];
 foreach(array_chunk(array_keys($keys),300) as $batch){$q=$db->prepare('SELECT source_hash,translated FROM chemical_local_translations WHERE source_hash IN ('.implode(',',array_fill(0,count($batch),'?')).')');$q->execute($batch);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$found[$row['source_hash']]=$row['translated'];}
 foreach($record['sections'] as &$entries)foreach($entries as &$e){$tr=$found[hash('sha256',$e['text']??'')]??null;if($tr!==null){$e['text_tr']=$tr;$e['translation_provider']='Tarayıcı · yerel çeviri';}}unset($e,$entries);return $record;
}
