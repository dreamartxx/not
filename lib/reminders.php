<?php
// Page-owned reminder metadata participates in the note's versioned transaction.
$db->exec('CREATE TABLE IF NOT EXISTS page_reminders (page_id VARCHAR(40) PRIMARY KEY, payload TEXT NOT NULL)');
function validReminders(mixed $items): array {
 if(!is_array($items)||!array_is_list($items)||count($items)>30)fail('Bir notta en fazla 30 hatırlatıcı olabilir.');
 $out=[];$ids=[];
 foreach($items as $item){
  if(!is_array($item))fail('Geçersiz hatırlatıcı.');$id=$item['id']??'';
  if(!is_string($id)||!preg_match('/^[a-zA-Z0-9-]{8,40}$/D',$id)||isset($ids[$id]))fail('Geçersiz hatırlatıcı kimliği.');$ids[$id]=true;
  $at=$item['at']??'';
  if(!is_string($at)||!preg_match('/^20\d{2}-\d{2}-\d{2}T\d{2}:\d{2}:00Z$/D',$at))fail('Geçerli tarih ve saat seç.');
  $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$at,new DateTimeZone('UTC'));
  if(!$date||$date->format('Y-m-d\TH:i:s\Z')!==$at)fail('Geçerli tarih ve saat seç.');
  if(!is_bool($item['done']??false))fail('Geçersiz hatırlatıcı durumu.');
  $out[]=['id'=>$id,'label'=>shortText($item['label']??'',160),'at'=>$at,'done'=>$item['done']??false,'lead'=>choice($item['lead']??60,[0,5,15,30,60,180,1440]),'color'=>choice($item['color']??'violet',['violet','blue','pink','orange','teal'])];
 }
 return $out;
}
function pageReminders(PDO $db,string $id): array {$q=$db->prepare('SELECT payload FROM page_reminders WHERE page_id=?');$q->execute([$id]);return json_decode($q->fetchColumn()?:'[]',true);}
function saveReminders(PDO $db,string $id,array $items): void {
 $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO page_reminders VALUES(?,?) ON CONFLICT(page_id) DO UPDATE SET payload=excluded.payload':'INSERT INTO page_reminders VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)';
 $db->prepare($sql)->execute([$id,json_encode($items,JSON_UNESCAPED_UNICODE)]);
}
