<?php
declare(strict_types=1);
function educationCourse(): array {static $course=null;if($course===null){$root=__DIR__.'/../assets/data/education/';$course=json_decode(file_get_contents($root.'lessons.json'),true,512,JSON_THROW_ON_ERROR);$course['tutorials']=json_decode(file_get_contents($root.'tutorials.json'),true,512,JSON_THROW_ON_ERROR);$course['outline']=json_decode(file_get_contents($root.'outline.json'),true,512,JSON_THROW_ON_ERROR);$course['guides']=json_decode(file_get_contents($root.'guides.json'),true,512,JSON_THROW_ON_ERROR);}return $course;}
function educationBlank(): array {return ['page'=>12,'read'=>[],'bookmarks'=>[],'notes'=>[],'cards'=>[],'font'=>18,'learning'=>['selected'=>'yargilama','completed'=>[],'answers'=>(object)[]]];}
function educationClean($v): array {
 if(!is_array($v))throw new InvalidArgumentException('Eğitim verisi geçersiz.');
 $integer=function($n,int $lo,int $hi){if(!is_int($n)||$n<$lo||$n>$hi)throw new InvalidArgumentException('Sayısal eğitim alanı geçersiz.');return $n;};
 $str=function($s,int $max,bool $required=false){if(!is_string($s)||mb_strlen($s)>$max||($required&&trim($s)===''))throw new InvalidArgumentException('Eğitim metni geçersiz veya çok uzun.');return $s;};
 $list=function($a,int $max){if(!is_array($a)||!array_is_list($a)||count($a)>$max)throw new InvalidArgumentException('Eğitim listesi geçersiz.');return $a;};
 $out=['page'=>$integer($v['page']??null,1,307),'font'=>$integer($v['font']??null,16,24)];
 foreach(['read','bookmarks'] as $key){$a=$list($v[$key]??null,307);foreach($a as $n)$integer($n,1,307);if(count(array_unique($a))!==count($a))throw new InvalidArgumentException('Tekrarlanan sayfa.');$out[$key]=$a;}
 foreach(['notes'=>500,'cards'=>300] as $key=>$max){$out[$key]=[];$ids=[];foreach($list($v[$key]??null,$max) as $item){if(!is_array($item))throw new InvalidArgumentException('Kayıt geçersiz.');$id=$str($item['id']??null,40,true);if(!preg_match('/^[a-zA-Z0-9-]{8,40}$/D',$id)||isset($ids[$id]))throw new InvalidArgumentException('Kayıt kimliği geçersiz.');$ids[$id]=true;$row=['id'=>$id,'page'=>$integer($item['page']??null,1,307)];
  if($key==='notes'){$row['quote']=$str($item['quote']??null,2000);$row['text']=$str($item['text']??null,15000,true);}
  else{$row['front']=$str($item['front']??null,1000,true);$row['back']=$str($item['back']??null,5000,true);$row['level']=$integer($item['level']??null,0,6);$due=$str($item['due']??null,10,true);if(!preg_match('/^20\d{2}-\d{2}-\d{2}$/D',$due)||date('Y-m-d',strtotime($due)?:0)!==$due)throw new InvalidArgumentException('Tekrar tarihi geçersiz.');$row['due']=$due;}$out[$key][]=$row;
 }}
 if(array_key_exists('learning',$v)){
  $learn=$v['learning'];if(!is_array($learn))throw new InvalidArgumentException('Ders ilerlemesi geçersiz.');
  $lessons=educationCourse()['lessons'];$ids=array_column($lessons,'id');$selected=$learn['selected']??null;if(!in_array($selected,$ids,true))throw new InvalidArgumentException('Ders bulunamadı.');
  $completed=$list($learn['completed']??null,5);if(count(array_unique($completed))!==count($completed))throw new InvalidArgumentException('Tekrarlanan ders.');foreach($completed as $id)if(!is_string($id)||!in_array($id,$ids,true))throw new InvalidArgumentException('Ders bulunamadı.');
  $answers=$learn['answers']??null;if(!is_array($answers)||count($answers)>15)throw new InvalidArgumentException('Test yanıtları geçersiz.');$questions=[];foreach($lessons as $l)foreach($l['quiz'] as $q)$questions[$q['id']]=$q;
  foreach($answers as $id=>$choice){if(!isset($questions[$id]))throw new InvalidArgumentException('Soru bulunamadı.');$integer($choice,0,count($questions[$id]['options'])-1);}
  foreach($lessons as $l)if(in_array($l['id'],$completed,true))foreach($l['quiz'] as $q)if(($answers[$q['id']]??null)!==$q['correct'])throw new InvalidArgumentException('Dersi tamamlamak için tüm soruları doğru yanıtla.');
  $out['learning']=['selected'=>$selected,'completed'=>$completed,'answers'=>(object)$answers];
  if(array_key_exists('book',$learn)){
   $book=$learn['book'];$nodes=array_column(educationCourse()['outline']['nodes'],null,'id');if(!is_array($book))throw new InvalidArgumentException('Kitap ilerlemesi geçersiz.');
   $current=$book['current']??null;if(!is_string($current)||($current!==''&&!isset($nodes[$current])))throw new InvalidArgumentException('Kitap başlığı bulunamadı.');
   $visited=$list($book['visited']??null,count($nodes));if(count(array_unique($visited))!==count($visited))throw new InvalidArgumentException('Tekrarlanan kitap başlığı.');foreach($visited as $id)if(!is_string($id)||!isset($nodes[$id]))throw new InvalidArgumentException('Kitap başlığı bulunamadı.');
   $notes=$book['notes']??null;if(!is_array($notes)||count($notes)>count($nodes))throw new InvalidArgumentException('Kitap çalışma notları geçersiz.');foreach($notes as $id=>$note){if(!isset($nodes[$id]))throw new InvalidArgumentException('Kitap başlığı bulunamadı.');$str($note,6000);}
   $out['learning']['book']=['current'=>$current,'visited'=>$visited,'notes'=>(object)$notes];
  }
  if(array_key_exists('study',$learn)){
   $study=$learn['study'];if(!is_array($study))throw new InvalidArgumentException('Konu çalışması geçersiz.');
   $units=array_column(educationCourse()['tutorials']['units'],null,'id');$current=$study['current']??null;if(!is_string($current)||($current!==''&&!isset($units[$current])))throw new InvalidArgumentException('Konu bulunamadı.');
   $responses=$study['answers']??null;$notes=$study['notes']??null;if(!is_array($responses)||count($responses)>count($units)||!is_array($notes)||count($notes)>count($units))throw new InvalidArgumentException('Konu kayıtları geçersiz.');
   foreach($responses as $id=>$choice){if(!isset($units[$id]))throw new InvalidArgumentException('Konu sorusu bulunamadı.');$integer($choice,0,count($units[$id]['check']['options'])-1);}
   foreach($notes as $id=>$note){if(!isset($units[$id]))throw new InvalidArgumentException('Çalışma konusu bulunamadı.');$str($note,6000);}
   $out['learning']['study']=['current'=>$current,'answers'=>(object)$responses,'notes'=>(object)$notes];
  }

 }
 if(strlen(json_encode($out,JSON_UNESCAPED_UNICODE))>2500000)throw new InvalidArgumentException('Eğitim yedeği en fazla 2,5 MB olabilir.');return $out;
}
function educationAPI(PDO $db,string $action,string $method,array $input): never {
 if($action==='education-figure'&&$method==='GET'){
  $page=filter_var($_GET['page']??null,FILTER_VALIDATE_INT);if(!$page||!in_array($page,educationCourse()['outline']['figurePages'],true))fail('Kaynak görsel bulunamadı.',404);
  $path=__DIR__.'/../assets/data/education/figures/page-'.$page.'.jpg';if(!is_file($path))fail('Kaynak görsel bulunamadı.',404);
  header('Content-Type: image/jpeg');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');session_write_close();readfile($path);exit;
 }
 if($action==='education-book'&&$method==='GET'){$root=__DIR__.'/../assets/data/education/';$book=json_decode(file_get_contents($root.'catalog.json'),true,512,JSON_THROW_ON_ERROR);$book['course']=educationCourse();$book['pages']=[];for($i=1;$i<=8;$i++)$book['pages']=array_merge($book['pages'],json_decode(file_get_contents($root.'pages-'.$i.'.json'),true,512,JSON_THROW_ON_ERROR));respond($book);}
 $db->exec('CREATE TABLE IF NOT EXISTS education_workspace (id INTEGER PRIMARY KEY, payload MEDIUMTEXT NOT NULL, version INTEGER NOT NULL, updated_at VARCHAR(30) NOT NULL)');
 $q=$db->prepare($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO education_workspace VALUES(1,?,1,?)':'INSERT IGNORE INTO education_workspace VALUES(1,?,1,?)');$q->execute([json_encode(educationBlank()),gmdate('c')]);
 if($action==='education'&&$method==='GET'){$r=$db->query('SELECT * FROM education_workspace WHERE id=1')->fetch();respond(['data'=>json_decode($r['payload'],true),'version'=>(int)$r['version']]);}
 if($action==='education-save'&&$method==='POST'){
  try{$data=educationClean($input['data']??null);$old=json_decode($db->query('SELECT payload FROM education_workspace WHERE id=1')->fetchColumn(),true);if(!array_key_exists('learning',$data)){if(isset($old['learning']))$data['learning']=$old['learning'];}else{foreach(['study','book'] as $field)if(!array_key_exists($field,$data['learning'])&&isset($old['learning'][$field]))$data['learning'][$field]=$old['learning'][$field];}}catch(InvalidArgumentException $e){fail($e->getMessage());}
  if(!is_int($input['version']??null))fail('Sürüm bilgisi eksik.');
  $q=$db->prepare('UPDATE education_workspace SET payload=?,version=version+1,updated_at=? WHERE id=1 AND version=?');$q->execute([json_encode($data,JSON_UNESCAPED_UNICODE),gmdate('c'),$input['version']]);
  if(!$q->rowCount())fail('Eğitim alanı başka bir sekmede değişti. Taslağını indirip sunucu kaydını aç.',409);respond(['version'=>$input['version']+1]);
 }fail('İşlem bulunamadı.',404);
}
