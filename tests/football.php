<?php
require __DIR__.'/../lib/football.php';
function check($condition){if(!$condition)throw new RuntimeException('Football assertion failed');}
check(footballYouTube('https://youtu.be/dQw4w9WgXcQ')==='https://www.youtube.com/watch?v=dQw4w9WgXcQ');
check(footballYouTube('https://www.youtube.com/shorts/dQw4w9WgXcQ')==='https://www.youtube.com/watch?v=dQw4w9WgXcQ');
foreach(['http://youtube.com/watch?v=dQw4w9WgXcQ','https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ','https://user:pass@youtube.com/watch?v=dQw4w9WgXcQ','https://youtube.com/watch?v[]=123','javascript:alert(1)'] as $bad){try{footballYouTube($bad);throw new RuntimeException('Unsafe URL accepted');}catch(InvalidArgumentException $e){}}
$event=['id'=>'123','date'=>'2026-10-17T16:30Z','timeValid'=>false,'season'=>['displayName'=>'2026-27 Spanish LALIGA'],'competitions'=>[['competitors'=>[['homeAway'=>'home','team'=>['id'=>'244','displayName'=>'Real Betis']],['homeAway'=>'away','team'=>['id'=>'83','displayName'=>'Barcelona']]]]]];
$m=footballNormalize(['events'=>[$event]]);check(count($m)===1&&!$m[0]['time_confirmed']);
$guide=[['name'=>'Real Betis – Barcelona','date'=>'2026-10-17T19:30:00+03:00','channel'=>'S Sport','source'=>'https://www.ssportplus.com/yayin-akisi/']];
check(footballChannels($m,$guide)[0]['channel']==='');$m[0]['time_confirmed']=true;check(footballChannels($m,$guide)[0]['channel']==='S Sport');
$guide[0]['name']='Valencia – Barcelona';check(footballChannels($m,$guide)[0]['channel']==='');$guide[0]['name']='Real Betis – Barcelona';$guide[0]['date']='2026-10-18T19:30:00+03:00';check(footballChannels($m,$guide)[0]['channel']==='');
$body="PREMIER LEAGUE - (6. HAFTA)\n17.10.2026 CUMARTESİ\n17:00 | MANCHESTER CITY - IPSWICH TOWN | beIN SPORTS 3\nTÜRKİYE SİGORTA BASKETBOL SÜPER LİGİ - (4. HAFTA)\n17.10.2026 CUMARTESİ\n20:00 | BEŞİKTAŞ - GALATASARAY | beIN SPORTS 5";
$parsed=footballBein('<script type="application/ld+json">'.json_encode(['articleBody'=>$body]).'</script>','https://www.beinsports.com.tr/');check(count($parsed)===1);
echo "PASS: football normalization, YouTube validation, broadcast team/date matching and sport separation.\n";
require __DIR__.'/../lib/football-extra.php';
check(footballScore(['displayValue'=>'3'])==='3'&&footballScore('0')==='0'&&footballScore('<img>')===null);
$event['season']['year']=2026;$event['league']=['slug'=>'uefa.champions'];$event['competitions'][0]['status']=['type'=>['state'=>'post','name'=>'STATUS_FULL_TIME'],'displayClock'=>"90'"];$event['competitions'][0]['competitors'][0]['score']=['displayValue'=>'1'];$event['competitions'][0]['competitors'][1]['score']='4';
$m=footballNormalize(['events'=>[$event]])[0];check($m['home_score']==='1'&&$m['away_score']==='4'&&$m['state']==='post'&&$m['competition']==='uefa.champions'&&$m['season']===2026);
$tables=footballTables(['children'=>[['name'=>'League A','children'=>[['name'=>'Group 1','standings'=>['entries'=>[['team'=>['id'=>'465','displayName'=>'Turkey'],'stats'=>[['name'=>'points','displayValue'=>'6'],['name'=>'rank','value'=>2]]]]]]]]]]);
check($tables[0]['name']==='League A · 1 Grubu'&&$tables[0]['rows'][0]['name']==='Türkiye'&&$tables[0]['rows'][0]['points']==='6'&&$tables[0]['rows'][0]['gamesPlayed']==='—');
echo "PASS: scores, competition identity, final match state, nested standings and missing statistics.\n";
