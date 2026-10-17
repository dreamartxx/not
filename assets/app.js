const {createDnd}=await import('./dnd.js'+new URL(import.meta.url).search);
const {createFootball}=await import('./football.js'+new URL(import.meta.url).search);
const {initPWA}=await import('./pwa.js'+new URL(import.meta.url).search);
const {createStudio,extraTypes,advancedTypes,defaults,blockText}=await import('./studio.js'+new URL(import.meta.url).search);
const $ = (s, root = document) => root.querySelector(s);
const app = $('#app');
const dialog = $('#dialog');
const uid = () => crypto.randomUUID();
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const icons = ['📄','🌿','💡','📚','✍️','🎯','🗓️','🧠','💼','🏡','✨','☕','🩺','🎨','🚀','📌'];
const types = {text:['T','Metin'],h1:['H₁','Büyük başlık'],h2:['H₂','Küçük başlık'],bullet:['•','Madde listesi'],todo:['☑','Yapılacak'],quote:['❝','Alıntı'],callout:['💡','Bilgi kutusu'],code:['〈〉','Kod'],divider:['—','Ayırıcı']};
Object.assign(types,extraTypes);
const state = {pages:[], current:null, view:'home', csrf:'', username:'', dirty:new Map(), saving:false, timer:null, error:null, query:'', collapsed:new Set(), theme:localStorage.getItem('not-theme') || 'light'};
document.documentElement.dataset.theme = state.theme;
const page = () => state.pages.find(p=>p.id===state.current);
const active = () => state.pages.filter(p=>!p.deleted_at);
const newBlock = (type='text',text='') => ({id:uid(),type,text,checked:false,...defaults(type,uid)});
const formatDate = date => new Date(date).toLocaleDateString('tr-TR',{day:'numeric',month:'short'});
const toast = message => { $('#toast').textContent=message;$('#toast').classList.add('show');clearTimeout(toast.timer);toast.timer=setTimeout(()=>$('#toast').classList.remove('show'),3500); };
async function api(action, data) {
  const r=await fetch(`api.php?action=${action}`,{method:data?'POST':'GET',credentials:'same-origin',headers:data?{'Content-Type':'application/json','X-CSRF-Token':state.csrf}:{},body:data?JSON.stringify(data):undefined});
  let body;try{body=await r.json();}catch{throw new Error('Sunucudan yanıt alınamadı. Lütfen tekrar dene.');}
  if(!r.ok){const error=new Error(body.error || 'İşlem tamamlanamadı.');error.status=r.status;error.body=body;throw error;}
  return body;
}
function download(name, content, type='application/json') { const url=URL.createObjectURL(new Blob([content],{type}));const a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000); }
function backup() { download(`not-yedek-${new Date().toISOString().slice(0,10)}.json`,JSON.stringify({format:'not-backup',version:1,exported_at:new Date().toISOString(),pages:state.pages},null,2));toast('Tüm notların yedeği indirildi.'); }
function modal(title, html, onReady) { dialog.innerHTML=`<div class="modal-heading"><h2>${esc(title)}</h2><button class="icon-button" data-close aria-label="Kapat">×</button></div>${html}`;dialog.showModal();$('[data-close]',dialog).onclick=()=>dialog.close();onReady?.(); }
function confirmAction(title, description, callback) { modal(title,`<p>${esc(description)}</p><div class="modal-actions"><button class="button" id="cancel">Vazgeç</button><button class="button danger" id="confirm">Evet, devam et</button></div>`,()=>{$('#cancel').onclick=()=>dialog.close();$('#confirm').onclick=async()=>{dialog.close();await callback();};}); }
function status() { const s=$('#save-status');if(!s)return;s.textContent=state.error?'Kaydedilemedi · Tekrar dene':state.saving?'Kaydediliyor…':state.dirty.size?'Değişiklikler bekliyor…':'✓ Tüm değişiklikler kaydedildi';s.classList.toggle('error',!!state.error);s.classList.toggle('is-saving',state.saving);s.classList.toggle('is-pending',!!state.dirty.size);s.setAttribute('aria-label',s.textContent);s.title=s.textContent; }
function dirty(p=page()) { recordHistory(p); state.dirty.set(p.id,(state.dirty.get(p.id)||0)+1);state.error=null;status();clearTimeout(state.timer);state.timer=setTimeout(()=>flush().catch(()=>{}),650); }
let savePromise=null;
async function flush() {
  await dnd.flush();
  clearTimeout(state.timer);
  if(savePromise) {await savePromise;if(state.dirty.size)return flush();return;}
  savePromise=(async()=>{
    state.saving=true;status();
    try {
      for (const [id,revision] of state.dirty) {
        const p=state.pages.find(p=>p.id===id);if(!p)continue;
        const payload=structuredClone(p);const result=await api('save',payload);
        p.version=result.page.version;p.updated_at=result.page.updated_at;
        if(state.dirty.get(id)===revision)state.dirty.delete(id);
      }
      state.error=null;
    } catch(e) {state.error=e;toast(e.message);throw e;}
    finally {state.saving=false;status();}
  })();
  try {await savePromise;} finally {savePromise=null;}
  if(state.dirty.size)return flush();
}
async function reload() {state.pages=(await api('pages')).pages;}
async function navigate(view,id=null) {if(view==='blank')return openBlank();try {await flush();state.view=view;state.current=id;state.query='';render();document.body.classList.remove('sidebar-open');history.replaceState(null,'',['football','dnd'].includes(view)?'#'+view:id?`#${id}`:'#');}catch{showRecovery();}}
function showRecovery() {if(dnd.busy()){dnd.recovery();return;}modal('Kaydedilmeyen değişikliklerin var',`<p>${esc(state.error?.message || 'Bağlantı kurulamadı.')} Çalışmanı kaybetmemek için taslağını indirebilirsin.</p><div class="modal-actions"><button class="button" id="draft">Taslağı indir</button><button class="button primary" id="retry">Tekrar kaydet</button></div>`,()=>{$('#draft').onclick=backup;$('#retry').onclick=async()=>{dialog.close();try{await flush();}catch{showRecovery();}};});}
function tree(parent=null, depth=0, seen=new Set()) {
  return active().filter(p=>p.parent_id===parent).map(p=>{
    if(seen.has(p.id))return '';seen.add(p.id);
    const children=active().some(c=>c.parent_id===p.id);const closed=state.collapsed.has(p.id);
    return `<div class="tree-item ${p.id===state.current?'selected':''}" style="--depth:${depth}"><button class="tree-toggle" data-toggle="${p.id}" aria-label="Alt sayfaları ${closed?'aç':'kapat'}">${children?(closed?'›':'⌄'):'·'}</button><button class="tree-page" data-page="${p.id}"><span>${esc(p.icon)}</span><span>${esc(p.title||'Başlıksız')}</span></button></div>${closed?'':tree(p.id,depth+1,seen)}`;
  }).join('');
}
function sidebar() { return `<aside class="sidebar"><button class="workspace" data-view="blank"><span class="brand-mark small">n.</span><span><strong>Kişisel alanım</strong><small>Sadece sana ait</small></span><span class="workspace-chevron">⌄</span></button><nav><button class="nav-item" id="search-open"><span>⌕</span> Hızlı arama <kbd>⌘ K</kbd></button><button class="nav-item ${state.view==='page'?'selected':''}" data-view="blank"><span>⌂</span> Çalışma alanı</button><button class="nav-item ${state.view==='home'?'selected':''}" data-view="home"><span>☷</span> Tüm sayfalar</button><button class="nav-item ${state.view==='football'?'selected':''}" data-view="football"><span>⚽</span> Futbol</button><button class="nav-item ${state.view==='dnd'?'selected':''}" data-view="dnd"><span>◇</span> D&D</button><button class="nav-item ${state.view==='favorites'?'selected':''}" data-view="favorites"><span>☆</span> Favoriler</button></nav><div class="side-label">SAYFALAR <button class="icon-button" id="workspace-menu" aria-label="Çalışma alanı menüsü" title="Yeni sayfa ve şablonlar">•••</button></div><div class="tree">${tree()||'<p class="side-empty">İlk düşüncene yer aç.</p>'}</div><div class="sidebar-bottom"><button class="nav-item" data-pwa-open><span>↧</span> Uygulama olarak kullan</button><button class="nav-item ${state.view==='trash'?'selected':''}" data-view="trash"><span>♲</span> Çöp kutusu</button><button class="nav-item" id="settings-open"><span>⚙</span> Ayarlar ve yedekleme</button><div class="profile"><span class="avatar">${esc(state.username.slice(0,1).toUpperCase())}</span><span><strong>${esc(state.username)}</strong><small>Kişisel hesap</small></span><button class="icon-button" id="theme-toggle" aria-label="Temayı değiştir">${state.theme==='dark'?'☀':'☾'}</button></div></div></aside>`; }
function card(p) {return `<button class="note-card" data-page="${p.id}"><div class="card-cover ${p.cover==='none'?'sage':esc(p.cover)}"><span>${esc(p.icon)}</span></div><div class="card-body"><h3>${esc(p.title||'Başlıksız')}</h3><p>${esc(p.blocks.map(blockText).find(Boolean)?.slice(0,95)||'Yeni fikirlerin için boş bir sayfa.')}</p><small>${formatDate(p.updated_at)} <span>${p.favorite?'★':''}</span></small></div></button>`;}
function home() {
 const notes=active().slice().sort((a,b)=>new Date(b.updated_at)-new Date(a.updated_at));
 return `<section class="home-wrap quiet-home"><div class="eyebrow">KİŞİSEL ÇALIŞMA ALANI</div><h1>Düşüncelerine yer aç.</h1><p class="lead">Her şey, bir sayfayla başlar.</p><div class="recent-heading"><h2>Son sayfalar</h2><span>${notes.length} sayfa</span></div><div class="recent-list">${notes.slice(0,12).map(p=>`<button class="recent-row" data-page="${p.id}"><span class="recent-icon">${esc(p.icon)}</span><span class="recent-title">${esc(p.title||'Başlıksız')}</span><small>${formatDate(p.updated_at)}</small><span class="recent-arrow">↗</span></button>`).join('')||'<p class="quiet">Çalışma alanı menüsünden yeni bir sayfa açabilirsin.</p>'}</div></section>`;
}
function blockHTML(b,index) {
 const controls='<div class="block-controls"><button draggable="true" data-block-menu="'+b.id+'" title="Blok seçenekleri; sürükleyerek taşı" aria-label="Blok seçenekleri">⠿</button></div>';
 const wrapper='<div class="block block-'+b.type+' '+(b.checked?'checked':'')+' '+(advancedTypes.has(b.type)?'block-widget':'')+'" data-block="'+b.id+'" data-width="'+(b.width||'full')+'" data-tone="'+(b.tone||'default')+'" style="text-align:'+(b.align||'left')+'">';
 if(b.type==='divider')return wrapper+controls+'<hr></div>';
 if(advancedTypes.has(b.type))return wrapper+controls+'<div class="widget-body">'+studio.render(b)+'</div></div>';
 const marker=b.type==='todo'?'<input type="checkbox" class="todo-check" '+(b.checked?'checked':'')+' aria-label="Tamamlandı">':b.type==='bullet'?'<span class="bullet">•</span>':b.type==='number'?'<span class="bullet">'+(page().blocks.slice(0,index+1).filter(x=>x.type==='number').length)+'.</span>':b.type==='callout'?'<span class="callout-icon">💡</span>':'';
 const input='<div contenteditable="true" role="textbox" aria-multiline="true" aria-label="'+types[b.type][1]+'" class="block-input" data-placeholder="'+(b.type==='text'?'Yazmaya başla…':types[b.type][1])+'" spellcheck="true">'+esc(b.text)+'</div>';
 if(b.type==='toggle')return wrapper+controls+'<details open><summary>'+input+'</summary><textarea class="toggle-details" rows="3" placeholder="Ayrıntıları buraya yaz…" aria-label="Açılır not ayrıntısı">'+esc(b.details||'')+'</textarea></details></div>';
 return wrapper+controls+marker+input+'</div>';
}
function editor() {
 const p=page();if(!p || p.deleted_at)return '<div class="empty-state">Sayfa bulunamadı.</div>';
 const kids=active().filter(c=>c.parent_id===p.id);
 return `${p.cover!=='none'?`<div class="page-cover ${esc(p.cover)}"></div>`:''}<article class="editor clean-editor"><textarea id="page-title" rows="1" maxlength="255" placeholder="Başlıksız" aria-label="Sayfa başlığı">${esc(p.title==='Başlıksız'?'':p.title)}</textarea><div id="blocks">${p.blocks.map(blockHTML).join('')}</div>${kids.length?`<div class="child-pages"><h3>Alt sayfalar</h3>${kids.map(c=>`<button data-page="${c.id}">${esc(c.icon)} ${esc(c.title||'Başlıksız')} <span>↗</span></button>`).join('')}</div>`:''}</article>`;
}
function wordCount(p) {return p.blocks.map(blockText).join(' ').trim().split(/\s+/u).filter(Boolean).length;}
function listing() {const trash=state.view==='trash';const list=state.pages.filter(p=>trash?p.deleted_at:p.favorite&&!p.deleted_at);return `<div class="list-wrap"><div class="eyebrow">KİŞİSEL ALANIM</div><h1>${trash?'Çöp kutusu':'Favoriler'}</h1><p class="lead">${trash?'Silinen sayfaların burada. İstediğin zaman geri yükleyebilirsin.':'Tekrar dönmek istediğin düşünceler, bir arada.'}</p>${list.length?(trash?`<div class="trash-list">${list.map(p=>`<div><span class="trash-icon">${esc(p.icon)}</span><strong>${esc(p.title||'Başlıksız')}</strong><small>${formatDate(p.deleted_at)}</small><button class="button" data-restore="${p.id}">Geri yükle</button><button class="icon-button danger-text" data-purge="${p.id}" aria-label="Kalıcı sil">×</button></div>`).join('')}</div>`:`<div class="cards">${list.map(card).join('')}</div>`):`<div class="empty-state"><span>${trash?'♲':'☆'}</span><h2>${trash?'Burada her şey temiz.':'Önemli düşüncelerini yakınında tut.'}</h2><p>${trash?'Sildiğin sayfalar burada görünecek.':'Bir sayfanın sağ üstündeki yıldıza dokun.'}</p></div>`}</div>`;}
function render() {
 const p=page();if(p)historyFor(p);app.innerHTML=`${sidebar()}<div class="sidebar-scrim"></div><main><header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" id="mobile-menu" aria-label="Menüyü aç">☰</button><span class="breadcrumb-root">Kişisel alanım</span><span class="slash">/</span><span>${state.view==='page'?esc(p?.icon)+' '+esc(p?.title||'Başlıksız'):({home:'Tüm sayfalar',football:'Futbol',dnd:'D&D',favorites:'Favoriler',trash:'Çöp kutusu'}[state.view])}</span></div><div class="top-actions"><button id="save-status" class="save-status"></button>${state.view==='page'?`<button class="menu-trigger" id="insert-menu" aria-label="İçerik ekle menüsü">Ekle <span>⌄</span></button><button class="icon-button ${p?.favorite?'starred':''}" id="favorite-page" aria-label="${p?.favorite?'Favorilerden çıkar':'Favorilere ekle'}">${p?.favorite?'★':'☆'}</button><button class="icon-button" id="page-menu" aria-label="Sayfa işlemleri">•••</button>`:'<span class="private-label">♧ Özel</span>'}</div></header>${state.view==='dnd'?dnd.view():state.view==='football'?football.view():state.view==='home'?home():state.view==='page'?editor():listing()}</main>`;
 status();bindCommon();if(state.view==='page')bindEditor();if(state.view==='football'){football.bind();football.load();}if(state.view==='dnd'){dnd.bind();dnd.load();}
}
function bindCommon() {
 app.querySelectorAll('[data-view]').forEach(el=>el.onclick=()=>navigate(el.dataset.view));
 app.querySelectorAll('[data-page]').forEach(el=>el.onclick=()=>navigate('page',el.dataset.page));
 app.querySelectorAll('[data-new]').forEach(el=>el.onclick=()=>createPage());
 app.querySelectorAll('[data-child]').forEach(el=>el.onclick=()=>createPage(el.dataset.child));
 app.querySelectorAll('[data-toggle]').forEach(el=>el.onclick=()=>{const id=el.dataset.toggle;state.collapsed.has(id)?state.collapsed.delete(id):state.collapsed.add(id);refreshSidebar();});
 app.querySelectorAll('[data-starter]').forEach(el=>el.onclick=()=>createStarter(el.dataset.starter));
 app.querySelectorAll('[data-template]').forEach(el=>el.onclick=()=>createTemplate(el.dataset.template));
 app.querySelectorAll('[data-task]').forEach(el=>el.onchange=()=>{const p=state.pages.find(p=>p.id===el.dataset.owner);p.blocks.find(b=>b.id===el.dataset.task).checked=el.checked;dirty(p);});
 app.querySelectorAll('[data-restore]').forEach(el=>el.onclick=()=>mutate('restore',el.dataset.restore));
 app.querySelectorAll('[data-purge]').forEach(el=>el.onclick=()=>confirmAction('Kalıcı olarak sil?', 'Bu sayfa ve alt sayfaları geri alınamayacak şekilde silinecek.',()=>mutate('purge',el.dataset.purge)));
 app.querySelector('[data-pwa-open]').onclick=()=>pwa.open();
 $('#workspace-menu').onclick=workspaceMenu;$('#search-open').onclick=openSearch;$('#settings-open').onclick=openSettings;$('#theme-toggle').onclick=toggleTheme;$('#save-status').onclick=()=>{if(state.error)showRecovery();};
 $('#mobile-menu').onclick=()=>document.body.classList.toggle('sidebar-open');$('.sidebar-scrim').onclick=()=>document.body.classList.remove('sidebar-open');
}
function refreshSidebar() {const old=$('.sidebar');old.outerHTML=sidebar();bindCommon();}
let openingBlank=null;
async function openBlank(){if(openingBlank)return openingBlank;openingBlank=(async()=>{await flush();const blank=active().find(p=>!p.parent_id&&(!p.title||p.title==='Başlıksız')&&p.cover==='none'&&!p.favorite&&p.blocks.length===1&&p.blocks[0].type==='text'&&!p.blocks[0].text);if(blank)await navigate('page',blank.id);else await createPage();})();try{await openingBlank;}catch(e){toast(e.message);}finally{openingBlank=null;}}
async function createPage(parent=null,title='Başlıksız',blocks=[newBlock()]) {try{await flush();const {page:p}=await api('create',{parent_id:parent,title,blocks});state.pages.push(p);if(parent)state.collapsed.delete(parent);await navigate('page',p.id);$('#page-title').focus();$('#page-title').select();}catch(e){toast(e.message);}}
async function createTemplate(type) {
 const templates={daily:{title:`Günlük · ${new Date().toLocaleDateString('tr-TR',{day:'numeric',month:'long'})}`,blocks:[newBlock('callout','Bugün kendime biraz zaman ayırıyorum.'),newBlock('h2','Bugünün odağı'),newBlock('todo',''),newBlock('h2','Aklımdan geçenler'),newBlock(),newBlock('h2','İyi ki…'),newBlock()]},project:{title:'Yeni projem',blocks:[newBlock('h2','Neyi başarmak istiyorum?'),newBlock(),newBlock('h2','Yol haritası'),newBlock('todo','İlk adımı belirle'),newBlock('todo','Kaynakları bir araya getir'),newBlock('h2','Fikirler ve notlar'),newBlock()]},reading:{title:'Okuma notları',blocks:[newBlock('h2','Kitap ve yazar'),newBlock(),newBlock('h2','Aklımda kalanlar'),newBlock('bullet',''),newBlock('h2','Bir alıntı'),newBlock('quote',''),newBlock('h2','Kendi düşüncelerim'),newBlock()]}};
 const t=templates[type];await createPage(null,t.title,t.blocks);
}
async function mutate(action,id) {try{await flush();await api(action,{id});await reload();state.current=null;state.view=action==='trash'?'home':'trash';render();toast(action==='restore'?'Sayfa geri yüklendi.':action==='trash'?'Sayfa çöp kutusuna taşındı.':'Sayfa kalıcı olarak silindi.');}catch(e){toast(e.message);}}
function createStarter(type){const starters={visual:['Görsel düşünceler',[newBlock('callout','Fikirlerini çiz, şekillerle anlat, aralarındaki bağlantıları keşfet.'),newBlock('diagram'),newBlock('h2','Serbest alan'),newBlock('drawing')]],plan:['Proje çalışma alanım',[newBlock('h2','Genel bakış'),newBlock('table'),newBlock('h2','İlerleme panosu'),newBlock('board')]],collect:['İlham panom',[newBlock('callout','Fotoğrafları sürükleyip bırak veya ekran görüntülerini yapıştır.'),newBlock('image'),newBlock('bookmark'),newBlock('video')]],meeting:['Toplantı notları',[newBlock('h2','Gündem'),newBlock('number',''),newBlock('h2','Notlar ve kararlar'),newBlock('table'),newBlock('h2','Sonraki adımlar'),newBlock('todo',''),newBlock('toggle','Ek ayrıntılar')]]};const [title,blocks]=starters[type];createPage(null,title,blocks);}
function toggleTheme(){state.theme=state.theme==='light'?'dark':'light';document.documentElement.dataset.theme=state.theme;localStorage.setItem('not-theme',state.theme);$('#theme-toggle').textContent=state.theme==='dark'?'☀':'☾';}
function bindEditor() {
 const title=$('#page-title');const fit=()=>{title.style.height='auto';title.style.height=`${title.scrollHeight}px`;};fit();title.oninput=()=>{page().title=title.value;fit();dirty();$('.breadcrumb > span:last-child').textContent=`${page().icon} ${title.value||'Başlıksız'}`;};title.onblur=()=>{const label=$('.tree-page[data-page="'+page().id+'"] span:last-child');if(label)label.textContent=page().title||'Başlıksız';};
 $('#favorite-page').onclick=()=>{page().favorite=!page().favorite;dirty();const b=$('#favorite-page');b.textContent=page().favorite?'★':'☆';b.classList.toggle('starred',page().favorite);b.setAttribute('aria-label',page().favorite?'Favorilerden çıkar':'Favorilere ekle');};
 $('#page-menu').onclick=pageMenu;
 $('#insert-menu').onclick=()=>blockMenu(null,selectedIndex()+1);
 bindBlocks();updateUndo();
 const blocks=$('#blocks');blocks.onclick=e=>{if(e.target!==blocks)return;const last=page().blocks.at(-1);if(last?.type==='text'&&!last.text)focusBlock(page().blocks.length-1);else insertBlock(page().blocks.length);};blocks.ondragover=e=>{e.preventDefault();};blocks.ondrop=e=>{e.preventDefault();if(e.dataTransfer.files.length)studio.addFiles([...e.dataTransfer.files]);};

}
function bindBlocks() {
 $('#blocks').querySelectorAll('[data-block]').forEach(row=>{
  const b=page().blocks.find(x=>x.id===row.dataset.block);
  row.onfocusin=()=>{selectedBlock=b.id;};
  row.onclick=()=>{selectedBlock=b.id;};
  const handle=$('[data-block-menu]',row);handle.ondragstart=e=>{e.dataTransfer.setData('application/x-not-block',b.id);e.dataTransfer.effectAllowed='move';};
  row.ondragover=e=>{e.preventDefault();e.stopPropagation();row.classList.add('drop-target');};
  row.ondragleave=()=>row.classList.remove('drop-target');
  row.ondrop=e=>{e.preventDefault();e.stopPropagation();row.classList.remove('drop-target');if(e.dataTransfer.files.length){studio.addFiles([...e.dataTransfer.files],b.id);return;}const id=e.dataTransfer.getData('application/x-not-block');const list=page().blocks,source=list.findIndex(x=>x.id===id);if(source<0||id===b.id)return;const item=list.splice(source,1)[0],at=list.indexOf(b)+(e.clientY>row.getBoundingClientRect().top+row.clientHeight/2?1:0);list.splice(at,0,item);dirty();renderBlocks();};
  const input=$('.block-input',row);
  $('[data-block-menu]',row).onclick=()=>blockMenu(b.id);
  studio.bind(row,b);
  const details=$('.toggle-details',row);if(details)details.oninput=()=>{b.details=details.value;dirty();};
  const check=$('.todo-check',row);if(check)check.onchange=()=>{b.checked=check.checked;row.classList.toggle('checked',b.checked);dirty();};
  if(!input)return;
  input.oninput=()=>{b.text=input.innerText.replace(/\r/g,'');dirty();if($('#word-count'))$('#word-count').textContent=`${wordCount(page())} kelime`;};
  input.onpaste=e=>{if(e.clipboardData.files.length){e.preventDefault();studio.addFiles([...e.clipboardData.files],b.id);return;}e.preventDefault();const text=e.clipboardData.getData('text/plain');const sel=getSelection();if(!sel.rangeCount)return;const range=sel.getRangeAt(0);range.deleteContents();const node=document.createTextNode(text);range.insertNode(node);range.setStartAfter(node);range.collapse(true);sel.removeAllRanges();sel.addRange(range);input.dispatchEvent(new Event('input'));};
  input.onkeydown=e=>{
   if(e.key==='/'&&!b.text){e.preventDefault();blockMenu(b.id);}
   if(e.key==='Enter'&&!e.shiftKey&&b.type!=='code'&&!e.isComposing){e.preventDefault();const selection=getSelection();const range=selection.rangeCount?selection.getRangeAt(0):null;let trailing='';if(range){range.deleteContents();const rest=document.createRange();rest.selectNodeContents(input);rest.setStart(range.startContainer,range.startOffset);trailing=rest.toString();rest.deleteContents();b.text=input.innerText;}insertBlock(page().blocks.indexOf(b)+1,['todo','bullet'].includes(b.type)?b.type:'text',trailing);}
   if(e.key==='Backspace'&&!b.text&&page().blocks.length>1){e.preventDefault();const index=page().blocks.indexOf(b);page().blocks.splice(index,1);dirty();renderBlocks();focusBlock(Math.max(index-1,0));}
   if(e.altKey&&['ArrowUp','ArrowDown'].includes(e.key)){e.preventDefault();moveBlock(b.id,e.key==='ArrowUp'?-1:1);}
  };
 });
}
function renderBlocks() {if(!$('#blocks'))return;$('#blocks').innerHTML=page().blocks.map(blockHTML).join('');bindBlocks();if($('#word-count'))$('#word-count').textContent=`${wordCount(page())} kelime`;}
function focusBlock(index) {const row=$('#blocks').children[index];const input=row?.querySelector('.block-input');input?.focus();if(!input)row?.scrollIntoView({behavior:'smooth',block:'center'});if(input){const r=document.createRange();r.selectNodeContents(input);r.collapse(false);const s=getSelection();s.removeAllRanges();s.addRange(r);}}
function insertBlock(index,type='text',text='') {const b=newBlock(type,text);page().blocks.splice(index,0,b);selectedBlock=b.id;dirty();renderBlocks();focusBlock(index);if(['image','video','audio','file','bookmark','drawing','diagram'].includes(type))studio.configure(b);}
function moveBlock(id,direction) {const a=page().blocks;const i=a.findIndex(b=>b.id===id),j=i+direction;if(j<0||j>=a.length)return;[a[i],a[j]]=[a[j],a[i]];dirty();renderBlocks();focusBlock(j);}
function blockMenu(id=null,insertAt=null) {
 const current=id?page().blocks.find(x=>x.id===id):null;
 modal(current?'Blok seçenekleri':'Ne eklemek istersin?', '<input class="palette-search" type="search" id="block-filter" placeholder="Tablo, fotoğraf, video, çizim…" aria-label="Blok türü ara"><div class="block-menu">'+Object.entries(types).map(([key,[symbol,name]])=>'<button data-type="'+key+'"><span>'+symbol+'</span><div>'+name+'<small>'+({table:'Düzenlenebilir hücreler',image:'Yükle veya bağlantı ekle',video:'YouTube, Vimeo veya MP4',drawing:'Kalem ve şekiller',diagram:'Birbirine bağlı fikirler',board:'Görevlerini sütunlarda düzenle',toggle:'Ayrıntıları aç / kapat',toc:'Başlıklara hızlı erişim'}[key]||'Sayfana bir blok ekle')+'</small></div></button>').join('')+'</div>'+(current?'<div class="block-layout"><label>Yerleşim<select id="block-width"><option value="full">Tam satır</option><option value="half">Yarım satır · yan yana</option></select></label><label>Hizalama<select id="block-align"><option value="left">Sol</option><option value="center">Orta</option><option value="right">Sağ</option></select></label><label>Renk<select id="block-tone">'+[['default','Varsayılan'],['sage','Arduvaz'],['sand','Kum'],['lavender','Lavanta'],['blue','Mavi'],['rose','Pudra']].map(([v,l])=>'<option value="'+v+'">'+l+'</option>').join('')+'</select></label></div><div class="modal-actions wrap"><button class="button" id="block-up">↑ Yukarı</button><button class="button" id="block-down">↓ Aşağı</button><button class="button" id="block-copy">⧉ Kopyala</button><button class="button danger-text" id="block-delete">Sil</button></div>':''),()=>{
 $('#block-filter').oninput=e=>dialog.querySelectorAll('[data-type]').forEach(btn=>btn.hidden=!btn.textContent.toLocaleLowerCase('tr').includes(e.target.value.toLocaleLowerCase('tr')));
 dialog.querySelectorAll('[data-type]').forEach(btn=>btn.onclick=()=>{dialog.close();if(current){const text=blockText(current);Object.assign(current,defaults(btn.dataset.type,uid),{type:btn.dataset.type,text});dirty();renderBlocks();if(advancedTypes.has(current.type))studio.configure(current);else focusBlock(page().blocks.indexOf(current));}else insertBlock(insertAt??page().blocks.length,btn.dataset.type);});
 if(current){
  for(const [selector,key] of [['#block-width','width'],['#block-align','align'],['#block-tone','tone']]){const el=$(selector);el.value=current[key]||({width:'full',align:'left',tone:'default'}[key]);el.onchange=()=>{current[key]=el.value;dirty();renderBlocks();};}
  $('#block-up').onclick=()=>{dialog.close();moveBlock(id,-1);};$('#block-down').onclick=()=>{dialog.close();moveBlock(id,1);};
  $('#block-copy').onclick=()=>{const copy=structuredClone(current);copy.id=uid();page().blocks.splice(page().blocks.indexOf(current)+1,0,copy);dirty();dialog.close();renderBlocks();};
  $('#block-delete').onclick=()=>{dialog.close();page().blocks=page().blocks.filter(x=>x.id!==id);if(!page().blocks.length)page().blocks.push(newBlock());dirty();renderBlocks();};
 }$('#block-filter').focus();
 });}
