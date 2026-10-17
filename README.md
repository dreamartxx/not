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

## Boş çalışma alanı ve Futbol

Kök adres / PWA açılışı boş bir not sayfasına gider. Kullanılmamış boş sayfa varsa tekrar kullanılır; mevcut notlar silinmez. Sayfa bağlantıları doğrudan ilgili notu, `#football` futbol alanını açar.

Futbol alanı Barcelona, Real Madrid, Bayern Münih, PSG, Manchester City, Manchester United, Beşiktaş, Bursaspor ve Türkiye millî takımının tüm kulvarlardaki fikstürlerini birleştirir. ESPN'in herkese açık takım fikstürü kaynağı sunucuda okunur; Türkiye yayınları S Sport ve beIN SPORTS'un resmi akışından takım çifti ve saatle eşleştirilir. Açıklanmayan saat ve kanallar tahmin edilmez. Tüm saatler Europe/Istanbul olarak gösterilir.

- Kaynaklar sayfa açıldığında en fazla 30 dakikada bir, Yenile ile en fazla dakikada bir kontrol edilir. Başarısız kaynaklarda önceki kayıt ve güncellik uyarısı gösterilir.
- `football_cache` ve `football_videos` tabloları ilk kullanımda otomatik oluşturulur (MySQL/SQLite). PHP cURL ve DOM uzantıları gerekir. `lib/football-seed.json` ilk erişim için tarihli, doğrulanmış bir fikstür görüntüsüdür; canlı kaynağın yerini sürekli tutmaz.
- Her maçın **••• → YouTube videosu ekle** menüsü isteğe bağlı bağlantıyı veritabanında saklar. Video maç altında görünür; kaldırmak için bağlantı boş kaydedilir. Video ekli geçmiş maçlar kaynak güncellemelerinde korunur.
- Mevcut JSON not yedeği yalnızca notlar ve medyayı kapsar; futbol bağlantıları veritabanı yedeğine dahildir.
- Fikstür kaynakları değişirse uyarlama gerekebilir. Lig yayın haklarından maç kanalı türetilmez; resmi akış eşleşmesi yoksa kanal açıklanmadı gösterilir.

### Futbol arşivi, puan durumu ve kapak

- Fikstürde **Geçmiş maçlar / Tüm tarihler** seçimiyle sezon (2000’den bugüne), başlangıç ve bitiş tarihi seçilebilir. Kaynakta olmayan sezonlar boş veya erişilemiyor olarak gösterilir. Şampiyonlar Ligi filtresi, takip edilen takımların bu turnuvadaki maçlarını ayırır; Türkiye filtresi millî takım fikstürünü gösterir.
- **Puan durumu** sekmesi beş ulusal lig, Şampiyonlar Ligi ve Uluslar Ligi tablolarını gösterir. Sıralama sağlayıcının resmi sıra alanına göredir; takip edilen takımlar vurgulanır. Tablo 15 dakika, geçmiş sezonlar 12 saat, devam eden sezon arşivi 30 dakika önbelleğe alınır.
- Futbol görünürken canlı durum dakikada bir yenilenir. Son 60 dakikada sarı ışık; yalnızca sağlayıcı `state=in` bildirdiğinde ve veri 3 dakikadan yeniyken kırmızı canlı dalga gösterilir. Başlama saati tek başına canlı kabul edilmez. Biten maçlarda skor gösterilir. Azaltılmış hareket tercihinde animasyonlar durur. Canlı güncelleme video iframe’lerini yeniden oluşturmaz.
- Üst bölümdeki **•••** menüsünden kapak yüklenir veya özel medya kütüphanesinden seçilir. Dikey konum ayarlanabilir; kapak kaldırılabilir. Kullanılan kapak görseli kütüphaneden yanlışlıkla silinemez. Kapak ayarı `football_settings`, harici veriler `football_extra_cache` tablosunda saklanır. Futbol ayarları/arşivi notların JSON yedeğine dahil değildir; veritabanı yedeği gerekir.

Bursaspor için ESPN güncel maç sunmadığından resmî kulüp fikstürü kullanılır. Kulübün gösterdiği sezonun maçları arşive de katılır; 00:00 yer tutucu saatler açıklanmamış sayılır. Bu kaynak canlı durum veya puan tablosu sunmaz; bunlar tahmin edilmez. Maç kimliği sezon ve takım çiftinden türetilir, saat değişince video bağlantısı korunur.

### D&D alanı · 2014

Yan menüde D&D veya `#dnd` bağlantısı açılır. İlk kullanım boş başlar; örnek karakterler canlı veritabanına yazılmaz. En fazla 12 karakter, özellik puanları, yeterlilik/uzmanlık, kurtarışlar, can/geçici can, ölüm kurtarışları, ilham, bitkinlik, can zarları, sınıf kaynakları, saldırılar, envanter ve paralar yönetilir. 2014 değiştirici/yeterlilik/AC dışı bonus hesapları ve kısa/uzun dinlenme işlemleri uygulanır. Sınıf/ırk/ekipman bonusları ile özel kural istisnaları otomatik uygulanmaz; karakter kâğıdı değerleri girilir. Warlock büyü yuvaları kısa dinlenmeden sonra elle yenilenir.

319 SRD 5.1 büyüsü, korumalı JSON dosyasından giriş gerektiren `dnd-spells` API işlemiyle sunulur ve arşivde aranır, sınıf/seviye ile filtrelenir, bilinen/hazırlanan listelerine eklenir. Kullanımda seçilen seviyenin yuvası harcanır; kanttrip ve uygun ritüeller yuva harcamaz. Konsantrasyon takip edilir. Açıklamalar özgün İngilizce, seçili adların Türkçe karşılıkları arama içindir. PHB 2014 içeriğinin tamamı dahil değildir; kişisel/ek büyüler yazılabilir. Türkçe başvuru bağlantıları kullanıcının önerdiği 5eTürkçe sitesine gider.

Oturum günlüğü (başlık, tarih, sefer, etiketler, serbest not), görevler, NPC'ler ve 80 atışlık zar geçmişi aynı özel alanda tutulur. `dnd_workspace` tablosu oturum doğrulamasından sonra oluşturulur. JSON belge 2,5 MB ile sınırlıdır; sunucu bütün alanları doğrular ve sürüm çakışmasında 409 döndürür. CSRF, otomatik kayıt, kaydetmeden ayrılma uyarısı ve taslak indirme bulunur. D&D menüsünden ayrı JSON yedeği indirilir; içe aktarma onaydan sonra mevcut D&D belgesini değiştirir. Not sayfalarının yedeği D&D verisini içermez; tam veritabanı yedeği ikisini de kapsar. PWA özel D&D verilerini servis çalışanı önbelleğine almaz; kayıt için bağlantı gerekir.

Büyü kaynağı: https://github.com/open5e/open5e-api/tree/master/data/WOTC_5e_SRD_v5.1 (2026-10-17). Yalnızca SRD 5.1 büyüleri alınmıştır. JSON alan adları/sunum biçimi değiştirilmiş ve bazı Türkçe arama adları eklenmiştir.

This work includes material taken from the System Reference Document 5.1 (“SRD 5.1”) by Wizards of the Coast LLC and available at https://dnd.wizards.com/resources/systems-reference-document. The SRD 5.1 is licensed under the Creative Commons Attribution 4.0 International License available at https://creativecommons.org/licenses/by/4.0/legalcode.
