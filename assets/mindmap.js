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
export function moveNode(node,x,y){node.dx=Math.max(-4000,Math.min(4000,(node.dx||0)+x));node.dy=Math.max(-4000,Math.min(4000,(node.dy||0)+y));}
export function mapView(){return '<section class="mm"><header class="mm-heading"><div><div class="eyebrow">FİKİR ATÖLYESİ</div><h1>Zihin haritası</h1></div><button class="button" id="mm-settings">⚙ Ayarlar</button></header><div class="mm-canvas" aria-label="Zihin haritası konuları"><div class="mm-stage"></div></div><p class="mm-hint">Konuları tutup sürükle · Klavyede ok tuşlarıyla taşı · Boş alanda kaydır</p><div class="mm-tools"><span id="mm-selected"></span><button class="button primary" id="mm-add">＋ Dal ekle</button><button class="button" id="mm-edit">Adını değiştir</button><button class="button" id="mm-del">Dalı sil</button><button class="button" id="mm-center">Merkeze dön</button></div></section>';}
export function bindMap({p,dirty,modal,esc,toast,confirmAction}){
 const m=p.mindmap,root=m.nodes[0],q=s=>document.querySelector(s),canvas=q('.mm-canvas'),stage=q('.mm-stage');let chosen=root.id,frame=0,drag=null,suppressClickUntil=0;const reduced=matchMedia('(prefers-reduced-motion: reduce)');
 const current=()=>m.nodes.find(n=>n.id===chosen)||root;
 function select(id,center=false){
  chosen=id;stage.querySelectorAll('[data-mm-node]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.mmNode===id)));
  q('#mm-selected').textContent='Seçili: '+current().label;q('#mm-del').hidden=id===root.id;
  if(center){const el=stage.querySelector('[aria-pressed=true]');canvas.scrollLeft=stage.offsetLeft+el.offsetLeft-canvas.clientWidth/2;canvas.scrollTop=stage.offsetTop+el.offsetTop-canvas.clientHeight/2;}
 }
 function paint(center=false){
  cancelAnimationFrame(frame);
  const {width,height,points}=layout(m);stage.style.width=width+'px';stage.style.height=height+'px';stage.style.setProperty('--mm-half',height/2+'px');stage.dataset.motion=m.motion;
  const hues=[265,204,335,30,175,243];const color=n=>hues[n.color%hues.length];
  stage.innerHTML='<div class="mm-water"><svg class="mm-lines" aria-hidden="true" width="'+width+'" height="'+height+'" fill="none" stroke-width="'+m.weight+'">'+points.slice(1).map(n=>'<path data-edge="'+esc(n.id)+'" style="--h:'+color(n)+'"/>').join('')+'</svg>'+points.map(n=>'<button type="button" data-mm-node="'+esc(n.id)+'" class="mm-node '+(n.id===root.id?'mm-root':'')+'" title="'+esc(n.label)+' · Sürükleyerek taşı" style="left:'+n.x+'px;top:'+n.y+'px;--h:'+color(n)+'"><span>'+esc(n.label)+'</span></button>').join('')+'</div>';
  const buttons=new Map([...stage.querySelectorAll('[data-mm-node]')].map(b=>[b.dataset.mmNode,b]));
  const edges=new Map([...stage.querySelectorAll('[data-edge]')].map(b=>[b.dataset.edge,b])),display=new Map();
  function draw(){
   for(const n of points){const v=display.get(n.id),btn=buttons.get(n.id);btn.style.left=v.x+'px';btn.style.top=v.y+'px';
    if(n.parent){const a=display.get(n.parent),x=(a.x+v.x)/2;edges.get(n.id).setAttribute('d','M '+a.x+' '+a.y+' C '+x+' '+a.y+' '+x+' '+v.y+' '+v.x+' '+v.y);}
   }
  }
  function tick(t){
   if(!stage.isConnected)return;
   if(!drag&&!document.hidden){for(const n of points){const w=waveOffset(m,n,t/1000,reduced.matches);display.set(n.id,{x:n.x+w.x,y:n.y+w.y});}draw();}
   frame=requestAnimationFrame(tick);
  }
  // Static first frame makes pointer and keyboard actions deterministic even before RAF.
  for(const n of points)display.set(n.id,{x:n.x,y:n.y});draw();
  function finish(commit){
   if(!drag)return;const d=drag;drag=null;stage.classList.remove('mm-dragging');
   if(d.button.hasPointerCapture(d.pointer))d.button.releasePointerCapture(d.pointer);
   if(!d.moved)return;
   if(d.moved&&commit){moveNode(m.nodes.find(n=>n.id===d.id),d.deltaX,d.deltaY);dirty(p);suppressClickUntil=Date.now()+350;}
   const before=d.button.getBoundingClientRect();paint(false);
   const after=stage.querySelector('[aria-pressed=true]').getBoundingClientRect();canvas.scrollLeft+=after.left-before.left;canvas.scrollTop+=after.top-before.top;
  }
  for(const [id,b] of buttons){
   b.onclick=()=>{if(Date.now()>=suppressClickUntil)select(id);};
   b.ondblclick=()=>{if(Date.now()>=suppressClickUntil){select(id);nameForm(false);}};
   b.onpointerdown=e=>{
    if(e.button!==0||drag)return;
    select(id);const n=m.nodes.find(n=>n.id===id),v=display.get(id);
    drag={id,button:b,pointer:e.pointerId,startX:e.clientX,startY:e.clientY,scrollX:canvas.scrollLeft,scrollY:canvas.scrollTop,x:v.x,y:v.y,dx:n.dx||0,dy:n.dy||0,deltaX:0,deltaY:0,moved:false};
    b.setPointerCapture(e.pointerId);b.focus({preventScroll:true});
   };
   b.onpointermove=e=>{
    if(!drag||drag.pointer!==e.pointerId||drag.id!==id)return;
    const d=drag,x=e.clientX-d.startX+canvas.scrollLeft-d.scrollX,y=e.clientY-d.startY+canvas.scrollTop-d.scrollY;
    if(!d.moved&&Math.hypot(x,y)<4)return;
    d.moved=true;stage.classList.add('mm-dragging');e.preventDefault();
    // Keep the dragged topic reachable inside the scrollable canvas.
    d.deltaX=Math.max(Math.max(-4000-d.dx,82-d.x),Math.min(Math.min(4000-d.dx,width-82-d.x),x));
    d.deltaY=Math.max(Math.max(-4000-d.dy,42-d.y),Math.min(Math.min(4000-d.dy,height-42-d.y),y));
    display.set(id,{x:d.x+d.deltaX,y:d.y+d.deltaY});draw();
   };
   b.onpointerup=e=>{if(drag?.pointer===e.pointerId)finish(true);};
   b.onpointercancel=e=>{if(drag?.pointer===e.pointerId)finish(false);};
   b.onlostpointercapture=e=>{if(drag?.pointer===e.pointerId)finish(false);};
   b.onkeydown=e=>{
    if(e.key==='Escape'&&drag){e.preventDefault();finish(false);return;}
    const step=e.shiftKey?20:5,dirs={ArrowLeft:[-step,0],ArrowRight:[step,0],ArrowUp:[0,-step],ArrowDown:[0,step]};
    if(!dirs[e.key])return;e.preventDefault();e.stopPropagation();select(id);moveNode(current(),...dirs[e.key]);dirty(p);paint(false);stage.querySelector('[aria-pressed=true]').focus({preventScroll:true});
   };
  }
  select(chosen,center);frame=requestAnimationFrame(tick);
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
 return ()=>{cancelAnimationFrame(frame);drag=null;};
}