function iconPicker(){modal('Sayfana bir simge seç',`<div class="icon-grid">${icons.map(i=>`<button>${i}</button>`).join('')}</div>`,()=>dialog.querySelectorAll('.icon-grid button').forEach(b=>b.onclick=()=>{page().icon=b.textContent;dirty();dialog.close();render();}));}
function coverPicker(){modal('Bir renk, biraz ilham',`<div class="cover-grid">${['none','sage','sand','lavender','blue','rose'].map((c,i)=>`<button class="${c}" data-cover="${c}">${['Kapaksız','Arduvaz','Kum','Lavanta','Gökyüzü','Pudra'][i]}</button>`).join('')}</div>`,()=>dialog.querySelectorAll('[data-cover]').forEach(b=>b.onclick=()=>{page().cover=b.dataset.cover;dirty();dialog.close();render();}));}
function movePage(){const p=page();const descendants=new Set([p.id]);let size;do{size=descendants.size;active().forEach(n=>{if(descendants.has(n.parent_id))descendants.add(n.id);});}while(size!==descendants.size);modal('Sayfayı taşı',`<label class="field-label" for="parent-select">Yeni konum</label><select id="parent-select"><option value="">Kişisel alanım</option>${active().filter(n=>!descendants.has(n.id)).map(n=>`<option value="${n.id}" ${p.parent_id===n.id?'selected':''}>${esc(n.icon)} ${esc(n.title)}</option>`).join('')}</select><div class="modal-actions"><button class="button primary" id="move-confirm">Taşı</button></div>`,()=>{$('#move-confirm').onclick=async()=>{const previous=p.parent_id;p.parent_id=$('#parent-select').value||null;dirty();dialog.close();try{await flush();render();}catch(e){p.parent_id=previous;toast(e.message);}};});}
function pageMenu(){modal('Sayfa işlemleri',`<div class="page-menu-meta"><span id="word-count">${wordCount(page())} kelime</span><span>${formatDate(page().updated_at)}</span></div><div class="menu-list"><button id="selected-block-options">Seçili bloğu düzenle</button><button id="undo-edit">Geri al <kbd>⌘ Z</kbd></button><button id="redo-edit">İleri al <kbd>⇧ ⌘ Z</kbd></button><button id="focus-mode">${document.body.classList.contains('focus-mode')?'Odak modundan çık':'Odak modu'}</button><div class="menu-divider"></div><button id="icon-picker">Sayfa simgesi</button><button id="cover-picker">Sayfa kapağı</button><button id="move-page">Sayfayı taşı</button><button id="add-child-page">Alt sayfa oluştur</button><button id="duplicate">⧉ Sayfanın kopyasını oluştur</button><button id="export-md">↓ Markdown olarak indir</button><button id="export-json">↓ Tüm notları yedekle</button><button class="danger-text" id="trash-page">♲ Çöp kutusuna taşı</button></div>`,()=>{
 const closeThen=fn=>()=>{dialog.close();fn();};
 $('#selected-block-options').disabled=!page().blocks.length;$('#selected-block-options').onclick=closeThen(()=>blockMenu(page().blocks[selectedIndex()]?.id));
 $('#undo-edit').onclick=closeThen(()=>stepHistory(-1));$('#redo-edit').onclick=closeThen(()=>stepHistory(1));updateUndo();
 $('#focus-mode').onclick=closeThen(()=>document.body.classList.toggle('focus-mode'));
 $('#icon-picker').onclick=closeThen(iconPicker);$('#cover-picker').onclick=closeThen(coverPicker);$('#move-page').onclick=closeThen(movePage);$('#add-child-page').onclick=closeThen(()=>createPage(page().id));
 $('#duplicate').onclick=()=>{const p=page();dialog.close();createPage(p.parent_id,`${p.title} (kopya)`,p.blocks.map(b=>({...b,id:uid()})));};
 $('#export-md').onclick=()=>{const p=page();const md='# '+p.title+'\n\n'+p.blocks.map(b=>studio.markdown(b)??(({h1:'# ',h2:'## ',bullet:'- ',number:'1. ',todo:'- ['+(b.checked?'x':' ')+'] ',quote:'> ',callout:'> '}[b.type]||'')+(b.type==='divider'?'---':b.type==='code'?'~~~\n'+b.text+'\n~~~':b.text))).join('\n\n');download(`${p.title.replace(/[^\p{L}\p{N} _-]/gu,'').slice(0,80)||'not'}.md`,md,'text/markdown');dialog.close();};
 $('#export-json').onclick=()=>studio.fullBackup();$('#trash-page').onclick=()=>{const id=page().id;dialog.close();confirmAction('Sayfayı çöp kutusuna taşı?', 'Alt sayfalar da taşınacak. Daha sonra geri yükleyebilirsin.',()=>mutate('trash',id));};
 });}
