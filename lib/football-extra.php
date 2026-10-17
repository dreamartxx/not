<?php
declare(strict_types=1);
function footballLeagues(): array {return ['esp.1'=>'LaLiga','eng.1'=>'Premier League','ger.1'=>'Bundesliga','fra.1'=>'Ligue 1','tur.1'=>'Süper Lig','tur.2'=>'1. Lig','uefa.champions'=>'Şampiyonlar Ligi','uefa.nations'=>'Uluslar Ligi'];}
function footballSeason(): int {return (int)date('Y')-((int)date('n')<7?1:0);}
function footballTables(array $data): array {
 $tables=[];
 $walk=function(array $node,string $path='')use(&$walk,&$tables){
  $name=trim($path.' · '.($node['name']??''),' ·');
  if(!empty($node['standings']['entries'])){
   $rows=[];foreach($node['standings']['entries'] as $entry){$team=$entry['team']??[];$id=(string)($team['id']??'');if(!preg_match('/^\d{1,20}$/D',$id))continue;$stats=[];foreach($entry['stats']??[] as $stat)$stats[$stat['name']]=$stat['displayValue']??$stat['value']??'—';
    $row=['id'=>$id,'name'=>footballTeams()[$id]['name']??$team['displayName']??'Takım'];foreach(['rank','gamesPlayed','wins','ties','losses','pointsFor','pointsAgainst','pointDifferential','points'] as $key)$row[$key]=(string)($stats[$key]??'—');$rows[]=$row;
   }
   usort($rows,fn($a,$b)=>(is_numeric($a['rank'])?(int)$a['rank']:999)<=>(is_numeric($b['rank'])?(int)$b['rank']:999));
   $name=preg_replace('/Group ([A-Z]?[0-9]+)/','$1 Grubu',$name);$name=strtr($name,['League Phase'=>'Lig aşaması','Overall'=>'Genel']);$tables[]=['name'=>$name,'rows'=>$rows];
  }
  foreach($node['children']??[] as $child)$walk($child,$name);
 };
 // Preserve group labels (including Nations League tiers) without repeating the competition name.
 foreach($data['children']??[] as $child){$walk($child);}
 if(!$tables&&isset($data['standings']))$walk($data);
 return $tables;
}
function footballExtraCache(PDO $db,string $key,int $ttl,bool $offline,callable $loader): array {
 $db->exec('CREATE TABLE IF NOT EXISTS football_extra_cache (cache_key VARCHAR(80) PRIMARY KEY, payload MEDIUMTEXT NOT NULL, updated_at BIGINT NOT NULL, attempted_at BIGINT NOT NULL)');
 $q=$db->prepare('SELECT * FROM football_extra_cache WHERE cache_key=?');$q->execute([$key]);$row=$q->fetch();$now=time();
 $data=$row?json_decode($row['payload'],true):['matches'=>[],'tables'=>[],'updated_at'=>null];
 if(!$offline&&(!$row||$now-(int)$row['attempted_at']>$ttl)){
  $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO football_extra_cache VALUES(?,?,?,?) ON CONFLICT(cache_key) DO UPDATE SET attempted_at=excluded.attempted_at':'INSERT INTO football_extra_cache VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE attempted_at=VALUES(attempted_at)';
  $db->prepare($sql)->execute([$key,json_encode($data),$row['updated_at']??0,$now]);
  try{$fresh=$loader();if(str_starts_with($key,'history-')){$merged=array_column($data['matches']??[],null,'id');foreach($fresh['matches']??[] as $m)$merged[$m['id']]=$m;$fresh['matches']=array_values($merged);}$fresh['updated_at']=gmdate('c');$data=$fresh;$db->prepare('UPDATE football_extra_cache SET payload=?,updated_at=? WHERE cache_key=?')->execute([json_encode($data,JSON_UNESCAPED_UNICODE),$now,$key]);}catch(Throwable $e){$data['unavailable']=true;}
 }
 $data['stale']=!empty($data['unavailable'])||empty($data['updated_at'])||$now-strtotime($data['updated_at'])>$ttl*2;
 return $data;
}
function footballKnownMatch(PDO $db,string $id,array $matches): bool {
 if(in_array($id,array_map('strval',array_column($matches,'id')),true))return true;
 $db->exec('CREATE TABLE IF NOT EXISTS football_extra_cache (cache_key VARCHAR(80) PRIMARY KEY, payload MEDIUMTEXT NOT NULL, updated_at BIGINT NOT NULL, attempted_at BIGINT NOT NULL)');
 foreach($db->query('SELECT payload FROM football_extra_cache')->fetchAll(PDO::FETCH_COLUMN) as $payload){$d=json_decode($payload,true);if(in_array($id,array_map('strval',array_column($d['matches']??[],'id')),true))return true;}return false;
}
function footballHistory(PDO $db,int $year,bool $offline): array {
 return footballExtraCache($db,'history-v3-'.$year,$year===footballSeason()?1800:43200,$offline,function()use($year){
  $urls=[];foreach(footballTeams() as $id=>$team)$urls[$id]='https://site.api.espn.com/apis/site/v2/sports/soccer/all/teams/'.$id.'/schedule?season='.$year;
  $urls['bursa']='https://www.bursaspor.org.tr/fikstur';
  $raw=footballFetch($urls);$matches=[];$failed=[];
  foreach(footballTeams() as $id=>$team){try{$d=json_decode($raw[$id]??'',true,512,JSON_THROW_ON_ERROR);if((string)($d['team']['id']??'')!==(string)$id)throw new RuntimeException();foreach(footballNormalize($d) as $m){if((string)$id==='465'?(int)substr($m['date'],0,4)!==$year:$m['season']!==$year)continue;$matches[$m['id']]=$m;}}catch(Throwable $e){$failed[]=(string)$id;}}
  try{foreach(footballBursa($raw['bursa']??'') as $m)if($m['season']===$year)$matches[$m['id']]=$m;$failed=array_values(array_diff($failed,['810']));}catch(Throwable $e){if(!isset($raw[810])&&!in_array('810',$failed,true))$failed[]='810';}
  if(count($failed)===count(footballTeams()))throw new RuntimeException('History unavailable');
  return ['matches'=>array_values($matches),'failed_teams'=>$failed,'season'=>$year];
 });
}
function footballExtraAPI(PDO $db,string $action,string $method,array $input,array $config): void {
 $offline=!empty($config['football_offline']);
 if($action==='football-live'&&$method==='GET'){
  session_write_close();$day=gmdate('Ymd');respond(footballExtraCache($db,'live',60,$offline,function(){
   // Include yesterday for fixtures that cross midnight in Turkey.
   $urls=[];foreach([0,86400] as $offset)$urls[(string)$offset]='https://site.api.espn.com/apis/site/v2/sports/soccer/all/scoreboard?dates='.gmdate('Ymd',time()-$offset).'&limit=1000';
   $raw=footballFetch($urls);$matches=[];if(!isset($raw[0]))throw new RuntimeException('Live data unavailable');foreach($raw as $body){$d=json_decode($body,true,512,JSON_THROW_ON_ERROR);foreach(footballNormalize($d) as $m)$matches[$m['id']]=$m;}return ['matches'=>array_values($matches)];
  }));
 }
 if($action==='football-history'&&$method==='GET'){
  $year=filter_var($_GET['season']??footballSeason(),FILTER_VALIDATE_INT);if($year===false||$year<2000||$year>footballSeason())fail('Geçersiz sezon.');session_write_close();respond(footballHistory($db,$year,$offline));
 }
 if($action==='football-standings'&&$method==='GET'){
  $league=$_GET['league']??'esp.1';if(!is_string($league)||!isset(footballLeagues()[$league]))fail('Geçersiz lig.');session_write_close();
  respond(footballExtraCache($db,'table-v2-'.$league,900,$offline,function()use($league){$raw=footballFetch(['table'=>'https://site.api.espn.com/apis/v2/sports/soccer/'.$league.'/standings']);$d=json_decode($raw['table']??'',true,512,JSON_THROW_ON_ERROR);$tables=footballTables($d);if(!$tables)throw new RuntimeException('Standings unavailable');return ['tables'=>$tables,'season'=>$d['season']['displayName']??'','source'=>'https://www.espn.com/soccer/standings/_/league/'.$league];}));
 }
 if($action==='football-cover'&&$method==='POST'){
  $id=$input['media_id']??null;$position=$input['position']??50;
  if(!is_string($id)||($id!==''&&!preg_match('/^[a-f0-9]{32}$/D',$id))||!is_int($position)||$position<0||$position>100)fail('Geçersiz kapak bilgisi.');
  if($id!==''){$q=$db->prepare('SELECT mime FROM media WHERE id=?');$q->execute([$id]);if(!in_array($q->fetchColumn(),['image/jpeg','image/png','image/webp','image/gif'],true))fail('Kütüphanendeki bir görseli seç.');}
  $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO football_settings VALUES(1,?,?) ON CONFLICT(id) DO UPDATE SET media_id=excluded.media_id,position=excluded.position':'INSERT INTO football_settings VALUES(1,?,?) ON DUPLICATE KEY UPDATE media_id=VALUES(media_id),position=VALUES(position)';
  $db->prepare($sql)->execute([$id,$position]);respond(['cover'=>['media_id'=>$id,'position'=>$position]]);
 }
}
