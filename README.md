# Not — kişisel çalışma alanı

Notion'dan esinlenen, Türkçe ve tek kullanıcılı bir not uygulaması. PHP 8.2+ ve MySQL/MariaDB üzerinde çalışır; Composer veya Node derlemesi gerekmez.

## Özellikler

- İç içe sayfalar, sayfa taşıma, kopyalama ve favoriler
- Metin, iki başlık seviyesi, madde listesi, yapılacaklar, alıntı, bilgi kutusu, kod ve ayırıcı blokları
- `/` blok menüsü, Enter ile blok ekleme, Alt + ↑/↓ ile blok taşıma
- Otomatik kayıt ve sürüm denetimi: başka sekmedeki değişikliklerin sessizce üzerine yazılmaz
- Başlık ve içerikte Türkçe arama (`Cmd/Ctrl + K`)
- Renkli kapaklar, simgeler, açık/koyu tema, mobil görünüm
- Günlük, proje ve okuma şablonları
- Alt sayfalarla birlikte çöp kutusu ve geri yükleme
- Markdown dışa aktarma, JSON yedekleme ve atomik JSON içe aktarma
- Tek hesap, kapalı kayıt, parola hash'i, CSRF doğrulaması, HttpOnly/SameSite oturumu ve giriş deneme sınırı

## Hostinger kurulumu

Hedef: https://n4me.com.tr/

1. hPanel → Veritabanları → Yönetici bölümünde bu uygulamaya özel MySQL veritabanını ve kullanıcısını oluşturun.
2. hPanel → Gelişmiş → GIT bölümünden `https://github.com/dreamartxx/not` reposunun `main` dalını web köküne (`public_html`) dağıtın. Başka bir siteyi içeren dizinin üzerine kurmayın.
3. Dosya Yöneticisi ile `private/config.example.php` örneğini kullanarak `public_html` klasörünün bir üstünde `not-config.php` oluşturun. Bu konum dağıtımlardan bağımsızdır. Alternatif olarak `private/config.php` de desteklenir. DSN, veritabanı kullanıcı adı ve şifresini girin. `setup_key` değerini en az 32 karakterlik rastgele bir değerle değiştirin. Örnek anahtar kabul edilmez.
4. HTTPS'in etkin olduğundan emin olun; `secure_cookie` canlıda `true` kalmalıdır. PHP 8.2+ ve `pdo_mysql`, `mbstring` uzantılarını etkinleştirin.
5. Siteyi açın. Kurulum anahtarını girip kendi kullanıcı adı ve en az 7 karakterlik parolanızla tek hesabı oluşturun. Sonraki ziyaretçiler yalnızca giriş ekranını görür; yeni hesap açılamaz.
6. Kurulumdan sonra `setup_key` değerini boşaltabilirsiniz. Mevcut hesap bundan etkilenmez.

Notlar MySQL'de, kod GitHub'da tutulur. `not-config.php` web kökünün dışında tutulur; `private/config.php` Git'e gönderilmez. `.htaccess`, özel klasörleri ve Git metadata'sını dış erişime kapatır. Dağıtımda bu dosyayı silmeyin; mevcut veritabanını sıfırlamayın. Hostinger PHP/HTML dağıtımı için kaynak: https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/

## Yerel çalıştırma

`private/config.example.php` dosyasını `private/config.php` olarak kopyalayın. Yerel MySQL kullanın veya test için DSN'yi `sqlite:/tmp/not-local.sqlite` olarak ayarlayın. Yerelde `secure_cookie=false` gerekir.

```sh
php -S 127.0.0.1:8080
```

PHP'nin geliştirme sunucusunu internete açmayın. Alternatif yapılandırma dosyasının yolu `NOT_CONFIG` ortam değişkeniyle verilebilir.

## Testler

```sh
php -l api.php
php -l index.php
node --check assets/app.js
python3 tests/integration.py
```

