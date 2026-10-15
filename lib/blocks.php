<?php
declare(strict_types=1);
function shortText(mixed $v, int $max=10000): string { if(!is_string($v)||mb_strlen($v)>$max) fail('İçerik sınırı aşıldı.');return $v; }
function choice(mixed $v,array $choices): mixed {if(!in_array($v,$choices,true))fail('Geçersiz seçenek.');return $v;}
function number(mixed $v,float $min,float $max): float {if(!is_numeric($v)||!is_finite((float)$v)||$v<$min||$v>$max)fail('Geçersiz boyut.');return (float)$v;}
function safeLink(mixed $v): string { $v=shortText($v,2048);if($v!=='' && (!filter_var($v,FILTER_VALIDATE_URL)||strtolower((string)parse_url($v,PHP_URL_SCHEME))!=='https'||parse_url($v,PHP_URL_USER)||parse_url($v,PHP_URL_PASS)))fail('Geçerli bir HTTPS bağlantısı kullan.');return $v; }
function mediaId(mixed $v): string {if(!is_string($v)||($v!==''&&!preg_match('/^[a-f0-9]{32}$/D',$v)))fail('Geçersiz dosya.');return $v;}
function validBlocks(mixed $blocks): array {
 if(!is_array($blocks)||!array_is_list($blocks)||count($blocks)>2000)fail('Geçersiz blok listesi.');
 $out=[];$ids=[];
 foreach($blocks as $b){
  if(!is_array($b))fail('Geçersiz blok.');
  $type=choice($b['type']??'', ['text','h1','h2','bullet','number','todo','quote','code','divider','callout','toggle','table','image','video','audio','file','bookmark','drawing','diagram','board','toc']);
  $id=$b['id']??'';if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$id)||isset($ids[$id]))fail('Geçersiz blok kimliği.');$ids[$id]=true;
  $r=['id'=>$id,'type'=>$type,'text'=>shortText($b['text']??'',100000),'checked'=>(bool)($b['checked']??false), 'width'=>choice($b['width']??'full',['full','half']), 'align'=>choice($b['align']??'left',['left','center','right']), 'tone'=>choice($b['tone']??'default',['default','sage','sand','lavender','blue','rose'])];
  if($type==='toggle')$r['details']=shortText($b['details']??'',100000);
  if($type==='code')$r['language']=shortText($b['language']??'Metin',30);
  if($type==='table'){
   $rows=$b['rows']??[['Başlık 1','Başlık 2'],['','']];if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>100)fail('Tablo 1–100 satır içermeli.');
   if(!is_array($rows[0]))fail('Geçersiz tablo satırı.');$cols=count($rows[0]);if($cols<1||$cols>12)fail('Tablo 1–12 sütun içermeli.');
   $r['rows']=[];foreach($rows as $row){if(!is_array($row)||!array_is_list($row)||count($row)!==$cols)fail('Tablo satırları eşit uzunlukta olmalı.');$r['rows'][]=array_map(fn($v)=>shortText($v,5000),$row);}$r['header']=(bool)($b['header']??true);
  }
  if(in_array($type,['image','video','audio','file','bookmark'],true)){
   $r['url']=safeLink($b['url']??'');$r['media']=mediaId($b['media']??'');$r['caption']=shortText($b['caption']??'',1000);$r['size']=number($b['size']??100,25,100);
  }
  if($type==='board'){
   $columns=$b['columns']??[['title'=>'Yapılacak','cards'=>[]],['title'=>'Devam ediyor','cards'=>[]],['title'=>'Tamamlandı','cards'=>[]]];
   if(!is_array($columns)||!array_is_list($columns)||count($columns)<1||count($columns)>6)fail('Geçersiz pano.');$r['columns']=[];
   foreach($columns as $column){if(!is_array($column)||!is_array($column['cards']??null)||!array_is_list($column['cards'])||count($column['cards'])>100)fail('Geçersiz pano kartları.');$r['columns'][]=['title'=>shortText($column['title']??'',100),'cards'=>array_map(fn($v)=>shortText($v,5000),$column['cards'])];}
  }
  if(in_array($type,['drawing','diagram'],true)){
   $objects=$b['objects']??[];if(!is_array($objects)||!array_is_list($objects)||count($objects)>500)fail('Tahtada en fazla 500 öğe olabilir.');$r['objects']=[];$objectIds=[];
   foreach($objects as $o){
    if(!is_array($o))fail('Geçersiz çizim öğesi.');$oid=shortText($o['id']??'',60);if(!preg_match('/^[a-zA-Z0-9_-]+$/D',$oid)||isset($objectIds[$oid]))fail('Geçersiz çizim kimliği.');$objectIds[$oid]=true;
    $obj=['id'=>$oid,'kind'=>choice($o['kind']??'',['pen','rect','ellipse','diamond','arrow','line','text','image']), 'x'=>number($o['x']??0,-2000,3000),'y'=>number($o['y']??0,-2000,3000),'w'=>number($o['w']??100,-2000,2000),'h'=>number($o['h']??70,-2000,2000),'stroke'=>number($o['stroke']??3,1,20),'text'=>shortText($o['text']??'',2000),'media'=>mediaId($o['media']??'')];
    foreach(['color'=>'#334155','fill'=>'none'] as $k=>$default){$v=$o[$k]??$default;if(!is_string($v)||!preg_match('/^(#[a-fA-F0-9]{6}|none)$/D',$v))fail('Geçersiz çizim rengi.');$obj[$k]=$v;}
    foreach(['from','to'] as $k)$obj[$k]=shortText($o[$k]??'',60);
    $points=$o['points']??[];if(!is_array($points)||!array_is_list($points)||count($points)>3000)fail('Çizim çok uzun.');$obj['points']=[];foreach($points as $point){if(!is_array($point)||count($point)!==2)fail('Geçersiz çizim noktası.');$obj['points'][]=[number($point[0],-2000,3000),number($point[1],-2000,3000)];}
    $r['objects'][]=$obj;
   }
  }
  $out[]=$r;
 }
 if(strlen(json_encode($out))>4_000_000)fail('Sayfa çok büyük. Çalışmanı alt sayfalara bölebilirsin.',413);
 return $out;
}
