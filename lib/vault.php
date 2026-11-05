<?php
declare(strict_types=1);
// Called after the owner/session and CSRF checks in api.php. Only ciphertext is accepted.
function vaultAPI(PDO $db,string $method,array $input): never {
    $db->exec('CREATE TABLE IF NOT EXISTS password_vault (id INTEGER PRIMARY KEY, version INTEGER NOT NULL, envelope MEDIUMTEXT NOT NULL)');
    if ($method==='GET') {
        $row=$db->query('SELECT version,envelope FROM password_vault WHERE id=1')->fetch();
        respond(['version'=>$row?(int)$row['version']:0,'envelope'=>$row?json_decode($row['envelope'],true):null]);
    }
    $v=$input['version']??null; $e=$input['envelope']??null;
    if (!is_int($v)||$v<0||!is_array($e)||count($e)!==4||($e['format']??null)!==1) fail('Geçersiz kasa biçimi.');
    foreach (['salt'=>[16,16],'iv'=>[12,12],'data'=>[18,1000000]] as $k=>[$min,$max]) {
        $s=$e[$k]??null;
        if(!is_string($s)||strlen($s)>4*ceil($max/3)) fail('Geçersiz kasa boyutu.');
        $b=base64_decode($s,true);
        if($b===false||strlen($b)<$min||strlen($b)>$max||base64_encode($b)!==$s) fail('Geçersiz kasa verisi.');
    }
    $payload=json_encode($e,JSON_THROW_ON_ERROR);
    if ($v===0) {
        try {$db->prepare('INSERT INTO password_vault (id,version,envelope) VALUES(1,1,?)')->execute([$payload]);}
        catch(PDOException $ex){if($ex->getCode()!=='23000') throw $ex; fail('Kasa başka bir cihazda değişti. Kapatıp tekrar aç.',409);}
    } else {
        $s=$db->prepare('UPDATE password_vault SET version=version+1,envelope=? WHERE id=1 AND version=?');
        $s->execute([$payload,$v]);if($s->rowCount()!==1) fail('Kasa başka bir cihazda değişti. Kapatıp tekrar aç.',409);
    }
    respond(['version'=>$v+1]);
}
