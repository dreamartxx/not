const {vaultKey,seal,unseal,parseOTP,otpAt,rand,b64}=await import('./vault-crypto.js'+new URL(import.meta.url).search);

export async function openVault({api,esc}){
 if(document.querySelector('.vault-dialog'))return;
 const d=document.createElement('dialog');d.className='vault-dialog';d.setAttribute('aria-labelledby','vault-title');document.body.append(d);
 d.innerHTML='<header class="modal-heading"><h2 id="vault-title">Şifre kasası</h2><button type="button" class="icon-button" aria-label="Kasayı kilitle ve kapat">×</button></header><section class="vault-body"></section><p class="vault-msg" role="status"></p>';
 const body=d.querySelector('section'),msg=d.querySelector('.vault-msg'),get=s=>body.querySelector(s);
 let alive=true,key=null,entries=[],version=0,envelope=null,salt='',idle,clock,busy=false;
 const stopClock=()=>{clearInterval(clock);clock=0;};
 function lock(){if(!alive)return;alive=false;key=null;entries=[];envelope=null;stopClock();clearTimeout(idle);window.removeEventListener('pagehide',lock);document.removeEventListener('visibilitychange',hidden);d.replaceChildren();if(d.open)d.close();d.remove();}
 function hidden(){if(document.hidden)lock();}
 function touch(){clearTimeout(idle);idle=setTimeout(lock,180000);}
 d.querySelector('header button').onclick=lock;d.oncancel=e=>{e.preventDefault();lock();};d.onclose=lock;
 d.onkeydown=e=>{e.stopPropagation();touch();};d.onpointerdown=touch;d.oninput=touch;
 window.addEventListener('pagehide',lock);document.addEventListener('visibilitychange',hidden);
 d.showModal();touch();
 const note=s=>{if(alive)msg.textContent=s;};
 function view(html){stopClock();body.innerHTML=html;note('');}
 function run(fn){return async e=>{
  e?.preventDefault();if(!alive||busy)return;busy=true;body.inert=true;note('İşleniyor…');
  try{await fn();}catch(err){if(err.status===401){lock();return;}note(err.message||'İşlem tamamlanamadı.');}
  finally{busy=false;body.inert=false;}
 };}
 const btn=(id,label,primary=false)=>'<button type="button" class="button'+(primary?' primary':'')+'" id="'+id+'">'+label+'</button>';
 async function write(next){
  const sealed=await seal(key,salt,next);if(!alive)return false;
  const saved=await api('vault',{version,envelope:sealed});if(!alive)return false;
  version=saved.version;envelope=sealed;entries=next;return true;
 }
 async function load(){
  if(!crypto.subtle)throw Error('Kasayı kullanmak için siteyi HTTPS ile aç.');
  const res=await api('vault');if(!alive)return;version=res.version;envelope=res.envelope;
  const fresh=version===0;
  view('<div class="eyebrow">KİŞİSEL KASA</div><p>'+(fresh?'Hesapların için ayrı bir kasa parolası belirle. Bu parolayı unutursan kasayı geri açamazsın.':'Kayıtlarını görmek için kasa parolanı gir.')+'</p><form><label>Kasa parolası<input name="pass" type="password" autocomplete="'+(fresh?'new-password':'current-password')+'" required '+(fresh?'minlength="12" placeholder="En az 12 karakter"':'')+'></label>'+(fresh?'<label>Kasa parolasını tekrarla<input name="again" type="password" autocomplete="new-password" required></label>':'')+'<button class="button primary">'+(fresh?'Kasa oluştur':'Kilidi aç')+'</button></form>');
  get('[name=pass]').focus();
  get('form').onsubmit=run(async()=>{
   const pass=get('[name=pass]').value;if(fresh&&pass!==get('[name=again]').value)throw Error('Kasa parolaları eşleşmiyor.');
   salt=fresh?b64(rand(16)):envelope.salt;const candidate=await vaultKey(pass,salt);if(!alive)return;
   if(fresh){key=candidate;if(!await write([]))return;}
   else{let decoded;try{decoded=await unseal(candidate,envelope);}catch{throw Error('Kasa açılamadı. Kasa parolanı kontrol et.');}
    if(!alive)return;key=candidate;entries=decoded;}
   list();
  });
 }
 function list(){
  view('<div class="vault-bar"><div><div class="eyebrow">KAYITLARIM</div><strong>'+entries.length+' hesap</strong></div>'+btn('v-add','＋ Kayıt ekle',true)+'</div><div class="menu-list vault-list">'+(entries.map((r,i)=>'<button type="button" data-entry="'+i+'"><span><strong>'+esc(r.name)+'</strong><small>'+esc(r.user||'Kullanıcı adı yok')+'</small></span><span>'+(r.otp?'TOTP · ':'')+'↗</span></button>').join('')||'<p class="quiet">Henüz kayıt yok. İlk hesabını ekleyebilirsin.</p>')+'</div>');
  get('#v-add').onclick=()=>edit(-1);body.querySelectorAll('[data-entry]').forEach(b=>b.onclick=()=>detail(Number(b.dataset.entry)));
 }
 function edit(i){
  const current=entries[i]||{};
  view('<form><label>Hesap adı<input name="name" required maxlength="100" placeholder="Örn. GitHub"></label><label>Kullanıcı adı veya e-posta<input name="user" autocomplete="off" maxlength="256"></label><label>Hesap parolası<input name="password" type="password" autocomplete="new-password" maxlength="4096"></label><label>Authenticator anahtarı (isteğe bağlı)<input name="otp" type="password" autocomplete="off" maxlength="2048" placeholder="Kurulum anahtarı veya otpauth:// bağlantısı"></label><p class="small-note">Hesabın iki adımlı doğrulama ayarlarında “Authenticator uygulaması”nı seç. QR kodunun yanındaki manuel kurulum anahtarını buraya gir.</p><div class="modal-actions">'+btn('v-back','Vazgeç')+'<button class="button primary">Kaydet</button></div></form>');
  const form=get('form');for(const field of ['name','user','password','otp'])form.elements.namedItem(field).value=current[field]||'';
  get('#v-back').onclick=()=>i<0?list():detail(i);form.elements.namedItem('name').focus();
  form.onsubmit=run(async()=>{const item=Object.fromEntries(new FormData(form));item.name=item.name.trim();item.otp=item.otp.trim();
   if(!item.name||(!item.password&&!item.otp))throw Error('Hesap adı ve en az bir parola veya Authenticator anahtarı gir.');
   if(item.otp)parseOTP(item.otp);const next=entries.slice();const idx=i<0?next.length:i;next[idx]=item;if(await write(next)){detail(idx);note('Kayıt saklandı.');}
  });
 }
 function detail(i){
  const r=entries[i];view('<div class="eyebrow">HESAP</div><h3>'+esc(r.name)+'</h3><p>'+esc(r.user)+'</p>'+(r.password?'<label>Parola<input type="password" readonly id="v-secret" autocomplete="off"></label><div class="vault-actions">'+btn('v-show','Göster')+btn('v-copy','Parolayı kopyala')+'</div>':'')+(r.otp?'<div class="vault-otp"><div class="eyebrow">AUTHENTICATOR</div><output id="v-code">··· ···</output><small id="v-time"></small>'+btn('v-copy-code','Kodu kopyala')+'</div>':'')+'<div class="vault-actions">'+btn('v-back','Kayıtlar')+btn('v-edit','Düzenle')+btn('v-delete','Sil')+'</div>');
  const copy=async text=>{try{await navigator.clipboard.writeText(text);note('Panoya kopyalandı.');}catch{note('Panoya erişilemedi. Alanı seçip elle kopyalayabilirsin.');}};
  if(r.password){const p=get('#v-secret');p.value=r.password;get('#v-show').onclick=e=>{p.type=p.type==='password'?'text':'password';e.target.textContent=p.type==='password'?'Göster':'Gizle';};get('#v-copy').onclick=()=>copy(r.password);}
  if(r.otp){const cfg=parseOTP(r.otp),out=get('#v-code'),time=get('#v-time');let slot=-1;
   const tick=async()=>{const sec=Math.floor(Date.now()/1000),n=Math.floor(sec/cfg.period);time.textContent=(cfg.period-sec%cfg.period)+' sn sonra yenilenir';
    if(n!==slot){slot=n;try{const code=await otpAt(cfg,sec);if(out.isConnected&&slot===n)out.textContent=code;}catch{out.textContent='Kod üretilemedi';}}};
   tick();clock=setInterval(tick,350);get('#v-copy-code').onclick=async()=>{try{await copy(await otpAt(cfg,Math.floor(Date.now()/1000)));}catch{note('Kod üretilemedi.');}};
  }
  get('#v-back').onclick=list;get('#v-edit').onclick=()=>edit(i);get('#v-delete').onclick=()=>{view('<p><strong>'+esc(r.name)+'</strong> kaydı kasadan silinsin mi? Bu işlem geri alınamaz.</p><div class="modal-actions">'+btn('v-back','Vazgeç')+btn('v-confirm','Kaydı sil')+'</div>');get('#v-back').onclick=()=>detail(i);get('#v-confirm').onclick=run(async()=>{if(await write(entries.filter((_,j)=>j!==i)))list();});};
 }
 note('Kasa yükleniyor…');await run(load)();
}
