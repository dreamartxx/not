export function initPWA({modal, toast, flush, busy}) {
  let promptEvent = null, registration = null, applying = false, unavailable = false;
  const standalone = matchMedia('(display-mode: standalone)');
  const installed = () => standalone.matches || navigator.standalone === true;
  const notices = document.createElement('div');
  notices.className = 'pwa-notices';
  notices.setAttribute('aria-live', 'polite');
  document.body.append(notices);
  const offline = document.createElement('div');
  offline.className = 'pwa-notice offline-notice';
  offline.textContent = 'Bağlantı yok. Açık nottaki değişiklikler bağlantı gelince kaydedilir. Kaydetmeden bu pencereyi kapatma.';
  const update = document.createElement('div');
  update.className = 'pwa-notice';
  update.innerHTML = '<span>Not’un yeni sürümü hazır.</span><button class="button" type="button">Güncellemeyi yükle</button>';
  notices.append(offline, update);
  function paint() {
    offline.hidden = navigator.onLine;
    update.hidden = !registration?.waiting || applying;
    document.documentElement.classList.toggle('installed-app', installed());
  }
  async function applyUpdate() {
    if (applying || !registration?.waiting) return;
    if (busy()) { toast('Önce açık çizimini kaydet ve tüm değişikliklerin kaydedilmesini bekle.'); return; }
    applying = true;
    const button = update.querySelector('button');
    button.disabled = true;
    try {
      await flush();
      if (busy()) throw new Error('Kaydedilmeyen çalışma var. Kaydettikten sonra tekrar dene.');
      const worker = registration.waiting;
      // No tab reloads automatically. The next safe reload picks up the new worker.
      worker.addEventListener('statechange', () => {
        if (worker.state === 'activated') {
          applying = false; paint();
          toast('Güncelleme hazır. Uygulama bir sonraki açılışında kullanacak.');
        }
      });
      worker.postMessage({type:'ACTIVATE_UPDATE'});
    } catch (error) { applying = false; toast(error.message); }
    finally { button.disabled = false; paint(); }
  }
  update.querySelector('button').onclick = applyUpdate;
  function open() {
    const workerStatus = unavailable ? 'Bu tarayıcı çevrimdışı ekranı etkinleştiremedi; çevrimiçi kullanabilirsin.' : registration?.active ? 'Çevrimdışı karşılama ekranı hazır' : 'Çevrimdışı karşılama ekranı hazırlanıyor';
    modal('Not, hep elinin altında', `<div class="pwa-intro"><img src="assets/icon-192.png" width="72" height="72" alt="Not uygulama simgesi"><div><strong>Kendi alanın. Tek dokunuşla.</strong><p>Telefonunda veya bilgisayarında, ayrı bir uygulama penceresinde aç.</p></div></div><div class="pwa-chips"><span>${installed()?'✓ Uygulama modunda':'Tarayıcıda açık'}</span><span>${navigator.onLine?'● Bağlantı var':'○ Çevrimdışı'}</span></div><p class="small-note">${workerStatus}</p>${promptEvent&&!installed()?'<button class="button primary pwa-install" id="pwa-install">↧ Not’u bu cihaza yükle</button>':''}${!installed()?'<div class="pwa-instructions"><p><strong>iPhone / iPad</strong><br>Safari’de Paylaş → Ana Ekrana Ekle. Görünüyorsa “Web Uygulaması Olarak Aç” seçeneğini etkinleştir.</p><p><strong>Android</strong><br>Chrome menüsü → Uygulamayı yükle veya Ana ekrana ekle.</p><p><strong>Bilgisayar</strong><br>Chrome / Edge adres çubuğundaki yükleme simgesini veya tarayıcı menüsünü kullan. Safari’de Dosya → Dock’a Ekle seçeneğini kullanabilirsin.</p></div>':''}<div class="pwa-privacy"><strong>Notların sunucuda, kontrol sende.</strong><p>Notlar ve ekler cihazın çevrimdışı önbelleğine yazılmaz. İnternet yokken uygulamayı yeniden açarsan notlarını görmek için bağlantı gerekir. Zaten açık olan notta yaptığın değişiklikler pencere açık kaldığı sürece bekler; bağlantı gelince kaydetme yeniden denenir.</p></div>`, () => {
      const button = document.querySelector('#pwa-install');
      if (button) button.onclick = async () => {
        const event = promptEvent;
        if (!event) return;
        button.disabled = true;
        try { await event.prompt(); const choice = await event.userChoice; toast(choice.outcome==='accepted'?'Not için kurulum başlatıldı.':'Dilediğinde buradan yükleyebilirsin.'); }
        catch { toast('Tarayıcının menüsünden yükleme seçeneğini kullanabilirsin.'); }
        finally { promptEvent = null; document.querySelector('#dialog').close(); }
      };
    });
  }
  window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); promptEvent = event; });
  window.addEventListener('appinstalled', () => { promptEvent = null; paint(); toast('Not yüklendi. Ana ekranından açabilirsin.'); });
  window.addEventListener('online', paint);
  window.addEventListener('offline', paint);
  standalone.addEventListener('change', paint);
  if ('serviceWorker' in navigator && window.isSecureContext) {
    navigator.serviceWorker.register('sw.js', {scope:'./', updateViaCache:'none'}).then(reg => {
      registration = reg; paint();
      const watch = worker => worker?.addEventListener('statechange', paint);
      watch(reg.installing);
      reg.addEventListener('updatefound', () => watch(reg.installing));
      navigator.serviceWorker.addEventListener('controllerchange', paint);
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState==='visible' && navigator.onLine) reg.update().catch(()=>{});
      });
    }).catch(() => { unavailable = true; });
  } else { unavailable = true; }
  paint();
  return {open};
}
