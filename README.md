# Not — kişisel çalışma alanı

Notion'dan esinlenen, Türkçe ve tek kullanıcılı bir not uygulaması. PHP 8.2+ ve MySQL/MariaDB üzerinde çalışır; Composer veya Node derlemesi gerekmez.

## Özellikler

- İç içe sayfalar, sayfa taşıma, kopyalama ve favoriler
- Metin, iki başlık seviyesi, madde listesi, yapılacaklar, alıntı, bilgi kutusu, kod ve ayırıcı blokları
- `/` blok menüsü, Enter ile blok ekleme, Alt + ↑/↓ ile blok taşıma
- Otomatik kayıt ve sürüm denetimi: başka sekmedeki değişikliklerin sessizce üzerine yazılmaz
- Başlık ve içerikte Türkçe arama (`Cmd/Ctrl + K`)
- Renkli kapaklar, simgeler, açık/koyu tema, mobil görünüm
- Her not/alt sayfa, Tüm sayfalar, Favoriler, Çöp kutusu, D&D ve Futbol için bağımsız fotoğraf kapağı. Sağ üst `•••` menüsünden dosya yükleme veya özel kütüphaneden seçme, dikey kadraj ve kaldırma; kapaksız sayfalarda ek bir alan görünmez.
- Kapaklar özel medya deposunda ve veritabanında saklanır; kullanılan kapak dosyaları silinmeye karşı korunur. Tam JSON yedeği kapak görsellerini de içerir. İçe aktarım sayfa kapaklarını yeni kopyalara taşır, mevcut alan kapaklarının üzerine yazmaz.
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

460 büyü (PHB 361, SCAG 4, XGE 95), korumalı JSON dosyasından giriş gerektiren `dnd-spells` API işlemiyle sunulur. Kaynak sitenin `data/spells/index.json` listesindeki tüm kayıtlar dahildir. Seçili büyüler önde görünür; arşiv istenince açılır ve ad, kaynak kitap, sınıf, seviye, okul ve özellik ile filtrelenir. Bilinen/hazırlanan listeleri, yuva tüketimi, ritüeller ve konsantrasyon takibi tüm büyülerde çalışır. İngilizce kaynak adları gösterilir; eski SRD adları aramada takma ad olarak korunur ve mevcut karakterlerdeki büyü kimlikleri değişmez. 319 SRD büyüsünün Türkçe açıklaması uygulamadadır. Ek 141 büyüde açıklama kopyalanmaz; istek üzerine kaynak sayfası izole çerçevede açılır, ayrıca doğrudan bağlantı sunulur. Kaynak açıklamaları internet gerektirir.

Oturum günlüğü (başlık, tarih, sefer, etiketler, serbest not), görevler, NPC'ler ve 80 atışlık zar geçmişi aynı özel alanda tutulur. `dnd_workspace` tablosu oturum doğrulamasından sonra oluşturulur. JSON belge 2,5 MB ile sınırlıdır; sunucu bütün alanları doğrular ve sürüm çakışmasında 409 döndürür. CSRF, otomatik kayıt, kaydetmeden ayrılma uyarısı ve taslak indirme bulunur. D&D menüsünden ayrı JSON yedeği indirilir; içe aktarma onaydan sonra mevcut D&D belgesini değiştirir. Not sayfalarının yedeği D&D verisini içermez; tam veritabanı yedeği ikisini de kapsar. PWA özel D&D verilerini servis çalışanı önbelleğine almaz; kayıt için bağlantı gerekir.

Büyü kaynağı: https://github.com/open5e/open5e-api/tree/master/data/WOTC_5e_SRD_v5.1 (2026-10-17). Yalnızca SRD 5.1 büyüleri alınmıştır. JSON alan adları/sunum biçimi değiştirilmiş ve bazı Türkçe arama adları eklenmiştir.

This work includes material taken from the System Reference Document 5.1 (“SRD 5.1”) by Wizards of the Coast LLC and available at https://dnd.wizards.com/resources/systems-reference-document. The SRD 5.1 is licensed under the Creative Commons Attribution 4.0 International License available at https://creativecommons.org/licenses/by/4.0/legalcode.

