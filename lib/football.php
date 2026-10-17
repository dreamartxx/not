<?php
declare(strict_types=1);
require_once __DIR__.'/football-bursa.php';
function footballTeams(): array {return [
 '83'=>['name'=>'Barcelona','short'=>'BAR'], '86'=>['name'=>'Real Madrid','short'=>'RMA'],
 '132'=>['name'=>'Bayern Münih','short'=>'BAY'], '160'=>['name'=>'Paris Saint-Germain','short'=>'PSG'],
 '382'=>['name'=>'Manchester City','short'=>'MCI'], '360'=>['name'=>'Manchester United','short'=>'MUN'],
 '1895'=>['name'=>'Beşiktaş','short'=>'BJK'], '810'=>['name'=>'Bursaspor','short'=>'BUR'], '465'=>['name'=>'Türkiye','short'=>'TUR']];}
function footballScore($score): ?string {if(is_array($score))$score=$score['displayValue']??$score['value']??null;return is_scalar($score)&&preg_match('/^\d{1,3}$/D',(string)$score)?(string)$score:null;}
function footballYouTube(string $url): string {
 if($url==='')return '';
 $p=parse_url($url);if(!$p||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port']))throw new InvalidArgumentException('Geçerli bir YouTube bağlantısı gir.');
 $host=strtolower($p['host']??'');$path=$p['path']??'';$id='';
 if($host==='youtu.be')$id=trim($path,'/');
 elseif(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','www.youtube-nocookie.com'],true)){
  if($path==='/watch'){parse_str($p['query']??'',$q);$id=$q['v']??'';}
  elseif(preg_match('~^/(?:embed|shorts|live)/([\w-]+)/*$~',$path,$m))$id=$m[1];
 }
 if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{11}$/D',$id))throw new InvalidArgumentException('Geçerli bir YouTube video bağlantısı gir.');
 return 'https://www.youtube.com/watch?v='.$id;
}
function footballNormalize(array $data): array {
 if(!isset($data['events'])||!is_array($data['events']))throw new RuntimeException('Fikstür biçimi değişti.');
 $out=[];$teams=footballTeams();
 foreach($data['events'] as $e){
  $c=$e['competitions'][0]??[];$home=null;$away=null;
  foreach($c['competitors']??[] as $v){if(($v['homeAway']??'')==='home')$home=$v;if(($v['homeAway']??'')==='away')$away=$v;}
  $id=(string)($e['id']??'');$date=$c['date']??$e['date']??'';
  if(!$home||!$away||!preg_match('/^\d{1,20}$/D',$id)||!strtotime($date))continue;
  $h=(string)$home['team']['id'];$a=(string)$away['team']['id'];if(!preg_match('/^\d{1,20}$/D',$h)||!preg_match('/^\d{1,20}$/D',$a))continue;if(!isset($teams[$h])&&!isset($teams[$a]))continue;
  $league=preg_replace('/^\d{4}(?:-\d{2})?\s*/','',$e['season']['displayName']??$e['league']['name']??$c['altGameNote']??'Futbol');
  $league=strtr($league,['Spanish LALIGA'=>'LaLiga','German Bundesliga'=>'Bundesliga','French Ligue 1'=>'Ligue 1','English Premier League'=>'Premier League','Turkish Super Lig'=>'Süper Lig','UEFA Champions League'=>'Şampiyonlar Ligi','UEFA Europa League'=>'Avrupa Ligi','UEFA Conference League'=>'Konferans Ligi','UEFA Nations League'=>'Uluslar Ligi','FIFA World Cup'=>'Dünya Kupası','International Friendly'=>'Hazırlık maçı','UEFA European Championship Qualifying'=>'Avrupa Şampiyonası Elemeleri','FIFA World Cup Qualifying - UEFA'=>'Dünya Kupası Elemeleri','World Cup Qualifying - UEFA'=>'Dünya Kupası Elemeleri']);
  $status=$c['status']['type']??$e['status']['type']??[];
  $out[]=['id'=>$id,'date'=>gmdate('c',strtotime($date)),'time_confirmed'=>($c['timeValid']??$e['timeValid']??false)===true,
   'home_id'=>$h,'away_id'=>$a,'home'=>$teams[$h]['name']??$home['team']['displayName'],'away'=>$teams[$a]['name']??$away['team']['displayName'],
   'league'=>$league,'competition'=>$e['league']['slug']??(str_contains($league,'Şampiyonlar Ligi')?'uefa.champions':''),'season'=>(int)($e['season']['year']??date('Y')),
   'home_score'=>footballScore($home['score']??null),'away_score'=>footballScore($away['score']??null),'clock'=>$c['status']['displayClock']??$e['status']['displayClock']??'','status_updated_at'=>gmdate('c'),
   'status'=>$status['name']??'STATUS_SCHEDULED','state'=>$status['state']??'pre',
   'source'=>'https://www.espn.com/soccer/match/_/gameId/'.$id,'channel'=>'','channel_source'=>''];
 }return $out;
}
function footballFold(string $s): string {return preg_replace('/[^a-z0-9]/','',strtr(mb_strtolower($s),['ı'=>'i','ş'=>'s','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c','é'=>'e','è'=>'e','í'=>'i','á'=>'a','ñ'=>'n']));}
function footballTeamIn(string $name,string $id): bool {
 $aliases=['83'=>['barcelona'],'86'=>['realmadrid'],'132'=>['bayernmunih','bayernmunich','bayernmunchen'],'160'=>['parissaintgermain','psg'],'382'=>['manchestercity'],'360'=>['manchesterunited'],'1895'=>['besiktas'],'810'=>['bursaspor'],'465'=>['turkiye','turkey']];
 foreach($aliases[$id]??[] as $alias)if(str_contains(footballFold($name),$alias))return true;return false;
}
function footballSSport(string $html): array {
 $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);$out=[];
 foreach($xpath->query('//a[contains(@href,"text/calendar;charset=utf8;base64,")]') as $a){
  $raw=base64_decode(explode('base64,',$a->getAttribute('href'),2)[1]??'',true);if(!$raw)continue;
  $raw=preg_replace('/\r?\n[ \t]/','',$raw);
  if(!preg_match('/DTSTART;TZID=Europe\/Istanbul:(\d{8}T\d{6})/',$raw,$dt)||!preg_match('/SUMMARY:([^\r\n]+)/',$raw,$title)||!preg_match('/DESCRIPTION:([^\r\n]+)/',$raw,$desc))continue;
  if(!preg_match('/(?:La Liga|Bundesliga|Futbol|Premier|Ligue)/iu',$desc[1])||preg_match('/(?:U19|Program|Bundesliga 2)/iu',$desc[1]))continue;
  if(!preg_match('/Platform\/Kanal\s*:\s*(.+)/u',$desc[1],$channel))continue;
  $date=DateTimeImmutable::createFromFormat('Ymd\THis',$dt[1],new DateTimeZone('Europe/Istanbul'));if(!$date)continue;
  $li=$a;while($li&&$li->nodeName!=='li')$li=$li->parentNode;$channels=[];if($li)foreach($li->getElementsByTagName('img') as $img){$alt=$img->getAttribute('alt');if($alt)$channels[]=$alt;}
  $out[]=['name'=>$title[1],'date'=>$date->format('c'),'channel'=>$channels?implode(' · ',array_unique($channels)):trim($channel[1]),'source'=>'https://www.ssportplus.com/yayin-akisi/'];
 }return $out;
}
function footballBein(string $html,string $source): array {
 $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);$text='';
 foreach($xpath->query('//script[@type="application/ld+json"]') as $script){$d=json_decode($script->textContent,true);if(isset($d['articleBody']))$text=$d['articleBody'];}
 for($i=0;$i<3;$i++)$text=html_entity_decode($text,ENT_QUOTES|ENT_HTML5,'UTF-8');
 $out=[];$day='';$football=false;
 foreach(preg_split('/\r?\n/',$text) as $line){$line=trim($line);if(!$line)continue;
  if(preg_match('/(?:SÜPER LİG|PREMIER LEAGUE|LIGUE 1)\s*-/u',$line))$football=true;
  elseif(preg_match('/(?:LİG|LIGUE|LEAGUE|BASKETBOL|STARLIGUE|WTA|PADEL|WORLD).*\(/u',$line)||str_contains($line,'BASKETBOL'))$football=false;
  if(preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/',$line,$m)){$day="$m[3]-$m[2]-$m[1]";continue;}
  if($football&&$day&&preg_match('/^(\d{2}:\d{2})\s*\|\s*(.+?)\s*\|\s*(.+)$/u',$line,$m))$out[]=['name'=>$m[2],'date'=>$day.'T'.$m[1].':00+03:00','channel'=>$m[3],'source'=>$source];
 }return $out;
}
function footballChannels(array $matches,array $guide): array {
 foreach($matches as &$m){if(!$m['time_confirmed'])continue;
  foreach($guide as $g){if(abs(strtotime($m['date'])-strtotime($g['date']))>900)continue;
   $pair=preg_split('/\s+[-–]\s+/u',$g['name']);if(count($pair)!==2)continue;
   $tracked=false;$valid=true;
   foreach([[$m['home_id'],$m['home'],$pair[0]],[$m['away_id'],$m['away'],$pair[1]]] as [$id,$name,$other]){
    if(isset(footballTeams()[$id])){$tracked=true;if(!footballTeamIn($other,$id))$valid=false;}
    else{$x=footballFold($name);$y=footballFold($other);if(!str_contains($x,$y)&&!str_contains($y,$x))$valid=false;}
   }
   if($tracked&&$valid){$m['channel']=$g['channel'];$m['channel_source']=$g['source'];break;}
  }
 }unset($m);return $matches;
}
// Fixed public provider hosts only; no user URL is ever fetched by the server.
function footballFetch(array $urls): array {
 if(!function_exists('curl_multi_init'))return [];$multi=curl_multi_init();$handles=[];$bodies=[];
 foreach($urls as $key=>$url){if(!in_array(parse_url($url,PHP_URL_HOST),['site.api.espn.com','www.ssportplus.com','www.beinsports.com.tr','www.bursaspor.org.tr'],true))continue;
  $bodies[$key]='';$h=curl_init($url);curl_setopt_array($h,[CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'NotPersonalWorkspace/1.0',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_ENCODING=>'',CURLOPT_WRITEFUNCTION=>function($ch,$data)use(&$bodies,$key){if(strlen($bodies[$key])+strlen($data)>8_000_000)return 0;$bodies[$key].=$data;return strlen($data);}]);curl_multi_add_handle($multi,$h);$handles[$key]=$h;
 }
 do{$code=curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,1);}while($running&&$code===CURLM_OK);
 $out=[];foreach($handles as $key=>$h){if(curl_getinfo($h,CURLINFO_HTTP_CODE)===200&&!curl_errno($h))$out[$key]=$bodies[$key];curl_multi_remove_handle($multi,$h);}curl_multi_close($multi);return $out;
}
function footballData(PDO $db,bool $refresh=false,bool $offline=false): array {
 $db->exec('CREATE TABLE IF NOT EXISTS football_cache (id INTEGER PRIMARY KEY, payload MEDIUMTEXT NOT NULL, updated_at BIGINT NOT NULL, attempted_at BIGINT NOT NULL)');
 $seed=json_decode(file_get_contents(__DIR__.'/football-seed.json'),true);$row=$db->query('SELECT * FROM football_cache WHERE id=1')->fetch();$data=$row?json_decode($row['payload'],true):$seed;$now=time();
 if(!$offline&&(!$row||(($data['schema']??0)<3&&$now-(int)$row['attempted_at']>60)||$now-(int)$row['attempted_at']>($refresh?60:1800))){
  // Reserve a refresh window before external I/O so other tabs reuse the cached snapshot.
  $stmt=$db->prepare($db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO football_cache VALUES(1,?,?,?) ON CONFLICT(id) DO UPDATE SET attempted_at=excluded.attempted_at':'INSERT INTO football_cache VALUES(1,?,?,?) ON DUPLICATE KEY UPDATE attempted_at=VALUES(attempted_at)');$stmt->execute([json_encode($data),$row['updated_at']??0,$now]);
  $urls=['ssport'=>'https://www.ssportplus.com/yayin-akisi/','bein-home'=>'https://www.beinsports.com.tr/'];foreach(footballTeams() as $id=>$team)$urls['team-'.$id]=(string)$id==='810'?'https://www.bursaspor.org.tr/fikstur':'https://site.api.espn.com/apis/site/v2/sports/soccer/all/teams/'.$id.'/schedule?fixture=true';
  $raw=footballFetch($urls);$fresh=[];$failed=[];$success=[];
  foreach(footballTeams() as $id=>$team){try{if((string)$id==='810'){$events=footballBursa($raw['team-'.$id]??'');}else{$parsed=json_decode($raw['team-'.$id]??'',true,512,JSON_THROW_ON_ERROR);if((string)($parsed['team']['id']??'')!==(string)$id)throw new RuntimeException('Wrong team');$events=footballNormalize($parsed);}$success[]=(string)$id;foreach($events as $e)$fresh[$e['id']]=$e;}catch(Throwable $e){$failed[]=(string)$id;}}
  // Keep previously seen matches for video attachments and recent history, but mark failed feeds.
  $videoIds=array_flip($db->query("SELECT match_id FROM football_videos WHERE url<>''")->fetchAll(PDO::FETCH_COLUMN));
  $all=[];foreach($data['matches']??[] as $m){if(strtotime($m['date'])>$now-90*86400||isset($videoIds[$m['id']])){if(strtotime($m['date'])>$now&&!isset($fresh[$m['id']])&&(in_array($m['home_id'],$success,true)||in_array($m['away_id'],$success,true))){$m['time_confirmed']=false;$m['status']='STATUS_UNCONFIRMED';$m['channel']='';$m['channel_source']='';}$all[$m['id']]=$m;}}
  foreach($fresh as $id=>$m)$all[$id]=$m;
  $guide=isset($raw['ssport'])?footballSSport($raw['ssport']):[];$sources=[];if($guide)$sources[]='S Sport';
  if(preg_match('~href="(/haber/bu-hafta-hangi-mac-hangi-kanalda(?:-\d+)?)"~',$raw['bein-home']??'',$m)){
   $url='https://www.beinsports.com.tr'.$m[1].'?amp=1';$article=footballFetch(['bein'=>$url]);$be=footballBein($article['bein']??'',$url);if($be){$guide=array_merge($guide,$be);$sources[]='beIN SPORTS';}
  }
  if($success){$data=['schema'=>3,'updated_at'=>gmdate('c'),'matches'=>footballChannels(array_values($all),$guide),'failed_teams'=>$failed,'channel_sources'=>$sources];$stmt=$db->prepare('UPDATE football_cache SET payload=?,updated_at=? WHERE id=1');$stmt->execute([json_encode($data,JSON_UNESCAPED_UNICODE),$now]);}else{$data['failed_teams']=array_map('strval',array_keys(footballTeams()));$db->prepare('UPDATE football_cache SET payload=? WHERE id=1')->execute([json_encode($data,JSON_UNESCAPED_UNICODE)]);}
 }
 $data['stale']=!empty($data['failed_teams'])||$now-strtotime($data['updated_at']??'1970-01-01')>7200;
 $data['cover']=$db->query('SELECT media_id,position FROM football_settings WHERE id=1')->fetch()?:['media_id'=>'','position'=>50];$data['teams']=footballTeams();$data['videos']=$db->query('SELECT match_id,url FROM football_videos')->fetchAll();
 $wanted=array_flip(array_column(array_filter($data['videos'],fn($v)=>$v['url']!==''),'match_id'));
 if($wanted){$db->exec('CREATE TABLE IF NOT EXISTS football_extra_cache (cache_key VARCHAR(80) PRIMARY KEY, payload MEDIUMTEXT NOT NULL, updated_at BIGINT NOT NULL, attempted_at BIGINT NOT NULL)');$known=array_flip(array_column($data['matches'],'id'));foreach($db->query('SELECT payload FROM football_extra_cache')->fetchAll(PDO::FETCH_COLUMN) as $payload)foreach(json_decode($payload,true)['matches']??[] as $m)if(isset($wanted[$m['id']])&&!isset($known[$m['id']])){$data['matches'][]=$m;$known[$m['id']]=true;}}
 return $data;
}
function footballAPI(PDO $db,string $action,string $method,array $input,array $config): void {
 $db->exec('CREATE TABLE IF NOT EXISTS football_videos (match_id VARCHAR(20) PRIMARY KEY, url VARCHAR(2048) NOT NULL, updated_at VARCHAR(30) NOT NULL)');
 require_once __DIR__.'/football-extra.php';
 footballExtraAPI($db,$action,$method,$input,$config);
 if(($action==='football'&&$method==='GET')||($action==='football-refresh'&&$method==='POST')){session_write_close();respond(footballData($db,$action==='football-refresh',!empty($config['football_offline'])));}
 if($action==='football-video'&&$method==='POST'){
  $id=$input['match_id']??'';$url=$input['url']??null;if(!is_string($id)||!preg_match('/^\d{1,20}$/D',$id)||!is_string($url)||strlen($url)>2048)fail('Geçersiz maç veya bağlantı.');
  try{$url=footballYouTube(trim($url));}catch(InvalidArgumentException $e){fail($e->getMessage());}
  $data=footballData($db,false,true);if(!footballKnownMatch($db,$id,$data['matches']))fail('Maç bulunamadı.',404);
  $sql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'INSERT INTO football_videos VALUES(?,?,?) ON CONFLICT(match_id) DO UPDATE SET url=excluded.url,updated_at=excluded.updated_at':'INSERT INTO football_videos VALUES(?,?,?) ON DUPLICATE KEY UPDATE url=VALUES(url),updated_at=VALUES(updated_at)';
  $db->prepare($sql)->execute([$id,$url,gmdate('c')]);respond(['ok'=>true,'url'=>$url]);
 }fail('İşlem bulunamadı.',404);
}
