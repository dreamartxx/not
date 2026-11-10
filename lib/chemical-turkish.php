<?php
declare(strict_types=1);

function chemicalLocalTranslationValidate(string $source,string $translated): void {
 if(trim($translated)===''||strlen($translated)>500000||!mb_check_encoding($translated,'UTF-8'))throw new InvalidArgumentException('Geçerli Türkçe çeviri alınamadı.');
 $tokens=function(string $text):array{preg_match_all('/-?\d+(?:[.,]\d+)*/u',$text,$n);preg_match_all('/\b(?:H|P|EUH)\d{3}\b|°\s*[CF]|\b(?:ppm|ppb|mg\/m[3³]|g\/mol|mm\s?Hg|kPa|mPa|LD50|LC50)\b/u',$text,$u);$units=array_map(fn($v)=>preg_replace('/\s/u','',$v),$u[0]);sort($units);return [array_map(fn($v)=>str_replace(',','.',$v),$n[0]),$units];};
 if($tokens($source)!==$tokens($translated))throw new InvalidArgumentException('Çeviride sayı veya birim değişti; kayıt yapılmadı.');
 if(trim($source)===trim($translated)&&preg_match('/[a-z]{3}/i',$source))throw new InvalidArgumentException('Metin Türkçeye çevrilmedi.');
}
function chemicalLocalTable(PDO $db): void {$db->exec('CREATE TABLE IF NOT EXISTS chemical_local_translations (source_hash VARCHAR(64) PRIMARY KEY,translated MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');}
function chemicalLocalTranslationSave(PDO $db,array $input): array {
 foreach(['section','entry'] as $field)if(!isset($input[$field])||!is_int($input[$field]))throw new InvalidArgumentException('Kaynak kimliği geçersiz.');
 if($input['section']<1||$input['section']>16||$input['entry']<0)throw new InvalidArgumentException('Kaynak kimliği geçersiz.');
 require_once __DIR__.'/comptox.php';$id=comptoxId($input['dtxsid']??null);
 $db->exec('CREATE TABLE IF NOT EXISTS chemical_cache (cache_key VARCHAR(80) PRIMARY KEY,payload MEDIUMTEXT NOT NULL,updated_at BIGINT NOT NULL)');
 $q=$db->prepare('SELECT payload FROM chemical_cache WHERE cache_key=?');$q->execute(['comptox-v1:'.$id]);$raw=$q->fetchColumn();if(!$raw)throw new InvalidArgumentException('Önce kimyasal kaydını aç.');
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