### Kişisel büyü kitabı ve Türkçe açıklamalar

Büyü kitabı varsayılan olarak yalnızca aktif karakterin seçtiği büyüleri gösterir. Seviyeye göre katlanabilir gruplar; hazırlanmış büyüler, okul, ritüel, konsantrasyon ve kullanım türü filtreleri vardır. Genel arşiv başlangıçta kapalıdır, açıldığında 12 kayıtlık sayfalar ve sınırlı kaydırma alanı kullanır.

319 SRD büyüsünün Türkçe metinleri [5eTürkçe PHB 2014 verisinden](https://github.com/kanguen/kanguen.github.io/blob/master/data/spells/spells-phb.json) mevcut SRD büyülerine eşleştirilmiştir. Kaynak deponun [MIT bildirimi](licenses/5eturkce-MIT.txt) korunmuştur. Temel SRD 5.1 metni CC BY 4.0 kapsamındadır. Kaynak etiketleri düz metne, tablolar başlıklı satırlara çevrilmiştir. İngilizce SRD metinler veride korunur. Mass Cure Wounds, Mass Heal ve Revivify okul bilgileri [resmî PHB errata](https://media.wizards.com/2015/downloads/dnd/Errata_PH.pdf) ile düzeltilmiştir. Diğer PHB/SCAG/XGE kayıtları yalnızca olgusal katalog bilgileri ve yayıncı bağlantısı içerir; kitap açıklamaları yeniden yayımlanmaz.

Katalog eşleştirmesi `python3 tests/import_spell_catalog.py DIRECTORY` ile tekrarlanabilir. Dizin, kaynak sitenin `index.json` dosyasını ve işaret ettiği üç `spells-*.json` dosyasını içermelidir. Eşleştirme mevcut kimlikleri korur; eksik eşleşme veya yinelenen kimlik varsa durur. `catalog.sources` alanı 17 Ekim 2026 sayımını tutar.

### Seçili metin ve hatırlatıcılar

Not bloklarında seçilen metin için yüzen araç çubuğu açılır: kalın, italik, altı/üstü çizili, satır içi kod, yazı/vurgu rengi, font, boyut, alt/üst simge ve HTTPS bağlantı. `Alt+F10` araç çubuğuna odaklanır; `Esc` kapatır; `Ctrl/⌘ B/I/U` kısayolları seçime uygulanır. Biçimler HTML yerine doğrulanan `runs` dizilerinde saklanır. Eski düz metin blokları uyumludur. JSON yedekleri biçimleri korur; Markdown temel işaretleri taşır, renk/font/boyutu taşımaz. Kod blokları, tablo hücreleri ve D&D düz metin alanları bu araç çubuğunu kullanmaz.

Seçili yazı menüsündeki **◷ Seçili yazıya hatırlatıcı kur**, sayfadaki yazılara toplam en fazla 30 bağımsız hatırlatıcı ekler. Ayrı **Hatırlatıcılar** alanı başlık, not bağlantısı, üst sayfa/çalışma alanı, İstanbul saati, kalan/geçen süre ve durum gösterir. Yaklaşma eşiği, kart rengi, tamamla/yeniden aç, 15 dakika ertele ve kaldır seçenekleri vardır. Hareketi azalt tercihi animasyonu kapatır. `page_reminders` tablosu otomatik oluşturulur; notun sürümlü kaydıyla aynı transaction içinde güncellenir. Yedekleme, import, çöp kutusu/geri yükleme ve kalıcı silme notla birlikte işler. Eski istemci kaydı hatırlatıcıları silmez.

Google Takvim düğmesi [Google’ın etkinlik şablonu bağlantısını](https://developers.google.com/workspace/calendar/api/concepts/inviting-attendees-to-events) kullanır: başlık, zaman, not bağlantısı ve alan adı doldurulur; kullanıcı Google’da takvimini seçip kaydeder. Notun gövdesi gönderilmez. Alternatif `.ics` dışa aktarımı yerel takvim uyarısını içerir. Bunlar ayrı takvim kopyalarıdır; OAuth veya çift yönlü eşitleme yoktur. Uygulama içi sayaç ve izin verilen tarayıcı bildirimleri yalnızca uygulama açıkken çalışır. Sunucu push zamanlayıcısı yoktur; kapalı uygulamada uyarı için takvim kaydı gerekir.


Hatırlatıcılar artık sayfanın tamamına değil yazıya bağlanır: bir metin bloğunda cümle seçip yüzen menüde **◷ Seçili yazıya hatırlatıcı kur** seçilir; alternatif olarak bloğun menüsünde **Bu yazıya hatırlatıcı kur** kullanılır. Hatırlatıcı alanından eklerken de sayfa içindeki yazı seçilir. Gösterge sayfa başlığında değil ilgili yazının altında görünür. `target` alanı blok kimliği, UTF-16 başlangıç konumu ve en fazla 1000 karakterlik seçili metni saklar; aynı sayfadaki farklı yazılar bağımsız hatırlatıcılara sahip olabilir. Kart ve takvim bağlantıları `/reminder/ID` ile ilgili bölümü açar. Blok taşınırsa kimliği korunur; metin değişir/silinirse ilk alıntı saklanır ve kartta açıklanır. Eski sayfa hatırlatıcıları kaybolmaz; düzenlerken bir yazıya bağlanabilir. Not başına toplam hatırlatıcı JSON boyutu mevcut TEXT alanıyla uyum için 60 KB ile sınırlıdır.

### Eğitim çalışma alanı

`#education`, verilen **Bilirkişilik Temel Eğitimi Kaynak Kitabı, Mayıs 2025 / ikinci bası** dosyasının 307 sayfasını ve 5 ana bölümünü sunar. PDF sayfa numarası ile basılı sayfa numarası ayrı gösterilir. Türkçe tam metin arama, 31 konu bağlantısı, kaldığın yer, sayfa bazlı okundu işaretleri, yer imleri, yazı boyutu ve kapak fotoğrafı vardır. Seçili yazıdan sayfaya bağlı not veya tekrar kartı oluşturulur; notlar ayrıca listelenir. Kartların 1/3/7/14/30/60 günlük tekrar aralıkları İstanbul tarihine göre hesaplanır.

Kaynak metin 8 JSON parçasında tutulur ve oturum korumalı `education-book` API'sinden okunur. Kaynak PDF bağlantısı Bakanlığın yayımladığı, yüklenen dosyayla aynı SHA-256 değerine sahip özgün dosyaya gider (`a686e2b3d609d42173db3009c82ac0ee6040bc7240e41b8d1d6ba2880b5d6e04`). Ekran görüntüleri ve şablonlar metin aktarımında yeniden çizilmez; bu sayfalarda özgün PDF bağlantısı gösterilir. İçerik güncel mevzuat iddiasında bulunmadan Mayıs 2025 kaynağını yansıtır.

`education_workspace` tablosu otomatik kurulur. İlerleme, yer imleri, notlar ve kartlar tek sürümlü belge olarak atomik kaydedilir; çakışma sessizce üzerine yazılmaz. Eğitim menüsündeki ayrı JSON yedeği aynı doğrulamadan geçerek içe alınabilir. 307 sayfa sınırı, kayıt kimlikleri, tarih, liste ve metin uzunlukları sunucuda doğrulanır. Genel not yedeği eğitim kaydını içermez; eğitim yedeği kendi menüsündedir. Tam not yedeği eğitim kapak fotoğrafını da taşır.

### Etkileşimli eğitim stüdyosu

Eğitim alanı beş bölüm ve 20 ayrıntılı konu içerir. Her konu üç açıklamalı anlatım, sorunlu/geliştirilmiş yaklaşım karşılaştırması, üç adımlı çözümlü kurgusal vaka ve **Sen çöz** alanı sunar. Toplam 60 çözüm adımı, her seçenek için ayrı gerekçesi olan 20 konu sorusu ve 20 kişisel yazılı uygulama vardır. Örnek cevaplar isteğe bağlı açılır; serbest cevaplar otomatik puanlanmaz. Bölüm vakaları ve 15 soruluk bölüm testleri de korunur. İçerikler Mayıs 2025 kitabına dayalı kişisel öğrenme materyalidir; resmî sınav veya sertifikasyon değildir. Konuların kaynak sayfaları doğrudan açılabilir; kitabın 307 sayfası ayrı Kaynak kitap görünümündedir.

Konu seçimi, doğru/yanlış yanıtlar ve kişisel uygulama metinleri `learning.study` alanında otomatik kaydedilir ve eğitim yedeğine dahildir. Konu pekiştirme, doğru kontrol cevabından hesaplanır. Bölüm testi tamamlama için üç doğru yanıt sunucuda doğrulanır. Eski istemciler yeni alanları göndermediğinde mevcut kayıtlar korunur. Bilinmeyen konu/soru, geçersiz cevap ve 6000 karakterden uzun uygulama metni sunucuda reddedilir. Sayfa okuma, konu pekiştirme ve bölüm testi ilerlemeleri ayrıdır. Açık/koyu tema, dar ekran ve hareketi azalt tercihi desteklenir.

### Ses araçları

Üst çubuktaki mikrofon simgesi üç mod açar: **Konuşarak yaz**, **Sesli dinle** ve **Sesli komut**. Türkçe dikte, kesinleşen cümleleri yeni metin bloğuna veya seçili yazının sonuna ekler; mevcut biçimleri korur ve normal otomatik kayıt/geri alma akışını kullanır. Ara sonuçlar önizlemedir, notlara yazılmaz. Dikte sırasında sayfa değiştirmek mikrofonu durdurur. Sesli komut modunda sayfalar arasında gezinirken dinleme devam eder; tarayıcı oturumu bitirirse yeniden başlatılabilir.

Komutlar: “Futbolu aç”, “Eğitimi aç”, “DND aç”, “Hatırlatıcıları aç”, “Tüm sayfaları aç”, “Çalışma alanını aç”, “<tam başlık> sayfasını aç”, “Sonraki/önceki sayfa”, “Sonraki/önceki paragraf”, “Aşağı/yukarı kaydır”, “Başa/sona git”, “Notu oku”, “Bu bloğu oku”, “Komutları durdur”. Aynı başlıklı birden fazla sayfa varsa otomatik seçim yapılmaz. Komut modu metin yazmaz veya veri silmez.

Tüm sayfa, blok menüsünden tek not veya seçim araç çubuğundan seçili metin dinlenebilir. Türkçe ses önceliklidir; ses seçimi, hız, duraklat/devam et, durdur ve okuma ilerlemesi vardır. Uzun metinler parçalara bölünür. Okuma başlatılınca mikrofon kapanır; panel kapanınca dinleme ve okuma durur. Sekme arka plana geçince mikrofon kapanır, okuma duraklar.

Dikte/komutlar tarayıcının `SpeechRecognition` desteğini, HTTPS ve mikrofon iznini gerektirir. Bazı tarayıcılar sesi kendi çevrimiçi konuşma hizmetinde işler. Uygulama ham ses kaydı saklamaz, özel bir transkripsiyon hizmeti veya API anahtarı kullanmaz. Sesli okuma cihazın/tarayıcının `speechSynthesis` seslerini kullanır; Türkçe ses bulunmuyorsa cihaz ayarlarından eklenebilir. PWA desteği kurulu tarayıcıya bağlıdır. İzin ve ağ hataları panelde açıklanır.

`node tests/voice.mjs`: Türkçe komut eşleştirme ve belirsiz başlıklar, Unicode uzun metin bölme, biçimlerin korunması, tekrar eden/geciken tanıma sonuçları, durdurma, izin hatası ve oynatma yaşam döngüsü.
