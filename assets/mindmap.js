export function newMindmap(title){return {nodes:[{id:'root',parent:null,label:Array.from(title&&title!=='Başlıksız'?title:'Merkez konu').slice(0,100).join('')}],weight:3,length:90,motion:'float'};}

// Lay out each branch in its own vertical band; descendants never collide with siblings.
export function layout(map){
 const [root,...rest]=map.nodes,children=new Map(map.nodes.map(n=>[n.id,[]]));
 rest.forEach(n=>children.get(n.parent).push(n));
 const size=new Map();for(const n of [...map.nodes].reverse())size.set(n.id,Math.max(1,children.get(n.id).reduce((s,k)=>s+size.get(k.id),0)));
 const points=[{...root,x:0,y:0,color:0}],sides=[[],[]];
 children.get(root.id).forEach((n,i)=>sides[i%2].push([n,i]));
 function place(n,x,top,dir,color){const h=size.get(n.id)*78;points.push({...n,x,y:top+h/2,color});for(const k of children.get(n.id)){place(k,x+dir*(146+map.length),top,dir,color);top+=size.get(k.id)*78;}}
 sides.forEach((group,i)=>{const dir=i===0?1:-1;let top=-group.reduce((s,[n])=>s+size.get(n.id)*78,0)/2;for(const [n,color] of group){place(n,dir*(146+map.length),top,dir,color);top+=size.get(n.id)*78;}});
 const dx=Math.max(200,...points.map(p=>Math.abs(p.x)+90)),dy=Math.max(130,...points.map(p=>Math.abs(p.y)+50));
 return {width:dx*2,height:dy*2,points:points.map(p=>({...p,x:p.x+dx,y:p.y+dy}))};
}
export function mapView(){return '<section class="mm"><header class="mm-heading"><div><div class="eyebrow">FİKİR ATÖLYESİ</div><h1>Zihin haritası</h1></div><button class="button" id="mm-settings">⚙ Ayarlar</button></header><div class="mm-canvas" aria-label="Zihin haritası konuları"><div class="mm-stage"></div></div><div class="mm-tools"><span id="mm-selected"></span><button class="button primary" id="mm-add">＋ Dal ekle</button><button class="button" id="mm-edit">Adını değiştir</button><button class="button" id="mm-del">Dalı sil</button><button class="button" id="mm-center">Merkeze dön</button></div></section>';}
export function bindMap({p,dirty,modal,esc,toast,confirmAction}){
 const m=p.mindmap,root=m.nodes[0],q=s=>document.querySelector(s),canvas=q('.mm-canvas'),stage=q('.mm-stage');let chosen=root.id;
 const current=()=>m.nodes.find(n=>n.id===chosen)||root;
 function select(id,center=false){
  chosen=id;stage.querySelectorAll('[data-mm-node]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.mmNode===id)));
  q('#mm-selected').textContent='Seçili: '+current().label;q('#mm-del').hidden=id===root.id;
  if(center){const el=stage.querySelector('[aria-pressed=true]');canvas.scrollLeft=stage.offsetLeft+el.offsetLeft-canvas.clientWidth/2;canvas.scrollTop=stage.offsetTop+el.offsetTop-canvas.clientHeight/2;}
 }
 function paint(center=false){
  const {width,height,points}=layout(m),by=new Map(points.map(n=>[n.id,n]));stage.style.width=width+'px';stage.style.height=height+'px';stage.style.setProperty('--mm-half',height/2+'px');stage.dataset.motion=m.motion;
  const hues=[265,204,335,30,175,243];const color=n=>hues[n.color%hues.length];
  const edges=points.slice(1).map(n=>{const a=by.get(n.parent),x=(a.x+n.x)/2;return '<path d="M '+a.x+' '+a.y+' C '+x+' '+a.y+' '+x+' '+n.y+' '+n.x+' '+n.y+'" style="--h:'+color(n)+'"/>';}).join('');
  stage.innerHTML='<div class="mm-water"><svg class="mm-lines" aria-hidden="true" width="'+width+'" height="'+height+'" fill="none" stroke-width="'+m.weight+'">'+edges+'</svg>'+points.map(n=>'<button type="button" data-mm-node="'+esc(n.id)+'" class="mm-node '+(n.id===root.id?'mm-root':'')+'" title="'+esc(n.label)+'" style="left:'+n.x+'px;top:'+n.y+'px;--h:'+color(n)+'"><span>'+esc(n.label)+'</span></button>').join('')+'</div>';
  stage.querySelectorAll('[data-mm-node]').forEach(b=>{b.onclick=()=>select(b.dataset.mmNode);b.ondblclick=()=>nameForm(false);});select(chosen,center);
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
 q('#mm-settings').onclick=()=>modal('Harita ayarları','<form id="mm-options"><p class="small-note">Bu ayarlar yalnızca bu sayfanın haritasına uygulanır.</p><label>Çizgi kalınlığı <output id="mm-w"></output><input type="range" name="weight" min="1" max="9"></label><label>Dal uzunluğu <output id="mm-l"></output><input type="range" name="length" min="40" max="220" step="5"></label><label>Hareket<select name="motion"><option value="still">Durgun</option><option value="float">Suda süzülme</option><option value="ripple">Dalgalı akış</option></select></label><div class="modal-actions"><button class="button primary">Uygula</button></div></form>',()=>{
  const f=q('#mm-options');for(const k of ['weight','length','motion'])f.elements.namedItem(k).value=m[k];const labels=()=>{q('#mm-w').textContent=f.elements.weight.value+' px';q('#mm-l').textContent=f.elements.namedItem('length').value+' px';};labels();f.oninput=labels;
  f.onsubmit=e=>{e.preventDefault();Object.assign(m,{weight:+f.elements.weight.value,length:+f.elements.namedItem('length').value,motion:f.elements.motion.value});q('#dialog').close();dirty(p);paint(true);};
 });
 paint(true);
}
