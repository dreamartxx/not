<?php
declare(strict_types=1);
function dndSchema(): array {
 $str=fn(int $max=120)=>['string',$max];$num=fn(int $min=0,int $max=9999)=>['int',$min,$max];$list=fn($type,int $max)=>['list',$type,$max];$bool=['bool'];$id=['id'];
 $item=['id'=>$id,'name'=>$str(),'quantity'=>$num(0,9999),'weight'=>$num(0,9999),'equipped'=>$bool,'notes'=>$str(4000)];
 $character=['id'=>$id,'name'=>$str(),'race'=>$str(),'class'=>$str(),'subclass'=>$str(),'background'=>$str(),'alignment'=>$str(),'level'=>$num(1,20),'xp'=>$num(0,9999999),'abilities'=>['fixed',$num(1,30),6],'skills'=>['fixed',$num(0,2),18],'saves'=>['fixed',$bool,6],
  'hp'=>$num(),'maxHp'=>$num(1),'tempHp'=>$num(),'ac'=>$num(0,99),'speed'=>$num(0,999),'initiativeBonus'=>$num(-30,30),'spellAbility'=>['enum',['str','dex','con','int','wis','cha']],'deathSuccess'=>$num(0,3),'deathFailure'=>$num(0,3),'hitDice'=>$num(0,20),'hitDie'=>['enum',['d6','d8','d10','d12']],'inspiration'=>$bool,'exhaustion'=>$num(0,6),'conditions'=>$list($str(60),20),'concentration'=>$str(160),
  'slots'=>['fixed',['max'=>$num(0,20),'current'=>$num(0,20)],9],'known'=>$list($str(120),600),'prepared'=>$list($str(120),600),'inventory'=>$list($item,200),'coins'=>['cp'=>$num(0,9999999),'sp'=>$num(0,9999999),'ep'=>$num(0,9999999),'gp'=>$num(0,9999999),'pp'=>$num(0,9999999)],
  'attacks'=>$list(['id'=>$id,'name'=>$str(),'bonus'=>$num(-30,50),'damage'=>$str(80),'notes'=>$str(2000)],40),'resources'=>$list(['id'=>$id,'name'=>$str(),'current'=>$num(),'max'=>$num(),'reset'=>['enum',['short','long','manual']]],40),'biography'=>$str(30000),'features'=>$str(30000),'allies'=>$str(12000)];
 $spell=['id'=>$id,'name'=>$str(),'level'=>$num(0,9),'school'=>$str(80),'casting'=>$str(),'range'=>$str(),'components'=>$str(),'duration'=>$str(),'ritual'=>$bool,'concentration'=>$bool,'description'=>$str(20000),'higher'=>$str(6000)];
 return ['characters'=>$list($character,12),'selected'=>$str(40),'journal'=>$list(['id'=>$id,'title'=>$str(200),'date'=>['date'],'campaign'=>$str(),'text'=>$str(50000),'tags'=>$str(200)],200),
  'quests'=>$list(['id'=>$id,'title'=>$str(200),'detail'=>$str(10000),'status'=>['enum',['active','done','paused']]],200),'npcs'=>$list(['id'=>$id,'name'=>$str(),'role'=>$str(),'location'=>$str(),'notes'=>$str(10000)],200),'customSpells'=>$list($spell,200),
  'rolls'=>$list(['id'=>$id,'label'=>$str(160),'detail'=>$str(300),'total'=>$num(-9999,99999),'time'=>$str(40)],80)];
}
function dndValidate($v,$spec,string $path='D&D') {
 if(!array_is_list($spec)){
  if(!is_array($v))throw new InvalidArgumentException($path.' bilgisi geçersiz.');$out=[];
  foreach($spec as $k=>$rule){if(!array_key_exists($k,$v))throw new InvalidArgumentException($path.' · '.$k.' eksik.');$out[$k]=dndValidate($v[$k],$rule,$path.' · '.$k);}return $out;
 }
 $type=$spec[0];$ok=match($type){'string'=>is_string($v)&&mb_strlen($v)<=$spec[1],'id'=>is_string($v)&&preg_match('/^[a-zA-Z0-9-]{8,40}$/D',$v),'int'=>is_int($v)&&$v>=$spec[1]&&$v<=$spec[2],'bool'=>is_bool($v),'enum'=>in_array($v,$spec[1],true),'date'=>is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)&&($stamp=strtotime($v))!==false&&date('Y-m-d',$stamp)===$v,'list','fixed'=>is_array($v)&&array_is_list($v)&&($type==='fixed'?count($v)===$spec[2]:count($v)<=$spec[2]),default=>false};
 if(!$ok)throw new InvalidArgumentException($path.' alanını kontrol et.');
 if($type==='list'||$type==='fixed'){$out=[];$ids=[];foreach($v as $i=>$item){$clean=dndValidate($item,$spec[1],$path.'['.$i.']');if(is_array($clean)&&isset($clean['id'])){if(isset($ids[$clean['id']]))throw new InvalidArgumentException('Tekrarlanan kayıt kimliği.');$ids[$clean['id']]=true;}$out[]=$clean;}return $out;}return $v;
}
function dndBlank(): array {return ['characters'=>[],'selected'=>'','journal'=>[],'quests'=>[],'npcs'=>[],'customSpells'=>[],'rolls'=>[]];}
function dndClean($input): array {
 $d=dndValidate($input,dndSchema());if($d['selected']!==''&&!in_array($d['selected'],array_column($d['characters'],'id'),true))throw new InvalidArgumentException('Seçili karakter bulunamadı.');
 foreach($d['characters'] as $c){if($c['hp']>$c['maxHp']||$c['hitDice']>$c['level'])throw new InvalidArgumentException('Can veya can zarı üst sınırı aşıldı.');foreach($c['slots'] as $s)if($s['current']>$s['max'])throw new InvalidArgumentException('Büyü yuvası üst sınırı aşıldı.');foreach($c['resources'] as $r)if($r['current']>$r['max'])throw new InvalidArgumentException('Kaynak üst sınırı aşıldı.');}
 if(strlen(json_encode($d))>2_500_000)throw new InvalidArgumentException('D&D yedeği en fazla 2,5 MB olabilir.');return $d;
}
function dndAPI(PDO $db,string $action,string $method,array $input): never {
 if($action==='dnd-spells'&&$method==='GET')respond(json_decode(file_get_contents(__DIR__.'/../assets/data/dnd-spells-2014.json'),true,512,JSON_THROW_ON_ERROR));
 $db->exec('CREATE TABLE IF NOT EXISTS dnd_workspace (id INTEGER PRIMARY KEY, payload MEDIUMTEXT NOT NULL, version INTEGER NOT NULL, updated_at VARCHAR(30) NOT NULL)');
 $q=$db->prepare($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT OR IGNORE INTO dnd_workspace VALUES(1,?,1,?)':'INSERT IGNORE INTO dnd_workspace VALUES(1,?,1,?)');$q->execute([json_encode(dndBlank()),gmdate('c')]);
 if($action==='dnd'&&$method==='GET'){$r=$db->query('SELECT * FROM dnd_workspace WHERE id=1')->fetch();respond(['data'=>json_decode($r['payload'],true),'version'=>(int)$r['version'],'updated_at'=>$r['updated_at']]);}
 if($action==='dnd-save'&&$method==='POST'){
  try{$data=dndClean($input['data']??null);}catch(InvalidArgumentException $e){fail($e->getMessage());}
  if(!is_int($input['version']??null))fail('Sürüm bilgisi eksik.');
  $now=gmdate('c');$q=$db->prepare('UPDATE dnd_workspace SET payload=?,version=version+1,updated_at=? WHERE id=1 AND version=?');$q->execute([json_encode($data,JSON_UNESCAPED_UNICODE),$now,$input['version']]);
  if(!$q->rowCount())fail('D&D alanı başka bir sekmede değişti. Taslağını indir; ardından sunucudaki kaydı aç.',409);
  respond(['version'=>$input['version']+1,'updated_at'=>$now]);
 }fail('İşlem bulunamadı.',404);
}
