<?php
declare(strict_types=1);
// Google Translation Basic v2; only source-bound EPA entries may reach the fixed upstream.
require_once __DIR__.'/chemical-turkish.php';
function chemicalGoogleKey(array $config): string {
 $key=trim((string)(getenv('GOOGLE_TRANSLATION_API_KEY')?:($config['google_translation_api_key']??'')));
 return preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$key)?$key:'';
}
function chemicalGoogleTables(PDO $db): void {
 $db->exec("CREATE TABLE IF NOT EXISTS chemical_google_translations (source_hash VARCHAR(64) PRIMARY KEY,translated MEDIUMTEXT NOT NULL,state VARCHAR(12) NOT NULL,claim VARCHAR(32) NOT NULL,updated_at BIGINT NOT NULL)");
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_translation_usage (month_key VARCHAR(7) PRIMARY KEY,characters BIGINT NOT NULL)');
}
function chemicalGoogleHash(string $source): string {return hash('sha256',"google-nmt-en-tr-v1\n".$source);}
function chemicalGoogleStatus(PDO $db,array $config): array {
 chemicalGoogleTables($db);$month=(new DateTimeImmutable('now',new DateTimeZone('America/Los_Angeles')))->format('Y-m');
 $q=$db->prepare('SELECT characters FROM chemical_translation_usage WHERE month_key=?');$q->execute([$month]);$used=(int)$q->fetchColumn();
 $limit=max(0,min(450000,(int)($config['google_translation_monthly_limit']??450000)));
 return ['provider'=>'Google Cloud Translation','configured'=>chemicalGoogleKey($config)!=='','month'=>$month,'used'=>$used,'limit'=>$limit,'remaining'=>max(0,$limit-$used)];
}
function chemicalGoogleStored(PDO $db,array $record): array {
 chemicalGoogleTables($db);$keys=[];foreach($record['sections']??[] as $entries)foreach($entries as $e)$keys[chemicalGoogleHash($e['text']??'')]=true;$found=[];
 foreach(array_chunk(array_keys($keys),300) as $batch){$q=$db->prepare("SELECT source_hash,translated FROM chemical_google_translations WHERE state='ready' AND source_hash IN (".implode(',',array_fill(0,count($batch),'?')).')');$q->execute($batch);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$found[$r['source_hash']]=$r['translated'];}
 foreach($record['sections'] as &$entries)foreach($entries as &$e){$text=$found[chemicalGoogleHash($e['text']??'')]??null;if($text!==null){$e['text_tr']=$text;$e['translation_provider']='Google Cloud Translation';}}unset($e,$entries);return $record;
}
function chemicalGoogleSource(PDO $db,array $input): string {
 require_once __DIR__.'/comptox.php';$id=comptoxId($input['dtxsid']??null);
 if(!is_int($input['section']??null)||$input['section']<1||$input['section']>16||!is_int($input['entry']??null)||$input['entry']<0)throw new InvalidArgumentException('Kaynak kimliği geçersiz.');
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_cache (cache_key VARCHAR(80) PRIMARY KEY,payload MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');
 $q=$db->prepare('SELECT payload FROM chemical_cache WHERE cache_key=?');$q->execute(['comptox-v1:'.$id]);$record=json_decode($q->fetchColumn()?:'null',true);
 $source=$record['sections'][$input['section']][$input['entry']]['text']??null;
 if(!is_string($source)||!is_string($input['source_hash']??null)||!hash_equals(hash('sha256',$source),$input['source_hash']))throw new InvalidArgumentException('Kaynak değişti veya bulunamadı. Kimyasal kaydını yeniden aç.');
 if($source===''||mb_strlen($source)>25000)throw new InvalidArgumentException('Bu kaynak metni tek çeviri için çok uzun veya boş.');
 return $source;
}
function chemicalGooglePlan(string $source): array {
 // Preserve scientific notation, identifiers and routes; translate each remaining line separately.
 $labels=['Property'=>'Özellik','Value'=>'Değer','Unit'=>'Birim','Original value'=>'Özgün değer','Original unit'=>'Özgün birim','Value text'=>'Değer açıklaması','Dataset'=>'Veri kümesi','Temperature (°C)'=>'Sıcaklık (°C)','Pressure (mmHg)'=>'Basınç (mmHg)','pH'=>'pH','Model'=>'Model','Prediction error'=>'Tahmin hatası','Reported functional use'=>'Bildirilen işlevsel kullanım','Definition'=>'Tanım','Product use category'=>'Ürün kullanım kategorisi','Category kind'=>'Kategori türü','General category'=>'Genel kategori','Product family'=>'Ürün ailesi','Product type'=>'Ürün türü','Product count'=>'Ürün sayısı','Value type'=>'Değer türü','Subtype'=>'Alt tür','Category'=>'Kategori','Qualifier'=>'Karşılaştırma işareti','Assessment class'=>'Değerlendirme sınıfı','Human or ecology'=>'İnsan sağlığı veya ekoloji','Study type'=>'Çalışma türü','Duration class'=>'Süre sınıfı','Duration'=>'Süre','Duration unit'=>'Süre birimi','Species'=>'Tür','Species common name'=>'Türün yaygın adı','Species Latin name'=>'Türün Latince adı','Species category'=>'Tür kategorisi','Strain'=>'Suş','Sex'=>'Cinsiyet','Generation'=>'Nesil','Life stage'=>'Yaşam evresi','Exposure route'=>'Maruziyet yolu','Exposure method'=>'Maruziyet yöntemi','Exposure form'=>'Maruziyet biçimi','Medium'=>'Ortam','Effect'=>'Etki','Experimental record'=>'Deney kaydı','Study group'=>'Çalışma grubu','Source'=>'Kaynak','Subsource'=>'Alt kaynak','Original value type'=>'Özgün değer türü','Original subtype'=>'Özgün alt tür','Original study type'=>'Özgün çalışma türü','Original duration class'=>'Özgün süre sınıfı','Original duration'=>'Özgün süre','Original duration unit'=>'Özgün süre birimi','Original species'=>'Özgün tür','Original strain'=>'Özgün suş','Original sex'=>'Özgün cinsiyet','Original generation'=>'Özgün nesil','Original life stage'=>'Özgün yaşam evresi','Original route'=>'Özgün maruziyet yolu','Original method'=>'Özgün yöntem','Original form'=>'Özgün biçim','Original medium'=>'Özgün ortam','Original effect'=>'Özgün etki','Monoisotopic mass'=>'Monoizotopik kütle','Response site'=>'Yanıt bölgesi','Applicability method'=>'Uygulanabilirlik yöntemi','Applicability value'=>'Uygulanabilirlik değeri','Applicability conclusion'=>'Uygulanabilirlik sonucu','Applicability reasoning'=>'Uygulanabilirlik gerekçesi','Global applicability method'=>'Genel uygulanabilirlik yöntemi','Global applicability value'=>'Genel uygulanabilirlik değeri','Global applicability conclusion'=>'Genel uygulanabilirlik sonucu','Global applicability reasoning'=>'Genel uygulanabilirlik gerekçesi'];
 $routes=['oral'=>'Oral (ağız yoluyla)','inhalation'=>'İnhalasyon (solunum yoluyla)','dermal'=>'Dermal (deri yoluyla)','skin'=>'Deri yoluyla','intravenous'=>'İntravenöz (damar içi)','intraperitoneal'=>'İntraperitoneal (periton içi)','subcutaneous'=>'Subkutan (deri altı)','intramuscular'=>'İntramüsküler (kas içi)','ocular'=>'Oküler (göz yoluyla)','eye'=>'Göz yoluyla','intragastric'=>'İntragastrik (mide içine)','unknown'=>'Belirtilmemiş','not reported'=>'Bildirilmemiş','unspecified'=>'Belirtilmemiş'];
 $fixed=['Experimental value'=>'Deneysel değer','Predicted value (not a measurement)'=>'Hesaplanan değer (ölçüm değildir)'];$lines=[];$queries=[];
 foreach(explode("\n",$source) as $line){
  if(isset($fixed[$line])){$lines[]=['fixed'=>$fixed[$line]];continue;}
  $prefix='';$value=$line;$label='';
  if(preg_match('/^([^:]+): (.*)$/u',$line,$m)&&isset($labels[$m[1]])){$label=$m[1];$prefix=$labels[$label].': ';$value=$m[2];}
  if(in_array($label,['Exposure route','Original route'],true)){$lines[]=['fixed'=>$prefix.($routes[strtolower($value)]??$value.' (özgün kaynak terimi)')];continue;}
  if($value===''||!preg_match('/[a-z]{3}/i',$value)||in_array($label,['Unit','Original unit','Dataset','Model','Species Latin name','Qualifier','Value','Original value','Duration','Original duration','Temperature (°C)','Pressure (mmHg)','pH','Prediction error','Study group','Source','Subsource','Value type','Original value type','Monoisotopic mass'],true)){$lines[]=['fixed'=>$prefix.$value];continue;}
  $lines[]=['prefix'=>$prefix,'query'=>count($queries)];$queries[]=$value;
 }
 if(count($queries)>128)throw new InvalidArgumentException('Bu kaynak metni çok fazla ayrı satır içeriyor.');
 return ['lines'=>$lines,'queries'=>$queries,'characters'=>array_sum(array_map(fn($v)=>mb_strlen($v,'UTF-8'),$queries))];
}
function chemicalGoogleFetch(array $queries,string $key): array {
 $ch=curl_init('https://translation.googleapis.com/language/translate/v2');$body='';$large=false;
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>25,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-Goog-Api-Key: '.$key],CURLOPT_POSTFIELDS=>json_encode(['q'=>$queries,'source'=>'en','target'=>'tr','format'=>'text','model'=>'nmt'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body,&$large){if(strlen($body)+strlen($chunk)>1000000){$large=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
 $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);unset($ch);
 // Never expose the key or upstream error body to the browser/logs.
 if(in_array($status,[400,401,403],true))throw new RuntimeException('Google çeviri isteğini kabul etmedi. API anahtarı, Cloud Translation API kısıtlaması ve faturalandırmayı kontrol et.',503);
 if($status===429)throw new RuntimeException('Google çeviri kotası doldu. Google Cloud kota ayarlarını kontrol et.',429);
 if(!$ok||$large||$status!==200)throw new RuntimeException('Google çevirisinden yanıt alınamadı. Otomatik tekrar yapılmadı.',502);
 $data=json_decode($body,true);$rows=$data['data']['translations']??null;
 if(!is_array($rows)||count($rows)!==count($queries))throw new RuntimeException('Google eksik çeviri döndürdü; forma işlenmedi.',502);
 $out=[];foreach($rows as $row){if(!is_string($row['translatedText']??null)||trim($row['translatedText'])==='')throw new RuntimeException('Google boş çeviri döndürdü; forma işlenmedi.',502);$out[]=html_entity_decode($row['translatedText'],ENT_QUOTES|ENT_HTML5,'UTF-8');}return $out;
}
function chemicalGoogleTranslate(PDO $db,array $config,array $input,?callable $fetch=null): array {
 $source=chemicalGoogleSource($db,$input);$hash=chemicalGoogleHash($source);$usage=chemicalGoogleStatus($db,$config);
 $q=$db->prepare('SELECT * FROM chemical_google_translations WHERE source_hash=?');$q->execute([$hash]);$old=$q->fetch(PDO::FETCH_ASSOC);
 if($old&&$old['state']==='ready')return ['translated'=>$old['translated'],'provider'=>'Google Cloud Translation','cached'=>true,'usage'=>$usage];
 $plan=chemicalGooglePlan($source);$key=chemicalGoogleKey($config);
 if($key==='')throw new RuntimeException('Google çeviri anahtarı henüz sunucuya eklenmedi.',503);
 $now=time();$claim=bin2hex(random_bytes(16));$sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
 $q=$db->prepare(($sqlite?'INSERT OR IGNORE':'INSERT IGNORE')." INTO chemical_google_translations VALUES(?,'','pending',?,?)");$q->execute([$hash,$claim,$now]);$claimed=$q->rowCount()>0;
 if(!$claimed){$q=$db->prepare("UPDATE chemical_google_translations SET state='pending',claim=?,updated_at=? WHERE source_hash=? AND (state='failed' OR (state='pending' AND updated_at<?))");$q->execute([$claim,$now,$hash,$now-180]);$claimed=$q->rowCount()>0;}
 if(!$claimed)throw new RuntimeException('Bu metin başka bir istekte çevriliyor. Biraz sonra kaydı yeniden aç.',409);
 try{
  $chars=$plan['characters'];$db->prepare(($sqlite?'INSERT OR IGNORE':'INSERT IGNORE').' INTO chemical_translation_usage VALUES(?,0)')->execute([$usage['month']]);
  if($chars>0){$q=$db->prepare('UPDATE chemical_translation_usage SET characters=characters+? WHERE month_key=? AND characters<=?');$q->execute([$chars,$usage['month'],$usage['limit']-$chars]);if(!$q->rowCount())throw new RuntimeException('Aylık uygulama çeviri sınırına ulaşıldı. Kayıtlı çevirileri okuyabilirsin.',429);}
  // Reserve before sending. Timeouts/failed validation remain counted conservatively; no automatic retries.
  $result=$plan['queries']?($fetch??'chemicalGoogleFetch')($plan['queries'],$key):[];$translated=[];
  if(!is_array($result)||count($result)!==count($plan['queries']))throw new RuntimeException('Çeviri yanıtı eksik.',502);
  foreach($plan['lines'] as $line)$translated[]=$line['fixed']??($line['prefix'].$result[$line['query']]);
  $text=implode("\n",$translated);chemicalLocalTranslationValidate($source,$text);
  $q=$db->prepare("UPDATE chemical_google_translations SET translated=?,state='ready',updated_at=? WHERE source_hash=? AND claim=? AND state='pending'");$q->execute([$text,time(),$hash,$claim]);
  if(!$q->rowCount())throw new RuntimeException('Çeviri kaydedilemedi. Kaydı yeniden aç.',409);
  return ['translated'=>$text,'provider'=>'Google Cloud Translation','cached'=>false,'usage'=>chemicalGoogleStatus($db,$config)];
 }catch(Throwable $e){$db->prepare("UPDATE chemical_google_translations SET state='failed',updated_at=? WHERE source_hash=? AND claim=? AND state='pending'")->execute([time(),$hash,$claim]);throw $e;}
}
