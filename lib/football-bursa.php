<?php
declare(strict_types=1);

// The club publishes its current fixture list even when ESPN has no coverage.
function footballBursa(string $html): array {
 $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xp=new DOMXPath($doc);
 $cls=fn(string $name)=>'contains(concat(" ",normalize-space(@class)," ")," '.$name.' ")';
 $months=['Ocak'=>1,'Şubat'=>2,'Mart'=>3,'Nisan'=>4,'Mayıs'=>5,'Haziran'=>6,'Temmuz'=>7,'Ağustos'=>8,'Eylül'=>9,'Ekim'=>10,'Kasım'=>11,'Aralık'=>12];$matches=[];
 foreach($xp->query('//*['.$cls('fikstur-item').']') as $item){
  $value=fn(string $class)=>trim($xp->evaluate('string(.//*['.$cls($class).'][1])',$item));
  if(!preg_match('/^(\d{1,2})\s+(\S+)\s+(\d{4})$/u',$value('fikstur-date'),$d)||!isset($months[$d[2]])||!checkdate($months[$d[2]],(int)$d[1],(int)$d[3]))continue;
  $names=$xp->query('.//*['.$cls('fikstur-team-title').']',$item);if($names->length!==2)continue;
  $home=trim($names->item(0)->textContent);$away=trim($names->item(1)->textContent);
  if($home!=='Bursaspor'&&$away!=='Bursaspor')continue;
  $time=$value('fikstur-time');$confirmed=preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)&&$time!=='00:00';
  $date=new DateTimeImmutable(sprintf('%04d-%02d-%02d ',(int)$d[3],$months[$d[2]],(int)$d[1]).($confirmed?$time:'12:00'),new DateTimeZone('Europe/Istanbul'));
  $season=(int)$d[3]-($months[$d[2]]<7?1:0);
  // Stable across kickoff/date changes, so attached YouTube videos remain linked.
  $id='90'.base_convert(substr(hash('sha256',$season.'|'.$home.'|'.$away),0,13),16,10);
  $teamId=fn(string $name)=>$name==='Bursaspor'?'810':'91'.base_convert(substr(hash('sha256',$name),0,13),16,10);
  $scores=$xp->query('.//*['.$cls('score').']',$item);$hs=$scores->length===2?footballScore(trim($scores->item(0)->textContent)):null;$as=$scores->length===2?footballScore(trim($scores->item(1)->textContent)):null;
  // This source has no live-state field. Never infer live status from kickoff.
  $finished=$hs!==null&&$as!==null&&$date->getTimestamp()<time()-4*3600;
  $matches[$id]=['id'=>$id,'date'=>$date->setTimezone(new DateTimeZone('UTC'))->format('c'),'time_confirmed'=>(bool)$confirmed,
   'home_id'=>$teamId($home),'away_id'=>$teamId($away),'home'=>$home,'away'=>$away,'league'=>'Resmî kulüp fikstürü','competition'=>'','season'=>$season,
   'home_score'=>$finished?$hs:null,'away_score'=>$finished?$as:null,'clock'=>'','status_updated_at'=>gmdate('c'),
   'status'=>$finished?'STATUS_FULL_TIME':'STATUS_SCHEDULED','state'=>$finished?'post':'pre',
   'source'=>'https://www.bursaspor.org.tr/fikstur','channel'=>'','channel_source'=>''];
 }
 if(!$matches)throw new RuntimeException('Bursaspor fikstürü şu anda okunamıyor.');
 return array_values($matches);
}
