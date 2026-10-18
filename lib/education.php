<?php
declare(strict_types=1);
function educationBlank(): array {return ['page'=>12,'read'=>[],'bookmarks'=>[],'notes'=>[],'cards'=>[],'font'=>18];}
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
 if(strlen(json_encode($out,JSON_UNESCAPED_UNICODE))>2500000)throw new InvalidArgumentException('Eğitim yedeği en fazla 2,5 MB olabilir.');return $out;
}
function educationAPI(PDO $db,string $action,string $method,array $input): never {
 if($action==='education-book'&&$method==='GET'){$root=__DIR__.'/../assets/data/education/';$book=json_decode(file_get_contents($root.'catalog.json'),true,512,JSON_THROW_ON_ERROR);$book['pages']=[];for($i=1;$i<=8;$i++)$book['pages']=array_merge($book['pages'],json_decode(file_get_contents($root.'pages-'.$i.'.json'),true,512,JSON_THROW_ON_ERROR));respond($book);}
 $db->exec('CREATE TABLE IF NOT EXISTS education_workspace (id INTEGER PRIMARY KEY, payload MEDIUMTEXT NOT NULL, version INTEGER NOT NULL, updated_at VARCHAR(30) NOT NULL)');
 $q=$db->prepare($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO education_workspace VALUES(1,?,1,?)':'INSERT IGNORE INTO education_workspace VALUES(1,?,1,?)');$q->execute([json_encode(educationBlank()),gmdate('c')]);
 if($action==='education'&&$method==='GET'){$r=$db->query('SELECT * FROM education_workspace WHERE id=1')->fetch();respond(['data'=>json_decode($r['payload'],true),'version'=>(int)$r['version']]);}
 if($action==='education-save'&&$method==='POST'){
  try{$data=educationClean($input['data']??null);}catch(InvalidArgumentException $e){fail($e->getMessage());}
  if(!is_int($input['version']??null))fail('Sürüm bilgisi eksik.');
  $q=$db->prepare('UPDATE education_workspace SET payload=?,version=version+1,updated_at=? WHERE id=1 AND version=?');$q->execute([json_encode($data,JSON_UNESCAPED_UNICODE),gmdate('c'),$input['version']]);
  if(!$q->rowCount())fail('Eğitim alanı başka bir sekmede değişti. Taslağını indirip sunucu kaydını aç.',409);respond(['version'=>$input['version']+1]);
 }fail('İşlem bulunamadı.',404);
}
