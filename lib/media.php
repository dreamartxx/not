<?php
// Included only after the owner session has been verified.
$db->exec('CREATE TABLE IF NOT EXISTS media (id VARCHAR(32) PRIMARY KEY, name VARCHAR(255) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INTEGER NOT NULL, content LONGBLOB NOT NULL, created_at VARCHAR(30) NOT NULL)');
$db->exec("CREATE TABLE IF NOT EXISTS football_settings (id INTEGER PRIMARY KEY, media_id VARCHAR(32) NOT NULL, position INTEGER NOT NULL)");
if($action==='media' && $method==='GET'){
 $s=$db->prepare('SELECT * FROM media WHERE id=?');$s->execute([$_GET['id']??'']);$m=$s->fetch();if(!$m)fail('Dosya bulunamadı.',404);
 session_write_close();header('Content-Type: '.$m['mime']);header('Content-Length: '.$m['bytes']);header('Cache-Control: private, no-store');header("Content-Security-Policy: default-src 'none'; sandbox");header('Content-Disposition: '.(isset($_GET['download'])?'attachment':'inline').'; filename="file"; filename*=UTF-8\'\''.rawurlencode($m['name']));echo $m['content'];exit;
}
if($action==='library' && $method==='GET'){
 $list=$db->query('SELECT id,name,mime,bytes,created_at FROM media ORDER BY created_at DESC')->fetchAll();
 $content=implode(' ',array_column($db->query('SELECT blocks FROM pages')->fetchAll(),'blocks'));
 $content.=' '.implode(' ',array_column($db->query('SELECT media_id FROM football_settings')->fetchAll(),'media_id'));
 foreach($list as &$item)$item['used']=str_contains($content,$item['id']);unset($item);respond(['media'=>$list]);
}
if($action==='remove-media' && $method==='POST'){
 $id=mediaId($input['id']??'');if(!$id)fail('Dosya gerekli.');
 $q=$db->prepare('SELECT COUNT(*) FROM pages WHERE blocks LIKE ?');$q->execute(['%'.$id.'%']);if((int)$q->fetchColumn())fail('Dosya bir notta kullanılıyor. Çöp kutusundaki notlar da korunur.',409);
 $q=$db->prepare('SELECT COUNT(*) FROM football_settings WHERE media_id=?');$q->execute([$id]);if((int)$q->fetchColumn())fail('Dosya futbol kapağında kullanılıyor. Önce kapağı değiştir veya kaldır.',409);
 $q=$db->prepare('DELETE FROM media WHERE id=?');$q->execute([$id]);respond(['ok'=>true]);
}
if($action==='upload' && $method==='POST'){
 if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))fail('Oturum doğrulanamadı.',403);
 $file=$_FILES['file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK)fail('Dosya yüklenemedi. En fazla 8 MB dosya seç.',413);
 if($file['size']>8_000_000||$file['size']===0)fail('Dosya 8 MB sınırını aşıyor.',413);
 if((int)$db->query('SELECT COALESCE(SUM(bytes),0) FROM media')->fetchColumn()+$file['size']>150_000_000)fail('Dosya alanı doldu (150 MB).',413);
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
 if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif','application/pdf','audio/mpeg','audio/wav','audio/x-wav','audio/ogg','audio/mp4','video/mp4'],true))fail('JPEG, PNG, WebP, GIF, PDF veya ses/MP4 dosyası kullan.');
 if(str_starts_with($mime,'image/')){$dim=getimagesize($file['tmp_name']);if(!$dim||$dim[0]*$dim[1]>40_000_000)fail('Görsel boyutu çok büyük.');}
 $id=bin2hex(random_bytes(16));$name=mb_substr(basename(str_replace('\\','/',$file['name'])),0,255);$now=gmdate('c');
 $s=$db->prepare('INSERT INTO media (id,name,mime,bytes,content,created_at) VALUES (?,?,?,?,?,?)');$s->execute([$id,$name,$mime,$file['size'],file_get_contents($file['tmp_name']),$now]);respond(['media'=>['id'=>$id,'name'=>$name,'mime'=>$mime,'bytes'=>$file['size'],'created_at'=>$now]],201);
}