function workspaceMenu(){modal('Çalışma alanı',`<div class="menu-list"><button id="new-blank-page">Boş sayfa <span>↗</span></button><div class="menu-divider"></div><div class="menu-label">ŞABLONLAR</div><button data-template="daily">Günlük</button><button data-template="project">Proje</button><button data-template="reading">Okuma notları</button><button data-starter="visual">Çizim ve diyagram</button><button data-starter="plan">Tablo ve görev panosu</button><button data-starter="collect">İlham panosu</button><button data-starter="meeting">Toplantı notu</button></div>`,()=>{
 $('#new-blank-page').onclick=()=>{dialog.close();createPage();};
 dialog.querySelectorAll('[data-template]').forEach(el=>el.onclick=()=>{dialog.close();createTemplate(el.dataset.template);});
 dialog.querySelectorAll('[data-starter]').forEach(el=>el.onclick=()=>{dialog.close();createStarter(el.dataset.starter);});
});}
function openSearch(){modal('Çalışma alanında ara',`<input id="search-input" type="search" placeholder="Bir başlık veya düşünce ara…" autocomplete="off" aria-label="Notlarda ara"><div id="search-results"></div>`,()=>{const input=$('#search-input');const show=()=>{const q=input.value.toLocaleLowerCase('tr');const hits=active().filter(p=>(p.title+' '+p.blocks.map(blockText).join(' ')).toLocaleLowerCase('tr').includes(q)).slice(0,30);$('#search-results').innerHTML=hits.map(p=>`<button data-result="${p.id}"><span>${esc(p.icon)}</span><div><strong>${esc(p.title||'Başlıksız')}</strong><small>${esc(p.blocks.find(b=>blockText(b).toLocaleLowerCase('tr').includes(q))?.text.slice(0,100)||'Sayfayı aç')}</small></div><span>↗</span></button>`).join('')||'<p class="quiet">Bir sonuç bulunamadı.</p>';dialog.querySelectorAll('[data-result]').forEach(b=>b.onclick=()=>{dialog.close();navigate('page',b.dataset.result);});};input.oninput=show;show();input.focus();});}
function openSettings(){modal('Ayarlar ve yedekleme',`<div class="settings-account"><span class="avatar">${esc(state.username.slice(0,1))}</span><div><strong>${esc(state.username)}</strong><p>Tek kullanıcıya ait özel çalışma alanı</p></div></div><div class="menu-list"><button id="backup-all">↓ Tüm sayfaları JSON olarak indir</button><button id="import-backup">↑ JSON yedeğinden sayfa ekle</button><button id="media-library">▧ Dosya kütüphanem</button><button id="change-password">♧ Şifremi değiştir</button><button id="switch-theme">◐ Açık / koyu görünüm</button><button id="logout" class="danger-text">↗ Güvenli çıkış</button></div><p class="small-note">Yedek dosyası notlarının tamamını içerir. Kendine ait güvenli bir yerde sakla. İçe aktarılan notlar yeni kopyalar olarak eklenir.</p><input type="file" id="backup-file" accept="application/json,.json" hidden>`,()=>{
 $('#media-library').onclick=()=>{dialog.close();studio.library();};$('#change-password').onclick=()=>{dialog.close();changePassword();};$('#backup-all').onclick=()=>studio.fullBackup();$('#switch-theme').onclick=toggleTheme;$('#logout').onclick=async()=>{try{await flush();await api('logout',{});location.reload();}catch{dialog.close();showRecovery();}};$('#import-backup').onclick=()=>$('#backup-file').click();$('#backup-file').onchange=async e=>{const file=e.target.files[0];if(!file)return;try{const result=await studio.importBackup(file);dialog.close();await reload();state.view='home';state.current=null;render();toast(`${result.count} sayfa yeni kopya olarak eklendi.`);}catch(err){toast(err.message);}};
 });}
