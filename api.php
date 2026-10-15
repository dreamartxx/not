<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(array $data, int $status = 200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function fail(string $message, int $status = 400): never { respond(['error' => $message], $status); }
set_exception_handler(function(Throwable $e) { error_log('Not API: '.$e->getMessage()); fail('Sunucu işlemi tamamlayamadı. Bağlantını kontrol edip tekrar dene.', 500); });
$externalConfig = dirname(rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/')).'/not-config.php';
$configPath = getenv('NOT_CONFIG') ?: (is_file($externalConfig) ? $externalConfig : __DIR__.'/private/config.php');
if (!is_file($configPath)) respond(['configured'=>false, 'error'=>'Sunucu ayarları henüz tamamlanmadı.'], 503);
$config = require $configPath;
$secure = $config['secure_cookie'] ?? true;
session_name('not_session');
session_start(['cookie_httponly'=>true,'cookie_secure'=>$secure,'cookie_samesite'=>'Strict','use_strict_mode'=>true,'cookie_path'=>'/']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
if (isset($_SESSION['last_seen']) && time()-$_SESSION['last_seen'] > 43200) { unset($_SESSION['owner']); }
$_SESSION['last_seen'] = time();
$db = new PDO($config['dsn'], $config['db_user'] ?? null, $config['db_password'] ?? null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$sqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
if ($sqlite) $db->exec('PRAGMA busy_timeout=5000');
$db->exec('CREATE TABLE IF NOT EXISTS owner (id INTEGER PRIMARY KEY, username VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS pages (id VARCHAR(40) PRIMARY KEY, parent_id VARCHAR(40) NULL, title VARCHAR(255) NOT NULL, icon VARCHAR(32) NOT NULL, cover VARCHAR(20) NOT NULL, blocks MEDIUMTEXT NOT NULL, favorite INTEGER NOT NULL DEFAULT 0, deleted_at VARCHAR(30) NULL, version INTEGER NOT NULL DEFAULT 1, created_at VARCHAR(30) NOT NULL, updated_at VARCHAR(30) NOT NULL)');
// One global rate-limit row deliberately protects this single-user application across sessions.
$db->exec('CREATE TABLE IF NOT EXISTS login_guard (id INTEGER PRIMARY KEY, attempts INTEGER NOT NULL, window_start BIGINT NOT NULL)');
$db->exec($sqlite ? 'INSERT OR IGNORE INTO login_guard VALUES (1,0,0)' : 'INSERT IGNORE INTO login_guard VALUES (1,0,0)');
$action = $_GET['action'] ?? 'status';
$method = $_SERVER['REQUEST_METHOD'];
$owner = $db->query('SELECT id, username, password_hash FROM owner WHERE id=1')->fetch();
if (isset($_SESSION['owner']) && (!isset($_SESSION['fingerprint']) || !$owner || !hash_equals(hash('sha256',$owner['password_hash']), $_SESSION['fingerprint']))) unset($_SESSION['owner']);
if ($method === 'GET' && $action === 'status') respond(['configured'=>true,'setup'=>!$owner,'authenticated'=>isset($_SESSION['owner']),'username'=>isset($_SESSION['owner'])?$owner['username']:null,'csrf'=>$_SESSION['csrf']]);
if ($method === 'POST' && $action !== 'upload') {
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) fail('Oturum doğrulanamadı. Sayfayı yenile.',403);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>5_000_000) fail('İçerik çok büyük.',413);
    $raw=file_get_contents('php://input',false,null,0,5_000_001);
    if (strlen($raw)>5_000_000) fail('İçerik çok büyük.',413);
    $input=json_decode($raw,true);
    if (!is_array($input)) fail('Geçersiz istek.');
} elseif ($method !== 'GET' && $action !== 'upload') fail('Desteklenmeyen yöntem.',405);
if (in_array($action,['setup','login'],true) && $method==='POST') {
    $now=time();
    $stmt=$db->prepare('UPDATE login_guard SET attempts=0, window_start=? WHERE id=1 AND window_start<?'); $stmt->execute([$now,$now-900]);
    $db->exec('UPDATE login_guard SET attempts=attempts+1 WHERE id=1');
    if ((int)$db->query('SELECT attempts FROM login_guard WHERE id=1')->fetchColumn()>15) fail('Çok fazla giriş denemesi. 15 dakika sonra tekrar dene.',429);
    $username=trim((string)($input['username']??'')); $password=(string)($input['password']??'');
    if ($action==='setup') {
        if ($owner) fail('Hesap zaten oluşturuldu.',403);
        if (str_starts_with($config['setup_key']??'', 'BURAYA_') || strlen($config['setup_key']??'')<32 || !hash_equals($config['setup_key'],(string)($input['setup_key']??''))) fail('Kurulum anahtarı geçersiz.',403);
        if (mb_strlen($username)<2 || mb_strlen($username)>100 || strlen($password)<12 || strlen($password)>128) fail('Kullanıcı adı 2–100, şifre 12–128 karakter olmalı.');
        $stmt=$db->prepare('INSERT INTO owner (id,username,password_hash) VALUES (1,?,?)'); $stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT)]);
    } else {
        $record=$db->query('SELECT * FROM owner WHERE id=1')->fetch();
        if (!$record || !hash_equals($record['username'],$username) || !password_verify($password,$record['password_hash'])) fail('Kullanıcı adı veya şifre yanlış.',401);
    }
    $db->exec('UPDATE login_guard SET attempts=0 WHERE id=1');
    $_SESSION['fingerprint']=hash('sha256',(string)$db->query('SELECT password_hash FROM owner WHERE id=1')->fetchColumn());
    session_regenerate_id(true); $_SESSION['owner']=1; $_SESSION['csrf']=bin2hex(random_bytes(32));
    respond(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}
