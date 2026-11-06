<?php
declare(strict_types=1);
$db->exec('CREATE TABLE IF NOT EXISTS page_mindmaps (page_id VARCHAR(40) PRIMARY KEY, payload MEDIUMTEXT NOT NULL)');
function validMindmap(mixed $map): ?array {
 if($map===null)return null;
 if(!is_array($map)||!is_array($nodes=$map['nodes']??null)||!array_is_list($nodes)||count($nodes)<1||count($nodes)>150)fail('Haritada 1–150 konu olabilir.');
 $out=[];$seen=[];
 foreach($nodes as $i=>$n){
  if(!is_array($n))fail('Geçersiz konu.');
  $id=$n['id']??null;$parent=$n['parent']??null;
  if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$id)||isset($seen[$id])||($i===0?$parent!==null:!is_string($parent)||!isset($seen[$parent])))fail('Konu bağlantısı geçersiz.');
  $label=trim(shortText($n['label']??'',100));if($label==='')fail('Konu adı boş olamaz.');
  $seen[$id]=true;$out[]=['id'=>$id,'parent'=>$parent,'label'=>$label];
 }
 return ['nodes'=>$out,'weight'=>number($map['weight']??3,1,9),'length'=>number($map['length']??90,40,220),'motion'=>choice($map['motion']??'still',['still','float','ripple'])];
}
function saveMindmap(PDO $db,string $id,?array $map): void{
 if(!$map){$db->prepare('DELETE FROM page_mindmaps WHERE page_id=?')->execute([$id]);return;}
 $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO page_mindmaps VALUES(?,?) ON CONFLICT(page_id) DO UPDATE SET payload=excluded.payload':'INSERT INTO page_mindmaps VALUES(?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload)';
 $db->prepare($sql)->execute([$id,json_encode($map,JSON_UNESCAPED_UNICODE)]);
}
