export function newMindmap(title){return {nodes:[{id:'root',parent:null,label:Array.from(title&&title!=='Başlıksız'?title:'Merkez konu').slice(0,100).join('')}],weight:3,length:90,motion:'ripple',strength:30,speed:35};}

// Lay out each branch in its own vertical band; descendants never collide with siblings.
export function layout(map){
 const [root,...rest]=map.nodes,children=new Map(map.nodes.map(n=>[n.id,[]]));
 rest.forEach(n=>children.get(n.parent).push(n));
 const size=new Map();for(const n of [...map.nodes].reverse())size.set(n.id,Math.max(1,children.get(n.id).reduce((s,k)=>s+size.get(k.id),0)));
 const points=[{...root,x:0,y:0,color:0}],sides=[[],[]];
 children.get(root.id).forEach((n,i)=>sides[i%2].push([n,i]));
 function place(n,x,top,dir,color){const h=size.get(n.id)*78;points.push({...n,x,y:top+h/2,color});for(const k of children.get(n.id)){place(k,x+dir*(146+map.length),top,dir,color);top+=size.get(k.id)*78;}}
 sides.forEach((group,i)=>{const dir=i===0?1:-1;let top=-group.reduce((s,[n])=>s+size.get(n.id)*78,0)/2;for(const [n,color] of group){place(n,dir*(146+map.length),top,dir,color);top+=size.get(n.id)*78;}});
 for(const n of points){n.x+=n.dx||0;n.y+=n.dy||0;}
 const dx=Math.max(200,...points.map(p=>Math.abs(p.x)+90)),dy=Math.max(130,...points.map(p=>Math.abs(p.y)+50));
 return {width:dx*2,height:dy*2,points:points.map(p=>({...p,x:p.x+dx,y:p.y+dy}))};
}
export function waveOffset(map,node,time,reduced=false){
 if(reduced||map.motion==='still')return {x:0,y:0};
 const amount=(map.strength??30)/100,phase=time*(.25+(map.speed??35)/100*1.75);
 const seed=node.x*.009+node.y*.006;
 if(map.motion==='float')return {x:Math.sin(phase*.7+seed)*amount*5,y:Math.sin(phase+seed)*amount*12};
 return {x:(Math.sin(phase+seed)*.7+Math.cos(phase*.63-seed)*.3)*amount*20,y:(Math.sin(phase*1.3-seed)*.65+Math.cos(phase*.8+seed)*.35)*amount*26};
}
// Camera coordinates stay independent of the graph bounds and stored node offsets.
export function moveNode(node,x,y){node.dx=(node.dx||0)+x;node.dy=(node.dy||0)+y;}
export function zoomAt(camera,scale,x,y){const zoom=Math.max(.1,Math.min(3,scale)),ratio=zoom/camera.zoom;return {x:x-(x-camera.x)*ratio,y:y-(y-camera.y)*ratio,zoom};}
export function mapView(){return `<section class="mm"><div class="mm-canvas" aria-label="Zihin haritası konuları" tabindex="0"><div class="mm-stage"></div></div><header class="mm-heading"><div><div class="eyebrow">FİKİR ATÖLYESİ</div><h1>Zihin haritası</h1></div><button class="button" id="mm-settings">⚙ Ayarlar</button></header><div class="mm-navigation" role="group" aria-label="Harita görünümü"><button class="button" id="mm-out" aria-label="Uzaklaştır">−</button><output id="mm-zoom">100%</output><button class="button" id="mm-in" aria-label="Yakınlaştır">＋</button><button class="button" id="mm-fit">Tümünü göster</button><button class="button" id="mm-center">Merkeze dön</button></div><p class="mm-hint">Konuyu sürükle · Tıkla ve not al · Boşluğu sürükleyerek gezin · Ctrl / ⌘ + tekerlek ile yakınlaştır</p><div class="mm-tools"><span id="mm-selected"></span><button class="button primary" id="mm-add">＋ Dal ekle</button><button class="button" id="mm-edit">Adını değiştir</button><button class="button" id="mm-del">Dalı sil</button></div><aside class="mm-note-panel" hidden aria-label="Konu notu"><header><div><small>KONU NOTU</small><h2 id="mm-note-title"></h2></div><button class="icon-button" id="mm-note-close" aria-label="Konu notunu kapat">×</button></header><label for="mm-note">Bu konuya ait notların</label><textarea id="mm-note" maxlength="20000" placeholder="Fikirlerini, ayrıntıları ve yapılacakları yaz…"></textarea><p>Otomatik kaydedilir · Bu konuya özel</p></aside></section>`;}
export function bindMap({p,dirty,modal,esc,toast,confirmAction}){
 const m=p.mindmap,root=m.nodes[0],q=s=>document.querySelector(s),canvas=q('.mm-canvas'),stage=q('.mm-stage'),panel=q('.mm-note-panel');let chosen=root.id,frame=0,drag=null,pan=null,suppressClickUntil=0,camera={x:canvas.clientWidth/2,y:canvas.clientHeight/2,zoom:1},world=[];const reduced=matchMedia('(prefers-reduced-motion: reduce)');
 const current=()=>m.nodes.find(n=>n.id===chosen)||root;
 function view(){stage.style.transform=`translate(${camera.x}px,${camera.y}px) scale(${camera.zoom})`;canvas.style.backgroundPosition=`${camera.x}px ${camera.y}px`;q('#mm-zoom').textContent=Math.round(camera.zoom*100)+'%';}
 function centerOn(id){const n=world.find(n=>n.id===id);if(n){camera.x=canvas.clientWidth/2-n.x*camera.zoom;camera.y=canvas.clientHeight/2-n.y*camera.zoom;view();}}
 function showNote(){q('#mm-note-title').textContent=current().label;q('#mm-note').value=current().note||'';panel.hidden=false;}
 function select(id,center=false){
  chosen=id;stage.querySelectorAll('[data-mm-node]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.mmNode===id)));
  q('#mm-selected').textContent='Seçili: '+current().label;q('#mm-del').hidden=id===root.id;
  if(!panel.hidden)showNote();if(center)centerOn(id);
 }
 q('#mm-note').oninput=e=>{current().note=e.target.value;dirty(p);const b=stage.querySelector('[aria-pressed=true]');if(b)b.classList.toggle('has-note',!!current().note.trim());};
 q('#mm-note-close').onclick=()=>{panel.hidden=true;stage.querySelector('[aria-pressed=true]')?.focus({preventScroll:true});};
 function setZoom(scale,x=canvas.clientWidth/2,y=canvas.clientHeight/2){camera=zoomAt(camera,scale,x,y);view();}
 q('#mm-in').onclick=()=>setZoom(camera.zoom*1.25);q('#mm-out').onclick=()=>setZoom(camera.zoom/1.25);
 q('#mm-fit').onclick=()=>{const xs=world.map(n=>n.x),ys=world.map(n=>n.y),left=Math.min(...xs)-100,right=Math.max(...xs)+100,top=Math.min(...ys)-60,bottom=Math.max(...ys)+60;camera.zoom=Math.max(.1,Math.min(1,(canvas.clientWidth-48)/(right-left),(canvas.clientHeight-190)/(bottom-top)));camera.x=canvas.clientWidth/2-(left+right)/2*camera.zoom;camera.y=canvas.clientHeight/2-(top+bottom)/2*camera.zoom;view();};
 const wheel=e=>{e.preventDefault();if(drag||pan)return;const r=canvas.getBoundingClientRect();if(e.ctrlKey||e.metaKey)setZoom(camera.zoom*Math.exp(-e.deltaY*.006),e.clientX-r.left,e.clientY-r.top);else{camera.x-=e.deltaX;camera.y-=e.deltaY;view();}};
 canvas.addEventListener('wheel',wheel,{passive:false});
 canvas.onpointerdown=e=>{if(e.target.closest('[data-mm-node]')||e.button!==0||drag||pan)return;pan={pointer:e.pointerId,x:e.clientX,y:e.clientY,cx:camera.x,cy:camera.y};canvas.setPointerCapture(e.pointerId);canvas.classList.add('mm-panning');};
 canvas.onpointermove=e=>{if(!pan||pan.pointer!==e.pointerId)return;camera.x=pan.cx+e.clientX-pan.x;camera.y=pan.cy+e.clientY-pan.y;view();};
 const endPan=e=>{if(pan?.pointer!==e.pointerId)return;pan=null;canvas.classList.remove('mm-panning');if(canvas.hasPointerCapture(e.pointerId))canvas.releasePointerCapture(e.pointerId);};
 canvas.onpointerup=endPan;canvas.onpointercancel=endPan;canvas.onlostpointercapture=endPan;
 function paint(center=false){
  cancelAnimationFrame(frame);
  const {width,height,points:laid}=layout(m),points=laid.map(n=>({...n,x:n.x-width/2,y:n.y-height/2}));world=points;
  const hues=[265,204,335,30,175,243];const color=n=>hues[n.color%hues.length];
  stage.innerHTML='<svg class="mm-lines" aria-hidden="true" width="1" height="1" fill="none" stroke-width="'+m.weight+'">'+points.slice(1).map(n=>'<path data-edge="'+esc(n.id)+'" style="--h:'+color(n)+'"/>').join('')+'</svg>'+points.map(n=>'<button type="button" data-mm-node="'+esc(n.id)+'" class="mm-node '+(n.id===root.id?'mm-root ':'')+(n.note?.trim()?'has-note':'')+'" title="'+esc(n.label)+' · Tıkla ve not al · Sürükleyerek taşı" style="left:'+n.x+'px;top:'+n.y+'px;--h:'+color(n)+'"><span>'+esc(n.label)+'</span></button>').join('');
  const buttons=new Map([...stage.querySelectorAll('[data-mm-node]')].map(b=>[b.dataset.mmNode,b]));
  const edges=new Map([...stage.querySelectorAll('[data-edge]')].map(b=>[b.dataset.edge,b])),display=new Map();
  function draw(){for(const n of points){const v=display.get(n.id),btn=buttons.get(n.id);btn.style.left=v.x+'px';btn.style.top=v.y+'px';if(n.parent){const a=display.get(n.parent),x=(a.x+v.x)/2;edges.get(n.id).setAttribute('d','M '+a.x+' '+a.y+' C '+x+' '+a.y+' '+x+' '+v.y+' '+v.x+' '+v.y);}}}
  function tick(t){if(!stage.isConnected)return;if(!drag&&!document.hidden){for(const n of points){const w=waveOffset(m,n,t/1000,reduced.matches);display.set(n.id,{x:n.x+w.x,y:n.y+w.y});}draw();}frame=requestAnimationFrame(tick);}
  for(const n of points)display.set(n.id,{x:n.x,y:n.y});draw();
  function finish(commit){
   if(!drag)return;const d=drag;drag=null;stage.classList.remove('mm-dragging');
   if(d.button.hasPointerCapture(d.pointer))d.button.releasePointerCapture(d.pointer);
   if(!d.moved)return;
   suppressClickUntil=Date.now()+350;
   if(commit){moveNode(m.nodes.find(n=>n.id===d.id),d.deltaX,d.deltaY);dirty(p);}
   paint(false);
  }
  for(const [id,b] of buttons){
   b.onclick=()=>{if(Date.now()>=suppressClickUntil){select(id);showNote();}};
   b.ondblclick=()=>{if(Date.now()>=suppressClickUntil){select(id);nameForm(false);}};
   b.onpointerdown=e=>{if(e.button!==0||drag||pan)return;select(id);const v=display.get(id);drag={id,button:b,pointer:e.pointerId,startX:e.clientX,startY:e.clientY,x:v.x,y:v.y,deltaX:0,deltaY:0,moved:false};b.setPointerCapture(e.pointerId);b.focus({preventScroll:true});};
   b.onpointermove=e=>{if(!drag||drag.pointer!==e.pointerId||drag.id!==id)return;const d=drag,x=e.clientX-d.startX,y=e.clientY-d.startY;if(!d.moved&&Math.hypot(x,y)<4)return;d.moved=true;stage.classList.add('mm-dragging');e.preventDefault();d.deltaX=x/camera.zoom;d.deltaY=y/camera.zoom;display.set(id,{x:d.x+d.deltaX,y:d.y+d.deltaY});draw();};
   b.onpointerup=e=>{if(drag?.pointer===e.pointerId)finish(true);};b.onpointercancel=e=>{if(drag?.pointer===e.pointerId)finish(false);};b.onlostpointercapture=e=>{if(drag?.pointer===e.pointerId)finish(false);};
   b.onkeydown=e=>{if(e.key==='Escape'&&drag){e.preventDefault();finish(false);return;}const step=e.shiftKey?20:5,dirs={ArrowLeft:[-step,0],ArrowRight:[step,0],ArrowUp:[0,-step],ArrowDown:[0,step]};if(!dirs[e.key])return;e.preventDefault();e.stopPropagation();select(id);moveNode(current(),...dirs[e.key]);dirty(p);paint(false);stage.querySelector('[aria-pressed=true]').focus({preventScroll:true});};
  }
  select(chosen,center);view();frame=requestAnimationFrame(tick);
 }
 function nameForm(add){
  if(add&&m.nodes.length>=150){toast('Bir haritada en fazla 150 konu olabilir.');return;}
  const target=current();modal(add?'Dal ekle':'Konu adını değiştir','<form id="mm-form"><label>Konu adı<input id="mm-name" maxlength="100" required></label><p class="small-note">'+(add?'Bağlı olduğu konu: '+esc(target.label):'Bu ad yalnızca zihin haritasında değişir.')+'</p><div class="modal-actions"><button class="button primary">Kaydet</button></div></form>',()=>{
   const input=q('#mm-name');input.value=add?'':target.label;input.focus();input.select();q('#mm-form').onsubmit=e=>{e.preventDefault();const label=input.value.trim();if(!label){input.focus();return;}
    if(add){const n={id:crypto.randomUUID(),parent:target.id,label};m.nodes.push(n);chosen=n.id;}else target.label=label;
    q('#dialog').close();dirty(p);paint(true);};
  });
 }
 q('#mm-add').onclick=()=>nameForm(true);q('#mm-edit').onclick=()=>nameForm(false);
 q('#mm-del').onclick=()=>{const n=current();if(!n.parent)return;confirmAction('Dal silinsin mi?',n.label+' ve ona bağlı alt konular kaldırılacak.',()=>{const gone=new Set([n.id]);for(const k of m.nodes)if(gone.has(k.parent))gone.add(k.id);m.nodes=m.nodes.filter(k=>!gone.has(k.id));chosen=n.parent;dirty(p);paint(true);});};
 q('#mm-center').onclick=()=>select(root.id,true);
 q('#mm-settings').onclick=()=>modal('Harita ayarları','<form id="mm-options"><p class="small-note">Bu ayarlar yalnızca bu sayfanın haritasına uygulanır.</p><label>Çizgi kalınlığı <output id="mm-w"></output><input type="range" name="weight" min="1" max="9"></label><label>Dal uzunluğu <output id="mm-l"></output><input type="range" name="length" min="40" max="220" step="5"></label><label>Hareket<select name="motion"><option value="still">Durgun</option><option value="float">Suda süzülme</option><option value="ripple">Su dalgası</option></select></label><label>Dalga şiddeti <output id="mm-strength"></output><input type="range" name="strength" min="0" max="100"></label><label>Dalga hızı <output id="mm-speed"></output><input type="range" name="speed" min="10" max="100"></label><div class="modal-actions"><button class="button primary">Uygula</button></div></form>',()=>{
  const f=q('#mm-options');for(const k of ['weight','length','motion','strength','speed'])f.elements.namedItem(k).value=m[k]??(k==='strength'?30:35);const labels=()=>{q('#mm-w').textContent=f.elements.weight.value+' px';q('#mm-l').textContent=f.elements.namedItem('length').value+' px';q('#mm-strength').textContent=f.elements.strength.value+'%';q('#mm-speed').textContent=f.elements.speed.value+'%';for(const k of ['strength','speed'])f.elements[k].disabled=f.elements.motion.value==='still';};labels();f.oninput=labels;
  f.onsubmit=e=>{e.preventDefault();Object.assign(m,{weight:+f.elements.weight.value,length:+f.elements.namedItem('length').value,motion:f.elements.motion.value,strength:+f.elements.strength.value,speed:+f.elements.speed.value});q('#dialog').close();dirty(p);paint(true);};
 });
 paint(true);
 return ()=>{cancelAnimationFrame(frame);drag=null;pan=null;canvas.removeEventListener('wheel',wheel);};
}