Entegrasyon testleri geçici SQLite dosyası ve izole localhost sunucusu kullanır. Mevcut notlara dokunmaz. MySQL uyumluluğu dağıtımda ayrıca kontrol edilmelidir.

## Yedek ve kullanım sınırları

Ayarlar → tüm sayfaları JSON olarak indir. İçe aktarma mevcut notları değiştirmez; kimlikleri yenilenmiş kopyalar ekler ve tek işlemde tamamlanır. Bir içe aktarmada en fazla 500 sayfa ve 5 MB sayfa verisi; bir sayfada en fazla 2.000 blok. Kalıcı silme geri alınamaz. Hostinger veritabanı yedeği de düzenli tutulmalıdır.

Kaydedilmeyen düzenlemeler sekmede tutulur; kayıt hatasında uyarı gösterilir ve JSON taslak indirilebilir. Çevrimdışı çalışma/senkronizasyon sunulmaz. Sekmeyi kapatmadan önce kayıt durumunu kontrol edin. Notlar şifreli HTTPS üzerinden taşınır; veritabanında uçtan uca şifreleme yoktur. Çok kullanıcılı paylaşım bu sürüme dahil değildir.

Hesap şifresi Ayarlar → Şifremi değiştir üzerinden değiştirilebilir. Şifre değişikliği diğer oturumları geçersiz kılar.

## Studio editörü

Boş sayfa yalnızca başlık ve yazı alanıyla açılır. Üst çubuktaki **Ekle** menüsü 21 blok türünü açar; bir bloğa dokunup menüden eklediğinde yeni içerik o bloğun altına gelir. Boş metin satırında `/` de bu menüyü açar. Geri al/ileri al, odak modu, kapak, simge, taşıma ve alt sayfa işlemleri sağ üstteki **•••** menüsündedir. Her bloğun solundaki tutamaçla sürükleyip sıralayabilir; blok menüsünden yarım satır seçerek iki bloğu yan yana koyabilir, renk ve hizalama ayarlayabilirsin.

- **Tablo:** hücre düzenleme, satır/sütun ekleme ve silme, başlık satırı, Excel/Sheets'ten sekmeyle ayrılmış hücre yapıştırma, CSV dışa aktarma.
- **Çizim / diyagram:** kalem, silgi, metin, dikdörtgen, elips, karar şekli, çizgi ve ok; şekilleri taşıma ve boyutlandırma; iki düğümü bağlayan ve taşınırken takip eden oklar; fotoğraf ekleme; geri/ileri alma ve PNG indirme.
- **Fotoğraf:** dosya yükleme, ekran görüntüsü yapıştırma, sürükleyip bırakma, özel kütüphaneden seçim veya HTTPS bağlantısı; genişlik, hizalama ve açıklama.
- **Video:** YouTube (zaman damgası dahil), Vimeo, doğrudan MP4/WebM bağlantısı veya MP4 dosyası. Uzak oynatıcı tıklanınca yüklenir. Kaynağın gömmeye izin vermesi gerekir.
- **Ses / PDF:** dosya veya HTTPS bağlantısı, yerinde ses oynatma ve dosya indirme.
- **Bağlantı kartı, açılır not, numaralı liste, içindekiler, üç sütunlu görev panosu**, odak modu, editör geri/ileri alma.
- Yan menüde **SAYFALAR → •••** üzerinden boş sayfa ve günlük, proje, okuma, görsel düşünce, ilham ve toplantı şablonları.
- Tablo satır/sütun işlemleri ve CSV dışa aktarımı **Tablo menüsü** içinde.
- Açık temada kırık beyaz, koyu temada grafit yüzeyler; nötr kapaklar ve ölçülü mavi vurgular.

### Dosyalar ve taşınabilir yedek

