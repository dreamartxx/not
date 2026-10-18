<?php
declare(strict_types=1);

function educationGuestAccess(PDO $db): array {
 $db->exec('CREATE TABLE IF NOT EXISTS education_guest_access (id INTEGER PRIMARY KEY, username VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, version INTEGER NOT NULL DEFAULT 1)');
 $seed=require __DIR__.'/../private/education-guest-seed.php';
 $q=$db->prepare($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO education_guest_access VALUES (1,?,?,1,1)':'INSERT IGNORE INTO education_guest_access VALUES (1,?,?,1,1)');
 $q->execute([$seed['username'],$seed['password_hash']]);
 return $db->query('SELECT * FROM education_guest_access WHERE id=1')->fetch();
}
function educationGuestManage(PDO $db,string $method,array $input): never {
 $access=educationGuestAccess($db);
 if($method==='POST'){
  $username=$input['username']??null;$password=$input['password']??'';$enabled=$input['enabled']??null;
  if(!is_string($username)||mb_strlen(trim($username))<2||mb_strlen($username)>100||!is_bool($enabled)||!is_string($password)||($password!==''&&(mb_strlen($password)<7||strlen($password)>128)))fail('Kullanıcı adı 2–100, yeni şifre en az 7 karakter olmalı.');
  $hash=$password!==''?password_hash($password,PASSWORD_DEFAULT):$access['password_hash'];
  $q=$db->prepare('UPDATE education_guest_access SET username=?,password_hash=?,enabled=?,version=version+1 WHERE id=1');$q->execute([trim($username),$hash,(int)$enabled]);
  $access=educationGuestAccess($db);
 }
 respond(['username'=>$access['username'],'enabled'=>(bool)$access['enabled'],'url'=>'egitim.php']);
}
function educationGuestAPI(PDO $db,string $action,string $method): never {
 $access=educationGuestAccess($db);
 $fingerprint=hash('sha256',$access['password_hash'].':'.$access['version']);
 if(isset($_SESSION['guest'])&&(!$access['enabled']||!hash_equals($fingerprint,(string)$_SESSION['guest'])))unset($_SESSION['guest'],$_SESSION['guest_data'],$_SESSION['guest_version']);
 if($method==='GET'&&$action==='status')respond(['authenticated'=>isset($_SESSION['guest']),'csrf'=>$_SESSION['csrf']]);
 $input=[];
 if($method==='POST'){
  if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))fail('Oturum doğrulanamadı. Sayfayı yenile.',403);
  $raw=file_get_contents('php://input',false,null,0,500001);
  if(strlen($raw)>500000)fail('İçerik çok büyük.',413);
  $input=json_decode($raw,true);if(!is_array($input))fail('Geçersiz istek.');
 }elseif($method!=='GET')fail('Desteklenmeyen yöntem.',405);
 if($action==='login'&&$method==='POST'){
  // A separate rate-limit bucket cannot lock the owner out of the private app.
  $db->exec($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO login_guard VALUES (2,0,0)':'INSERT IGNORE INTO login_guard VALUES (2,0,0)');
  $now=time();$q=$db->prepare('UPDATE login_guard SET attempts=0,window_start=? WHERE id=2 AND window_start<?');$q->execute([$now,$now-900]);
  $db->exec('UPDATE login_guard SET attempts=attempts+1 WHERE id=2');
  if((int)$db->query('SELECT attempts FROM login_guard WHERE id=2')->fetchColumn()>30)fail('Çok fazla giriş denemesi. 15 dakika sonra tekrar dene.',429);
  $username=$input['username']??'';$password=$input['password']??'';
  if(!is_string($username)||!is_string($password)||strlen($password)>128||!$access['enabled']||!hash_equals($access['username'],trim($username))||!password_verify($password,$access['password_hash']))fail('Kullanıcı adı veya şifre yanlış ya da misafir erişimi kapalı.',401);
  $db->exec('UPDATE login_guard SET attempts=0 WHERE id=2');
  session_regenerate_id(true);$_SESSION=['guest'=>$fingerprint,'csrf'=>bin2hex(random_bytes(32)),'last_seen'=>time()];
  respond(['ok'=>true,'csrf'=>$_SESSION['csrf']]);
 }
 if(!isset($_SESSION['guest']))fail('Eğitime erişmek için misafir girişi yap.',401);
 if($action==='logout'&&$method==='POST'){$_SESSION=[];session_destroy();respond(['ok'=>true]);}
 // Default deny: no owner data or private application route is reachable here.
 if(!in_array($action,['education','education-save','education-book','education-figure'],true))fail('Misafir erişimi yalnızca eğitim içindir.',403);
 require_once __DIR__.'/education.php';
 if($method==='GET'&&in_array($action,['education-book','education-figure'],true))educationAPI($db,$action,$method,[]);
 if($action==='education'&&$method==='GET')respond(['data'=>$_SESSION['guest_data']??educationBlank(),'version'=>$_SESSION['guest_version']??1]);
 if($action==='education-save'&&$method==='POST'){
  try{$data=educationClean($input['data']??null);}catch(InvalidArgumentException $e){fail($e->getMessage());}
  if($data['notes']||$data['cards']||count((array)($data['learning']['book']['notes']??[]))||count((array)($data['learning']['study']['notes']??[])))fail('Misafir erişiminde not alma ve kart oluşturma kapalı.',403);
  $version=$_SESSION['guest_version']??1;if(($input['version']??null)!==$version)fail('Eğitim başka bir sekmede değişti. Sayfayı yenile.',409);
  $_SESSION['guest_data']=$data;$_SESSION['guest_version']=$version+1;respond(['version'=>$version+1]);
 }
 fail('İşlem bulunamadı.',404);
}
