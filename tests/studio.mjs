import assert from 'node:assert/strict';
import {videoSource,safeURL,defaults,blockText} from '../assets/studio.js';
assert.equal(safeURL('javascript:alert(1)'), '');
assert.equal(safeURL('https://user:secret@example.com/a'), '');
assert.equal(videoSource('https://youtu.be/dQw4w9WgXcQ?t=1m30s'),'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?start=90');
assert.equal(videoSource('https://www.youtube.com/shorts/dQw4w9WgXcQ'),'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
assert.equal(videoSource('https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'),'');
assert.equal(videoSource('https://vimeo.com/123456'),'https://player.vimeo.com/video/123456');
assert.equal(blockText({text:'Not',rows:[['Ölçüm','Sonuç']],objects:[{text:'Fikir'}],columns:[{title:'Plan',cards:['İş']}]}),'Not Ölçüm Sonuç Plan İş Fikir');
let id=0;const d=defaults('diagram',()=>String(++id));const nodes=d.objects.filter(o=>o.kind!=='arrow');assert.equal(nodes.length,3);for(const edge of d.objects.filter(o=>o.kind==='arrow')){assert(nodes.some(n=>n.id===edge.from));assert(nodes.some(n=>n.id===edge.to));}
assert.equal(defaults('table',()=>String(++id)).rows.length,3);
console.log('PASS: safe media URLs, supported video embeds/timestamps, searchable widget content, linked diagram defaults.');

const {createStudio}=await import('../assets/studio.js');
const {File}=await import('node:buffer');
globalThis.File=File;
globalThis.FileReader=class {readAsDataURL(blob){blob.arrayBuffer().then(buf=>{this.result='data:'+blob.type+';base64,'+Buffer.from(buf).toString('base64');this.onload();},()=>this.onerror());}};
const oldId='a'.repeat(32),newId='b'.repeat(32),bytes=Buffer.from('fake-image-bytes');
const pages=[{id:'page',title:'Test',blocks:[{id:'photo',type:'image',media:oldId},{id:'drawing',type:'drawing',objects:[{id:'shape',kind:'image',media:oldId}]}]}];
let exported,imported,importedCovers,validated=false,uploaded=0;
const originalFetch=globalThis.fetch;
globalThis.fetch=async(url,opts)=>{if(String(url).includes('action=media'))return new Response(bytes,{headers:{'Content-Type':'image/png'}});assert(validated,'validate before upload');assert.equal(Buffer.from(await opts.body.get('file').arrayBuffer()).toString(),bytes.toString());uploaded++;return Response.json({media:{id:newId,name:'image.png',mime:'image/png'}},{status:201});};
const studio=createStudio({esc:s=>s,uid:()=>'',getPage:()=>pages[0],dirty:()=>{},renderBlocks:()=>{},modal:()=>{},toast:()=>{},csrf:()=>'',pages:()=>pages,flush:async()=>{},download:(name,body)=>{exported=JSON.parse(body);},api:async(action,data)=>{if(action==='covers')return {covers:{'page:page':{media_id:oldId,position:25},dnd:{media_id:oldId,position:75}}};if(action==='library')return {media:[{id:oldId,mime:'image/png',name:'image.png'}]};if(action==='validate-import'){validated=true;return {ok:true};}if(action==='import'){imported=data.pages;importedCovers=data.covers;return {count:1};}throw Error(action);}});
await studio.fullBackup();assert.equal(exported.version,2);assert.equal(exported.media.length,1);assert.equal(exported.media[0].data,bytes.toString('base64'));
await studio.importBackup(new File([JSON.stringify(exported)],'backup.json',{type:'application/json'}));assert.equal(uploaded,1);assert.equal(imported[0].blocks[0].media,newId);assert.equal(imported[0].blocks[1].objects[0].media,newId);assert.equal(pages[0].blocks[0].media,oldId);assert.equal(imported[0].cover_photo.media_id,newId);assert.equal(imported[0].cover_photo.position,25);assert.equal(importedCovers.dnd.media_id,newId);assert.equal(importedCovers.dnd.position,75);
globalThis.fetch=originalFetch;
console.log('PASS: portable backups include deduplicated image bytes; import validates first and remaps photo and drawing references.');