Dosyalar MySQL'deki media tablosunda tutulur ve yalnızca giriş yapılmış oturumlara sunulur. Sunucu dosya türünü doğrular; SVG/HTML/çalıştırılabilir dosyalar kabul edilmez. Sınırlar dosya başına 8 MB ve toplam 150 MB'dir. .user.ini PHP yükleme sınırını ayarlar; Hostinger'ın bu ayarı uygulaması birkaç dakika sürebilir. Kullanılmayan dosyalar kütüphaneden yönetilebilir; herhangi bir notta (çöp kutusu dahil) kullanılan dosya silinemez.

JSON v2 yedeği notların yanında kullanılan yüklenmiş dosyaların baytlarını da içerir; içe aktarırken dosya kimlikleri yeniden eşlenir. Eski v1 metin yedekleri desteklenir. Uzak bağlantıların içeriği yedeğe indirilmez. Markdown dışa aktarmadaki özel dosya bağlantıları giriş gerektirir; taşınabilir tam yedek için JSON kullan. İçe aktarılacak sayfa verisinin toplamı API'nin 5 MB istek sınırına, ayrı yüklenen medya ise dosya sınırlarına tabidir.

Editörün geri alma geçmişi ve kapatılan çizim taslakları açık oturum belleğinde tutulur; sayfa yenilenince sıfırlanır. Çizim stüdyosunda **Çizimi sayfaya ekle** ile kaydet. Başka cihazda aynı notun değişmesi durumunda mevcut sürüm çakışması koruması geçerlidir. Metin blokları düz metindir; tablo formülleri, dosya OCR'ı ve çevrimdışı eşitleme bu sürümde yoktur.

### Ek doğrulamalar

    node tests/studio.mjs
    python3 tests/integration.py

Testler yeni blokların kalıcılığını, hatalı içerik reddini, özel dosya erişimini, dosya türü ve CSRF doğrulamasını, kullanılan dosyaların silinmeye karşı korunmasını ve görsellerin yedekle taşınmasını kapsar. GitHub Actions aynı HTTP testlerini hem SQLite hem MySQL üzerinde çalıştırır.

## PWA · Ana ekrana kurulum

Uygulama HTTPS üzerinde `manifest.webmanifest`, 192/512 px simgeler, ayrı maskable simge ve Apple dokunmatik simgesiyle kurulabilir. Menüdeki **Uygulama olarak kullan** ekranı destekleyen tarayıcılarda yükleme düğmesini, diğerlerinde cihazına uygun yönergeleri gösterir. Kurulunca bağımsız pencerede açılır. Mobil güvenli alanlar ve dokunmatik hedefler desteklenir.

`sw.js` yalnızca herkese açık çevrimdışı karşılama sayfasını, onun CSS/JS dosyalarını ve uygulama simgesini önbelleğe alır. Giriş yapılmış HTML, API yanıtları, notlar ve dosyalar Cache Storage’a **yazılmaz**. İnternet olmadan yeniden açılışta notlara erişilemez. Açık sekmedeki değişiklikler bellekte bekler ve bağlantı geri geldiğinde kaydetme yeniden denenir; pencere kapanırsa kaydedilmemiş çalışma kaybolabilir. Kaydetme hatasındaki taslak indirme seçeneğini kullan.

Yeni worker sürümleri bekleme durumuna geçer ve güncelleme bildirimi gösterir. Güncelleme ancak aktif çizim/yükleme ve bekleyen kayıt yokken uygulanır; hiçbir sekme kendiliğinden yenilenmez. Bir sonraki açılış güncel uygulamayı yükler. Çevrimdışı varlıklarda değişiklik yaparken `sw.js` içindeki `CACHE` sürümünü artır. Temizlik sadece `not-public-pwa-` önekli eski önbellekleri siler.

`npm test` manifest/simge doğrulamalarını ve worker testlerini içerir: kök/alt dizin kurulumu, API ve özel medya atlama, çevrimdışı açılış, kapsamlı olmayan önbellek temizliği ve açık güncelleme onayı. Yerel önizleme için HTTPS veya localhost gerekir.
