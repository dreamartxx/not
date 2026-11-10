<?php
declare(strict_types=1);
// EPA CTX public API contracts: https://comptox.epa.gov/ctx-api/docs/ (2026-11-07).
function comptoxId(mixed $id): string {
 if(!is_string($id)||!preg_match('/^DTXSID[0-9]{7,12}$/D',$id))throw new InvalidArgumentException('Geçerli bir CompTox DTXSID kaydı seç.');
 return $id;
}
function comptoxKey(array $config): string {
 $key=trim((string)(getenv('COMPTOX_API_KEY')?:($config['comptox_api_key']??'')));
 if($key!==''&&!preg_match('/^[\x21-\x7E]{8,512}$/D',$key))throw new RuntimeException('CompTox sunucu anahtarı biçimi geçersiz.',503);
 return $key;
}
function comptoxFetch(string $path,string $key): array {
 if($key==='')throw new RuntimeException('CompTox bağlantısı için ücretsiz EPA API anahtarı gerekiyor. Sunucudaki özel EPA yapılandırmasını kontrol et.',503);
 if(!preg_match('~^/(chemical|hazard|exposure)/[a-z0-9/-]+/~',$path))throw new InvalidArgumentException('Geçersiz CompTox yolu.');
 $body='';$large=false;$ch=curl_init('https://comptox.epa.gov/ctx-api'.$path);
 curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>20,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_ENCODING=>'',CURLOPT_HTTPHEADER=>['Accept: application/json','x-api-key: '.$key],CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body,&$large){if(strlen($body)+strlen($chunk)>12_000_000){$large=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
 $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);unset($ch);
 // Never echo upstream bodies, headers or credentials into responses/logs.
 if(in_array($status,[401,403],true))throw new RuntimeException('EPA CompTox anahtarını kabul etmedi. Sunucudaki anahtarı kontrol et.',503);
 if($status===404||($status===400&&str_starts_with($path,'/chemical/search/equal/')))throw new RuntimeException('CompTox kaydı bulunamadı.',404);
 if($status===429)throw new RuntimeException('EPA sorgu sınırına ulaşıldı. Biraz sonra yeniden dene.',429);
 if($large||!$ok||$status!==200)throw new RuntimeException('EPA CompTox verileri şu anda alınamadı.',502);
 try{$data=json_decode($body,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new RuntimeException('EPA yanıtı okunamadı.',502);}
 if(!is_array($data))throw new RuntimeException('EPA veri biçimi doğrulanamadı.',502);
 return $data;
}
function comptoxRows(array $data): array {return array_is_list($data)?$data:[$data];}
function comptoxSearchRows(array $data): array {
 $rows=[];foreach(comptoxRows($data) as $r){if(!is_array($r))continue;try{$id=comptoxId($r['dtxsid']??null);}catch(InvalidArgumentException){continue;}
 $rows[$id]=['DTXSID'=>$id,'Title'=>(string)($r['preferredName']??''),'Title_tr'=>chemicalTurkishName((string)($r['preferredName']??'')),'CAS'=>chemicalCAS((string)($r['casrn']??''))?$r['casrn']:''];}
 if($data&&!$rows)throw new RuntimeException('CompTox arama kimlikleri doğrulanamadı.',502);
 return array_values($rows);
}
function comptoxText(array $row,array $labels): string {
 $out=[];foreach($labels as $key=>$label){$value=$row[$key]??null;if(is_scalar($value)&&(string)$value!=='')$out[]=$label.': '.(string)$value;}
 return implode("\n",$out);
}
function comptoxNormalize(string $id,array $detail,array $datasets,array $warnings=[]): array {
 comptoxId($id);$detail=comptoxRows($detail);
 if(count($detail)!==1||($detail[0]['dtxsid']??'')!==$id)throw new RuntimeException('CompTox madde kimliği eşleşmedi.',502);
 $d=$detail[0];$source='https://comptox.epa.gov/dashboard/chemical/details/'.$id;
 $r=['dtxsid'=>$id,'provider'=>'EPA CompTox','title'=>(string)($d['preferredName']??$id),'title_tr'=>chemicalTurkishName((string)($d['preferredName']??'')),'cas'=>chemicalCAS((string)($d['casrn']??''))?[$d['casrn']]:[],'formula'=>(string)($d['molFormula']??''),'mass'=>'','modified'=>'','source'=>$source,'sections'=>array_fill(1,16,[]),'warnings'=>$warnings];
 $put=function(int $section,string $heading,string $text,array $row=[],bool $predicted=false)use(&$r,$source){
  if($text==='')return;
  $url=chemicalURL($row['directUrl']??$row['publicSourceUrl']??'');
  $citations=[];foreach(['lsCitation','briefCitation','longRef','doi','lsDoi','year','originalYear'] as $key)if(is_scalar($row[$key]??null)&&$row[$key]!=='')$citations[]=$key.': '.$row[$key];
  $r['sections'][$section][]=['heading'=>$heading,'name'=>'','text'=>$text,'path'=>[$predicted?'Computed Properties':'EPA CompTox',$heading],'icons'=>[],'citations'=>$citations,'url'=>$url,'source'=>['name'=>'EPA CompTox'.(!empty($row['sourceName']??$row['source']??'')?' · '.($row['sourceName']??$row['source']):''),'url'=>$source,'license'=>'EPA açık verisi. Özgün çalışma ve değerlendirme koşulları esas alınır.','license_url'=>'']];
 };
 $put(1,'CAS',implode(', ',$r['cas']));$put(1,'Molecular Formula',$r['formula']);
 $put(1,'InChI Key',(string)($d['inchikey']??''));
 // Monoisotopic mass is not average molecular weight; do not populate the latter.
 if(isset($d['monoisotopicMass']))$put(9,'Monoisotopic Mass','Monoisotopic mass: '.$d['monoisotopicMass'].' Da');
 foreach($datasets as $kind=>$rows)foreach(comptoxRows($rows) as $row){
  if(!is_array($row)||($row['dtxsid']??'')!==$id)throw new RuntimeException('CompTox alt kaydının madde kimliği eşleşmedi.',502);
  if(in_array($kind,['experimental','predicted'],true)){
   $labels=['propName'=>'Property','propValue'=>'Value','propUnit'=>'Unit','propValueOriginal'=>'Original value','propValueText'=>'Value text','propValueString'=>'Value text','dataset'=>'Dataset','expDetailsTemperatureC'=>'Temperature (°C)','expDetailsPressureMmhg'=>'Pressure (mmHg)','expDetailsPh'=>'pH','expDetailsResponseSite'=>'Response site','expDetailsSpeciesLatin'=>'Species','expDetailsSpeciesCommon'=>'Species common name','modelName'=>'Model','propValueError'=>'Prediction error','adMethod'=>'Applicability method','adValue'=>'Applicability value','adConclusion'=>'Applicability conclusion','adReasoning'=>'Applicability reasoning','adMethodGlobal'=>'Global applicability method','adValueGlobal'=>'Global applicability value','adConclusionGlobal'=>'Global applicability conclusion','adReasoningGlobal'=>'Global applicability reasoning'];
   $name=(string)($row['propName']??'');$heading=ucwords(str_replace('_',' ',$name));
   $put(9,$heading,($kind==='predicted'?'Predicted value (not a measurement)':'Experimental value')."\n".comptoxText($row,$labels),$row,$kind==='predicted');
  }elseif($kind==='uses')$put(1,'Uses',comptoxText($row,['category'=>'Reported functional use','definition'=>'Definition']),$row);
  elseif($kind==='products')$put(1,'Uses',comptoxText($row,['displayPuc'=>'Product use category','pucKind'=>'Category kind','genCat'=>'General category','prodfam'=>'Product family','prodtype'=>'Product type','definition'=>'Definition','prodCount'=>'Product count']),$row);
  elseif($kind==='toxval'){
   // Keep all study context. Environmental exposure guidance is NOT an occupational limit.
   $labels=['toxvalType'=>'Value type','toxvalTypeDefinition'=>'Definition','toxvalSubtype'=>'Subtype','toxvalTypeSuperCategory'=>'Category','qualifier'=>'Qualifier','toxvalNumeric'=>'Value','toxvalUnits'=>'Unit','riskAssessmentClass'=>'Assessment class','humanEco'=>'Human or ecology','studyType'=>'Study type','studyDurationClass'=>'Duration class','studyDurationValue'=>'Duration','studyDurationUnits'=>'Duration unit','speciesCommon'=>'Species','latinName'=>'Species Latin name','speciesSupercategory'=>'Species category','strain'=>'Strain','sex'=>'Sex','generation'=>'Generation','lifestage'=>'Life stage','exposureRoute'=>'Exposure route','exposureMethod'=>'Exposure method','exposureForm'=>'Exposure form','media'=>'Medium','toxicologicalEffect'=>'Effect','experimentalRecord'=>'Experimental record','studyGroup'=>'Study group','source'=>'Source','subsource'=>'Subsource','toxvalNumericOriginal'=>'Original value','toxvalUnitsOriginal'=>'Original unit','toxvalTypeOriginal'=>'Original value type','toxvalSubtypeOriginal'=>'Original subtype','studyTypeOriginal'=>'Original study type','studyDurationClassOriginal'=>'Original duration class','studyDurationValueOriginal'=>'Original duration','studyDurationUnitsOriginal'=>'Original duration unit','speciesOriginal'=>'Original species','strainOriginal'=>'Original strain','sexOriginal'=>'Original sex','generationOriginal'=>'Original generation','lifestageOriginal'=>'Original life stage','exposureRouteOriginal'=>'Original route','exposureMethodOriginal'=>'Original method','exposureFormOriginal'=>'Original form','mediaOriginal'=>'Original medium','toxicologicalEffectOriginal'=>'Original effect'];
   $put(11,'Toxicity Data',comptoxText($row,$labels),$row);
  }
 }
 return $r;
}
function comptoxLoad(string $id,string $key,?callable $fetch=null): array {
 $fetch??=fn($path)=>comptoxFetch($path,$key);
 $detail=$fetch('/chemical/detail/search/by-dtxsid/'.$id.'?projection=chemicaldetailall');$sets=[];$warnings=[];
 $paths=['experimental'=>['Deneysel özellikler','/chemical/property/experimental/search/by-dtxsid/'],'predicted'=>['Hesaplanan özellikler','/chemical/property/predicted/search/by-dtxsid/'],'uses'=>['Kullanım alanları','/exposure/ccd/functional-use/search/by-dtxsid/'],'products'=>['Ürün kullanım kategorileri','/exposure/ccd/puc/search/by-dtxsid/'],'toxval'=>['Toksisite verileri','/hazard/toxval/search/by-dtxsid/']];
 foreach($paths as $kind=>[$label,$path]){try{$sets[$kind]=$fetch($path.$id);}catch(RuntimeException $e){if($e->getCode()===404){$sets[$kind]=[];continue;}throw new RuntimeException($label.' alınamadı. Eksik yanıt kaydedilmedi; yeniden dene.',in_array($e->getCode(),[429,503],true)?$e->getCode():502);}}
 return comptoxNormalize($id,$detail,$sets,$warnings);
}
function comptoxAPI(PDO $db,string $action,string $method,array $config): never {
 if($method!=='GET')fail('Desteklenmeyen yöntem.',405);
 try{
  if($action==='chemical-translate')fail('Eski çeviri servisi kaldırıldı. Modüldeki ücretsiz cihaz içi çeviriyi kullan.',410);
  $key=comptoxKey($config);
  if($action==='chemical-status')respond(['provider'=>'EPA CompTox','configured'=>$key!=='']);
  if($action==='chemical-search'){
   $query=chemicalQuery($_GET['q']??'');$resolved=chemicalResolve($query);$term=$resolved['term'];session_write_close();
   if($key==='')throw new RuntimeException('CompTox için ücretsiz EPA API anahtarı henüz eklenmedi. Sunucudaki özel EPA yapılandırmasını kontrol et.',503);
   $result=chemicalCached($db,'ctx-q:'.hash('sha256',$term),function()use($key,$term){$rows=comptoxSearchRows(comptoxFetch('/chemical/search/equal/'.rawurlencode($term),$key));return ['term'=>$term,'total'=>count($rows),'compounds'=>array_slice($rows,0,12)];});
   respond([...$result,'query'=>$query,'resolved_name'=>$resolved['label'],'suggestions'=>$result['total']?[]:chemicalSuggestions($query)]);
  }
  $id=comptoxId($_GET['dtxsid']??null);session_write_close();
  $record=chemicalCached($db,'comptox-v1:'.$id,fn()=>comptoxLoad($id,$key));
  respond(chemicalLocalStored($db,$record));
 }catch(InvalidArgumentException $e){fail($e->getMessage());}catch(Throwable $e){$code=in_array($e->getCode(),[404,410,429,502,503],true)?$e->getCode():502;fail($code===502&&$e->getCode()!==502?'CompTox verileri alınamadı.':$e->getMessage(),$code);}
}