if (!isset($_SESSION['owner'])) fail('Devam etmek için giriş yap.',401);
if ($action==='password' && $method==='POST') {
    $current=(string)($input['current_password']??'');$next=(string)($input['new_password']??'');
    if(!password_verify($current,$owner['password_hash'])) fail('Mevcut şifre yanlış.',403);
    if(strlen($next)<12||strlen($next)>128) fail('Yeni şifre 12–128 karakter olmalı.');
    $hash=password_hash($next,PASSWORD_DEFAULT);$stmt=$db->prepare('UPDATE owner SET password_hash=? WHERE id=1');$stmt->execute([$hash]);
    session_regenerate_id(true);$_SESSION['fingerprint']=hash('sha256',$hash);$_SESSION['csrf']=bin2hex(random_bytes(32));respond(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
}
if ($action==='logout' && $method==='POST') { $_SESSION=[]; session_destroy(); respond(['ok'=>true]); }
function allPages(PDO $db): array { return $db->query('SELECT * FROM pages ORDER BY created_at,id')->fetchAll(); }
function decodePage(array $p): array { $p['blocks']=json_decode($p['blocks'],true); $p['favorite']=(bool)$p['favorite']; $p['version']=(int)$p['version']; return $p; }
function findPage(PDO $db,string $id): array { $s=$db->prepare('SELECT * FROM pages WHERE id=?');$s->execute([$id]);$p=$s->fetch();if(!$p) fail('Sayfa bulunamadı.',404);return $p; }
require __DIR__.'/lib/blocks.php';
require __DIR__.'/lib/media.php';
function validParent(PDO $db,?string $parent,string $id): void {
    $seen=[$id=>true];
    while($parent) { if(isset($seen[$parent])) fail('Bir sayfa kendi altına taşınamaz.');$seen[$parent]=true;$p=findPage($db,$parent);if($p['deleted_at']) fail('Çöp kutusundaki sayfaya eklenemez.');$parent=$p['parent_id']; }
}
if ($action==='pages' && $method==='GET') respond(['pages'=>array_map('decodePage',allPages($db))]);
if ($method!=='POST') fail('İşlem bulunamadı.',404);
if (in_array($action,['import','validate-import'],true)) {
    $items=$input['pages']??null;
    if(!is_array($items)||!array_is_list($items)||count($items)>500) fail('Bir defada en fazla 500 sayfa içe aktarılabilir.');
    $map=[];$clean=[];
    foreach($items as $item) {
        if(!is_array($item)||!is_string($item['id']??null)||isset($map[$item['id']])) fail('Geçersiz veya tekrar eden sayfa.');
        $map[$item['id']]=bin2hex(random_bytes(16));
        $title=$item['title']??'';$icon=$item['icon']??'📄';$cover=$item['cover']??'none';
        if(!is_string($title)||mb_strlen($title)>255||!is_string($icon)||mb_strlen($icon)>8||!in_array($cover,['none','sage','sand','lavender','blue','rose'],true)) fail('Geçersiz sayfa bilgileri.');
        $parent=$item['parent_id']??null;if($parent!==null&&!is_string($parent)) fail('Geçersiz üst sayfa.');
        $item['blocks']=validBlocks($item['blocks']??null);$clean[$item['id']]=$item;
    }
    foreach($clean as $id=>$item) {
        $seen=[$id=>true];$parent=$item['parent_id']??null;
        while($parent && isset($clean[$parent])) {if(isset($seen[$parent])) fail('Yedekte döngüsel sayfa ilişkisi var.');$seen[$parent]=true;$parent=$clean[$parent]['parent_id']??null;}
    }
    if($action==='validate-import') respond(['ok'=>true]);
    $db->beginTransaction();$now=gmdate('c');
    $stmt=$db->prepare('INSERT INTO pages (id,parent_id,title,icon,cover,blocks,favorite,deleted_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach($clean as $id=>$item) {
        $stmt->execute([$map[$id],$map[$item['parent_id']??'']??null,$item['title']??'',$item['icon']??'📄',$item['cover']??'none',json_encode($item['blocks'],JSON_UNESCAPED_UNICODE),(int)(bool)($item['favorite']??false),empty($item['deleted_at'])?null:$now,$now,$now]);
    }
    $db->commit();respond(['ok'=>true,'count'=>count($clean)],201);
}
if ($action==='create') {
    $id=bin2hex(random_bytes(16));$parent=$input['parent_id']??null; if($parent!==null && !is_string($parent)) fail('Geçersiz üst sayfa.');validParent($db,$parent,$id);
    $title=mb_substr(trim((string)($input['title']??'Başlıksız')),0,255);$now=gmdate('c');
    $blocks=validBlocks($input['blocks']??[['id'=>bin2hex(random_bytes(8)),'type'=>'text','text'=>'']]);
    $s=$db->prepare('INSERT INTO pages (id,parent_id,title,icon,cover,blocks,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)');
    $s->execute([$id,$parent,$title,'📄','none',json_encode($blocks,JSON_UNESCAPED_UNICODE),$now,$now]);respond(['page'=>decodePage(findPage($db,$id))],201);
}
if ($action==='save') {
    $id=(string)($input['id']??'');$p=findPage($db,$id);if($p['deleted_at']) fail('Bu sayfa çöp kutusunda.',409);
    $blocks=validBlocks($input['blocks']??null);$parent=$input['parent_id']??null;if($parent!==null && !is_string($parent)) fail('Geçersiz üst sayfa.');validParent($db,$parent,$id);
    $title=$input['title']??'';$icon=$input['icon']??'📄';$cover=$input['cover']??'none';
    if (!is_string($title)||mb_strlen($title)>255||!is_string($icon)||mb_strlen($icon)>8||!in_array($cover,['none','sage','sand','lavender','blue','rose'],true)) fail('Geçersiz sayfa bilgileri.');
    $s=$db->prepare('UPDATE pages SET title=?,icon=?,cover=?,blocks=?,favorite=?,parent_id=?,updated_at=?,version=version+1 WHERE id=? AND version=? AND deleted_at IS NULL');
    $s->execute([$title,$icon,$cover,json_encode($blocks,JSON_UNESCAPED_UNICODE),(int)(bool)($input['favorite']??false),$parent,gmdate('c'),$id,(int)($input['version']??0)]);
    if(!$s->rowCount()) fail('Bu not başka bir sekmede değişti. Yerel taslağını indirip sayfayı yenile.',409);
    respond(['page'=>decodePage(findPage($db,$id))]);
}
if (in_array($action,['trash','restore','purge'],true)) {
    $id=(string)($input['id']??'');$p=findPage($db,$id);
    if ($action==='purge' && !$p['deleted_at']) fail('Önce sayfayı çöp kutusuna taşı.');
    $pages=allPages($db);$ids=[$id];
    do { $count=count($ids); foreach($pages as $item) if(in_array($item['parent_id'],$ids,true)&&!in_array($item['id'],$ids,true)) $ids[]=$item['id']; } while(count($ids)>$count);
    $db->beginTransaction();
    if ($action==='restore') { $s=$db->prepare('UPDATE pages SET parent_id=NULL WHERE id=?');$s->execute([$id]); }
    foreach($ids as $pid) {
        if($action==='purge') {$s=$db->prepare('DELETE FROM pages WHERE id=?');$s->execute([$pid]);}
        else {$s=$db->prepare('UPDATE pages SET deleted_at=?,version=version+1,updated_at=? WHERE id=?');$s->execute([$action==='trash'?gmdate('c'):null,gmdate('c'),$pid]);}
    }
    $db->commit();respond(['ok'=>true]);
}
fail('İşlem bulunamadı.',404);
