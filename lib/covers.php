<?php
// Private cover references, independent of note contents and character saves.
$db->exec('CREATE TABLE IF NOT EXISTS workspace_covers (scope VARCHAR(48) PRIMARY KEY, media_id VARCHAR(32) NOT NULL, position INTEGER NOT NULL)');
function validCover(mixed $cover): array {
 if(!is_array($cover))fail('Geçersiz kapak bilgisi.');
 $id=$cover['media_id']??'';$position=$cover['position']??50;
 if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{32}$/D',$id))||!is_int($position)||$position<0||$position>100)fail('Geçersiz kapak bilgisi.');
 return ['media_id'=>$id,'position'=>$position];
}
function coverImageExists(PDO $db,array $cover): void {
 if($cover['media_id']==='')return;
 $q=$db->prepare('SELECT mime FROM media WHERE id=?');$q->execute([$cover['media_id']]);
 if(!in_array($q->fetchColumn(),['image/jpeg','image/png','image/webp','image/gif'],true))fail('Kütüphanendeki bir görseli seç.');
}
function saveCover(PDO $db,string $scope,array $cover): void {
 if($scope==='football'){
  $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO football_settings VALUES(1,?,?) ON CONFLICT(id) DO UPDATE SET media_id=excluded.media_id,position=excluded.position':'INSERT INTO football_settings VALUES(1,?,?) ON DUPLICATE KEY UPDATE media_id=VALUES(media_id),position=VALUES(position)';
  $db->prepare($sql)->execute([$cover['media_id'],$cover['position']]);return;
 }
 $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO workspace_covers VALUES(?,?,?) ON CONFLICT(scope) DO UPDATE SET media_id=excluded.media_id,position=excluded.position':'INSERT INTO workspace_covers VALUES(?,?,?) ON DUPLICATE KEY UPDATE media_id=VALUES(media_id),position=VALUES(position)';
 $db->prepare($sql)->execute([$scope,$cover['media_id'],$cover['position']]);
}
function allCovers(PDO $db): array {
 $covers=[];
 foreach($db->query('SELECT * FROM workspace_covers')->fetchAll() as $r)$covers[$r['scope']]=['media_id'=>$r['media_id'],'position'=>(int)$r['position']];
 $r=$db->query('SELECT media_id,position FROM football_settings WHERE id=1')->fetch();
 if($r)$covers['football']=['media_id'=>$r['media_id'],'position'=>(int)$r['position']];
 return $covers;
}
function coversAPI(PDO $db,string $action,string $method,array $input): void {
 if($action==='covers'&&$method==='GET')respond(['covers'=>(object)allCovers($db)]);
 if($action!=='cover-save'||$method!=='POST')fail('Desteklenmeyen yöntem.',405);
 $scope=$input['scope']??null;
 if(!is_string($scope))fail('Geçersiz çalışma alanı.');
 if(preg_match('/^page:([a-f0-9]{32})$/D',$scope,$m)){
  $p=findPage($db,$m[1]);if($p['deleted_at'])fail('Bu sayfa çöp kutusunda.',409);
 }elseif(!in_array($scope,['home','favorites','trash','dnd','football','reminders'],true))fail('Geçersiz çalışma alanı.');
 $cover=validCover($input);coverImageExists($db,$cover);saveCover($db,$scope,$cover);respond(['cover'=>$cover]);
}
