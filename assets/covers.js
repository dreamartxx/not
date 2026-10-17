export function createCovers({api,esc,modal,toast,render,uploadFile,flush}) {
 const $=s=>document.querySelector(s);let records={},pending=0;
 const get=scope=>records[scope]||{media_id:'',position:50};
 const url=id=>'api.php?action=media&id='+encodeURIComponent(id);
 async function load(){records=(await api('covers')).covers;}
 function image(scope,classes='workspace-cover-image'){
  const c=get(scope);return c.media_id?`<img class="${classes}" src="${esc(url(c.media_id))}" style="object-position:center ${c.position}%" alt="Çalışma alanı kapak fotoğrafı">`:'';
 }
 function banner(scope){return get(scope).media_id?`<div class="workspace-cover">${image(scope)}</div>`:'';}
 async function save(scope,cover){const r=await api('cover-save',{scope,...cover});records[scope]=r.cover;return r.cover;}
 async function edit(scope,{colorPicker,onSave}={}){
  try{await flush();await load();}catch(e){toast(e.message);return;}
  let media=get(scope).media_id,position=get(scope).position,working=false;
  modal('Kapak fotoğrafı',`<form id="cover-form" class="cover-form"><div id="cover-preview" class="cover-preview"></div><label class="cover-position">Dikey kadraj <span id="cover-position-value">${position}%</span><input id="cover-position" type="range" min="0" max="100" value="${position}"></label><div class="cover-source-actions"><label class="button cover-upload">↑ Fotoğraf yükle<input id="cover-file" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Kapak fotoğrafı yükle"></label><button type="button" class="button" id="cover-library">Kütüphaneden seç</button>${colorPicker?'<button type="button" class="button" id="cover-colors">Renkli kapaklar</button>':''}</div><p class="cover-help">JPEG, PNG, WebP veya GIF · En fazla 8 MB. Yalnızca bu alanın kapağı değişir.</p><div id="cover-library-grid" class="cover-library-grid"></div><p id="cover-error" class="form-error" role="alert"></p><div class="modal-actions"><button type="button" class="button" id="cover-remove">Kapağı kaldır</button><button type="submit" class="button primary" id="cover-save">Kaydet</button></div></form>`,()=>{
   const form=$('#cover-form'),preview=$('#cover-preview'),error=$('#cover-error'),library=$('#cover-library-grid');
   const current=()=>form.isConnected&&$('#dialog').open;
   const paint=()=>{preview.innerHTML=media?`<img src="${esc(url(media))}" alt="Kapak önizleme" style="object-position:center ${position}%">`:'<div class="cover-placeholder"><span>▧</span><strong>Alanına bir manzara ekle.</strong><small>Fotoğraf seç, kadrajını ayarla.</small></div>';$('#cover-position').disabled=!media;$('#cover-position-value').textContent=position+'%';};
   const busy=value=>{working=value;form.querySelectorAll('button,input').forEach(el=>el.disabled=value);if(!value)$('#cover-position').disabled=!media;};
   paint();$('#cover-position').oninput=e=>{position=Number(e.target.value);const img=preview.querySelector('img');if(img)img.style.objectPosition='center '+position+'%';$('#cover-position-value').textContent=position+'%';};
   $('#cover-file').onchange=async e=>{const file=e.target.files[0];if(!file)return;busy(true);pending++;error.textContent='Fotoğraf yükleniyor…';try{if(!/^image\/(jpeg|png|webp|gif)$/.test(file.type))throw Error('JPEG, PNG, WebP veya GIF görseli seç.');const m=await uploadFile(file);if(!current())return;media=m.id;error.textContent='';paint();}catch(err){if(current())error.textContent=err.message;}finally{pending--;if(current())busy(false);}};
   $('#cover-library').onclick=async()=>{busy(true);try{const r=await api('library');if(!current())return;library.innerHTML=r.media.filter(m=>m.mime.startsWith('image/')).map(m=>`<button type="button" data-cover-media="${m.id}" aria-label="${esc(m.name)}"><img src="${esc(url(m.id))}" alt="${esc(m.name)}" loading="lazy"></button>`).join('')||'<p>Kütüphanende henüz fotoğraf yok.</p>';library.querySelectorAll('[data-cover-media]').forEach(b=>b.onclick=()=>{media=b.dataset.coverMedia;library.innerHTML='';paint();});}catch(e){if(current())error.textContent=e.message;}finally{if(current())busy(false);}};
   const commit=async id=>{if(working)return;busy(true);pending++;error.textContent='';try{await save(scope,{media_id:id,position});await onSave?.();if(current()){$('#dialog').close();render();toast(id?'Kapak fotoğrafı kaydedildi.':'Fotoğraf kapağı kaldırıldı.');}}catch(e){if(current())error.textContent=e.message;}finally{pending--;if(current())busy(false);}};
   $('#cover-remove').onclick=()=>commit('');form.onsubmit=e=>{e.preventDefault();commit(media);};
   if(colorPicker)$('#cover-colors').onclick=()=>{$('#dialog').close();colorPicker();};
  });
 }
 return {load,get,image,banner,edit,save,busy:()=>pending>0,all:()=>structuredClone(records)};
}
