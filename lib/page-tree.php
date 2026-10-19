<?php
// Separate ordering metadata keeps existing note bodies and backups compatible.
$db->exec('CREATE TABLE IF NOT EXISTS page_order (page_id VARCHAR(40) PRIMARY KEY, position INTEGER NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS page_tree_guard (id INTEGER PRIMARY KEY, revision INTEGER NOT NULL)');
$db->exec($sqlite?'INSERT OR IGNORE INTO page_tree_guard VALUES (1,0)':'INSERT IGNORE INTO page_tree_guard VALUES (1,0)');
function beginPageWrite(PDO $db): void {
 $db->beginTransaction();
 // Serialize hierarchy changes, including legacy save/create/trash operations.
 $db->exec('UPDATE page_tree_guard SET revision=revision+1 WHERE id=1');
 $missing=$db->query('SELECT p.id FROM pages p LEFT JOIN page_order o ON o.page_id=p.id WHERE o.page_id IS NULL ORDER BY p.created_at,p.id')->fetchAll();
 foreach($missing as $p)appendPageOrder($db,$p['id']);
}
function appendPageOrder(PDO $db,string $id): void {
 $next=(int)$db->query('SELECT COALESCE(MAX(position),0)+1 FROM page_order')->fetchColumn();
 $db->prepare('INSERT INTO page_order (page_id,position) VALUES (?,?)')->execute([$id,$next]);
}
function pageTreeSnapshot(array $pages): array {
 return array_values(array_map(fn($p)=>['id'=>$p['id'],'parent_id'=>$p['parent_id']],array_filter($pages,fn($p)=>!$p['deleted_at'])));
}
function movePageTree(PDO $db,array $input): never {
 $id=$input['id']??null;$target=$input['target_id']??null;$place=$input['placement']??null;$expected=$input['tree']??null;
 if(!is_string($id)||($target!==null&&!is_string($target))||!in_array($place,['before','after','inside','root'],true)||!is_array($expected)||!array_is_list($expected))fail('Geçersiz taşıma isteği.');
 if(($place==='root')!==($target===null)||$id===$target)fail('Geçersiz bırakma konumu.');
 beginPageWrite($db);
 $pages=allPages($db);$actual=pageTreeSnapshot($pages);
 if($actual!==$expected)fail('Sayfa düzeni başka bir yerde değişti. Sayfayı yenileyip tekrar taşı.',409);
 $byId=[];foreach($pages as $p)if(!$p['deleted_at'])$byId[$p['id']]=$p;
 if(!isset($byId[$id])||($target!==null&&!isset($byId[$target])))fail('Taşınacak sayfa bulunamadı.',404);
 if(!is_int($input['version']??null)||(int)$byId[$id]['version']!==$input['version'])fail('Bu sayfa başka bir yerde değişti. Yenileyip tekrar taşı.',409);
 $parent=$place==='root'?null:($place==='inside'?$target:$byId[$target]['parent_id']);
 validParent($db,$parent,$id);
 $ids=array_column($actual,'id');$ids=array_values(array_filter($ids,fn($x)=>$x!==$id));
 $at=in_array($place,['root','inside'],true)?count($ids):array_search($target,$ids,true)+($place==='after'?1:0);
 array_splice($ids,$at,0,[$id]);
 $db->prepare('UPDATE pages SET parent_id=?,version=version+1,updated_at=? WHERE id=?')->execute([$parent,gmdate('c'),$id]);
 $order=$db->prepare('UPDATE page_order SET position=? WHERE page_id=?');foreach($ids as $i=>$pid)$order->execute([$i+1,$pid]);
 // Trashed pages remain outside the active order and can later be restored.
 $tree=array_map(fn($p)=>['id'=>$p['id'],'parent_id'=>$p['parent_id'],'version'=>(int)$p['version'],'updated_at'=>$p['updated_at']],allPages($db));
 $db->commit();respond(['tree'=>$tree]);
}