function changePassword(){modal('Şifreni değiştir',`<form id="password-form" class="password-form"><label>Mevcut şifre<input name="current_password" type="password" autocomplete="current-password" required></label><label>Yeni şifre<input name="new_password" type="password" autocomplete="new-password" minlength="7" maxlength="128" required></label><label>Yeni şifreyi tekrar yaz<input name="confirm_password" type="password" autocomplete="new-password" minlength="7" maxlength="128" required></label><p>En az 7 karakter kullan. Diğer cihazlardaki oturumların kapatılır.</p><p id="password-error" class="danger-text" role="alert"></p><div class="modal-actions"><button type="submit" class="button primary">Şifreyi güncelle</button></div></form>`,()=>{$('#password-form').onsubmit=async e=>{e.preventDefault();const data=Object.fromEntries(new FormData(e.target));if(data.new_password!==data.confirm_password){$('#password-error').textContent='Yeni şifreler eşleşmiyor.';return;}const button=$('button[type=submit]',e.target);button.disabled=true;try{await flush();const result=await api('password',data);state.csrf=result.csrf;dialog.close();toast('Şifren güncellendi.');}catch(err){$('#password-error').textContent=err.message;button.disabled=false;}};});}
function authScreen(setup=false,error='') {app.innerHTML=`<div class="auth-layout"><div class="auth-story"><span class="brand-mark">n.</span><div><div class="eyebrow">KENDİNE AİT BİR YER</div><h1>Düşüncelerini<br>burada bırak.</h1><p>Fikirlerin, planların, küçük hatırlatmaların.<br>Sana özel, sade bir çalışma alanı.</p><div class="auth-decoration">✳</div></div><small>Not · Kişisel çalışma alanın</small></div><div class="auth-panel"><form id="auth-form"><span class="auth-icon">✦</span><h2>${setup?'Alanını oluşturalım.':'Tekrar hoş geldin.'}</h2><p>${setup?'Bu kurulum yalnızca bir kez yapılır.':'Kaldığın yerden devam etmek için giriş yap.'}</p>${setup?'<label>Kurulum anahtarı<input name="setup_key" type="password" required autocomplete="off"></label>':''}<label>Kullanıcı adı<input name="username" required minlength="2" maxlength="100" autocomplete="username" autofocus></label><label>Şifre<input name="password" type="password" required ${setup?'minlength="7"':''} maxlength="128" autocomplete="${setup?'new-password':'current-password'}"></label>${setup?'<small>En az 7 karakterlik bir şifre belirle.</small>':''}<p class="form-error" role="alert">${esc(error)}</p><button class="button dark" type="submit">${setup?'Çalışma alanımı oluştur':'Çalışma alanıma gir'} <span>→</span></button><div class="auth-private">♧ Yalnızca senin erişimin var.</div><button class="subtle-button" type="button" data-pwa-open>↧ Uygulama olarak kullan</button></form></div></div>`;
 $('[data-pwa-open]').onclick=()=>pwa.open();
 $('#auth-form').onsubmit=async e=>{e.preventDefault();const btn=$('button[type=submit]',e.target);btn.disabled=true;try{const data=Object.fromEntries(new FormData(e.target));const result=await api(setup?'setup':'login',data);state.csrf=result.csrf;await start();}catch(err){$('.form-error').textContent=err.message;btn.disabled=false;}};
}
async function start(){try{const s=await api('status');state.csrf=s.csrf;if(!s.authenticated){authScreen(s.setup);return;}state.username=s.username;await reload();const target=location.hash.slice(1);if(['football','dnd'].includes(target)){state.view=target;state.current=null;render();}else if(active().some(p=>p.id===target)){state.current=target;state.view='page';render();}else{await openBlank();}}catch(e){if(e.body?.configured===false){app.innerHTML='<div class="setup-needed"><span class="brand-mark">n.</span><h1>Çalışma alanın hazırlanıyor.</h1><p>Hostinger veritabanı bağlantısı henüz tanımlanmadı.</p><ol><li>hPanel’de bir MySQL veritabanı oluştur.</li><li><code>private/config.example.php</code> dosyasını <code>private/config.php</code> olarak kopyala.</li><li>Veritabanı bilgilerini ve kurulum anahtarını bu dosyaya yaz.</li><li>Bu sayfayı yenile ve kendi hesabını oluştur.</li></ol><button class="button dark" id="reload">Tekrar kontrol et</button></div>';$('#reload').onclick=()=>location.reload();}else{app.innerHTML=`<div class="setup-needed"><h1>Bağlantı kurulamadı.</h1><p>${esc(e.message)}</p><button class="button" id="reload">Tekrar dene</button></div>`;$('#reload').onclick=()=>location.reload();}}}
document.addEventListener('keydown',e=>{if((e.metaKey||e.ctrlKey)&&e.key==='k'){e.preventDefault();if(state.username)openSearch();}if((e.metaKey||e.ctrlKey)&&e.key==='s'){e.preventDefault();flush().catch(showRecovery);}});
window.addEventListener('beforeunload',e=>{if(state.dirty.size||studio.busy()||dnd.busy()){e.preventDefault();e.returnValue='';}});
window.addEventListener('online',()=>{if(state.dirty.size||dnd.busy())flush().catch(()=>{});});
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden'&&(state.dirty.size||dnd.busy()))flush().catch(()=>{});});
dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
let selectedBlock=null,historyPaused=false;
const histories=new Map();
const snapshot=p=>JSON.stringify({title:p.title,icon:p.icon,cover:p.cover,blocks:p.blocks,favorite:p.favorite,parent_id:p.parent_id});
function historyFor(p){if(!histories.has(p.id))histories.set(p.id,{past:[],future:[],last:snapshot(p),time:0});return histories.get(p.id);}
function recordHistory(p){if(!p||historyPaused)return;const h=historyFor(p),now=Date.now(),next=snapshot(p);if(next!==h.last){if(now-h.time>650||!h.past.length){h.past.push(h.last);if(h.past.length>60)h.past.shift();}h.future=[];h.last=next;h.time=now;}updateUndo();}
function updateUndo(){const h=page()?historyFor(page()):null;if($('#undo-edit'))$('#undo-edit').disabled=!h?.past.length;if($('#redo-edit'))$('#redo-edit').disabled=!h?.future.length;}
function stepHistory(direction){const p=page(),h=historyFor(p),from=direction<0?h.past:h.future,to=direction<0?h.future:h.past;if(!from.length)return;to.push(snapshot(p));Object.assign(p,JSON.parse(from.pop()));h.last=snapshot(p);h.time=0;historyPaused=true;dirty(p);historyPaused=false;render();}
function selectedIndex(){const i=page().blocks.findIndex(b=>b.id===selectedBlock);return i<0?page().blocks.length-1:i;}
const studio=createStudio({esc,uid,getPage:page,dirty,renderBlocks,modal,toast,api,download,flush,csrf:()=>state.csrf,pages:()=>state.pages});
document.addEventListener('keydown',e=>{if(dialog.open||state.view!=='page'||!page())return;if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='z'&&!e.target.closest('input,textarea,[contenteditable]')){e.preventDefault();stepHistory(e.shiftKey?1:-1);}if(e.key==='Escape')document.body.classList.remove('focus-mode');});
const football=createFootball({api,esc,modal,toast,render,uploadFile:file=>studio.uploadFile(file),active:()=>state.view==='football'});
const dnd=createDnd({api,esc,modal,toast,render,download,active:()=>state.view==='dnd'});
const pwa=initPWA({modal,toast,flush,busy:()=>studio.busy()||state.dirty.size>0||dnd.busy()});
start();
