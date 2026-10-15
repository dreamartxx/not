import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const manifest=JSON.parse(await readFile(new URL('../manifest.webmanifest',import.meta.url),'utf8'));
assert.equal(manifest.display,'standalone');assert.equal(manifest.id,'./');assert.equal(manifest.start_url,'./');assert.equal(manifest.scope,'./');
for(const icon of manifest.icons){const bytes=await readFile(new URL('../'+icon.src,import.meta.url));assert.equal(bytes.subarray(1,4).toString(),'PNG');assert.equal(bytes.readUInt32BE(16),Number(icon.sizes.split('x')[0]));assert.equal(bytes.readUInt32BE(20),Number(icon.sizes.split('x')[1]));}
assert(manifest.icons.some(i=>i.purpose==='maskable'));
const source=await readFile(new URL('../sw.js',import.meta.url),'utf8');
// Exercise the worker in both a root deployment and a nested deployment.
for(const base of ['https://not.test/','https://not.test/notes/']){
 const handlers={},stored=new Map(),deleted=[];let claimed=false,skip=false,offline=false,fetches=[];
 const cache={addAll:async urls=>{for(const url of urls)stored.set(url,new Response(url.endsWith('offline.html')?'OFFLINE':'PUBLIC'));},match:async request=>stored.get(typeof request==='string'?request:request.url)?.clone()};
 const self={location:{href:base+'sw.js'},clients:{claim:async()=>{claimed=true;}},skipWaiting:async()=>{skip=true;},addEventListener:(name,fn)=>handlers[name]=fn};
 vm.runInNewContext(source,{self,URL,Set,caches:{open:async()=>cache,keys:async()=>['not-public-pwa-old','other-app-cache','not-public-pwa-v2'],delete:async k=>deleted.push(k)},fetch:async request=>{fetches.push(request.url);if(offline)throw new Error('offline');return new Response('NETWORK');}});
 async function life(name,data){let promise;handlers[name]({data,waitUntil:p=>promise=p});await promise;}
 await life('install');assert.equal(stored.size,4);assert(!skip,'install must not force update');
 await life('activate');assert(claimed);assert.deepEqual(deleted,['not-public-pwa-old']);
 async function request(path,mode='cors',method='GET'){let result;handlers.fetch({request:{url:new URL(path,base).href,mode,method},respondWith:p=>result=p});return result===undefined?undefined:await result;}
 for(const [url,mode,method] of [['api.php?action=pages','cors','GET'],['api.php?action=media&id=123','navigate','GET'],['api.php?action=save','cors','POST'],['private/config.php','navigate','GET'],['https://external.test/file','navigate','GET'],['assets/app.js','cors','GET']])assert.equal(await request(url,mode,method),undefined,'private/non-allowlisted requests must bypass worker');
 assert.equal(await (await request('./','navigate')).text(),'NETWORK');assert.equal(stored.size,4,'navigation HTML must not be stored');
 offline=true;assert.equal(await (await request('./#note','navigate')).text(),'OFFLINE');assert.equal(await (await request('index.php','navigate')).text(),'OFFLINE');assert.equal(await (await request('assets/icon-3d-192.png')).text(),'PUBLIC');
 await life('message',{type:'unrelated'});assert(!skip);await life('message',{type:'ACTIVATE_UPDATE'});assert(skip);
}
console.log('PASS: install manifest and PNG dimensions; worker lifecycle, offline fallback, private API/media bypass, scoped cache cleanup, explicit updates.');
