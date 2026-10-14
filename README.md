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

Hedef: https://beige-ibex-609908.hostingersite.com/

1. hPanel → Veritabanları → Yönetici bölümünde bu uygulamaya özel MySQL veritabanını ve kullanıcısını oluşturun.
2. hPanel → Gelişmiş → GIT bölümünden `https://github.com/dreamartxx/not` reposunun `main` dalını web köküne (`public_html`) dağıtın. Başka bir siteyi içeren dizinin üzerine kurmayın.
3. Dosya Yöneticisi ile `private/config.example.php` örneğini kullanarak `public_html` klasörünün bir üstünde `not-config.php` oluşturun. Bu konum dağıtımlardan bağımsızdır. Alternatif olarak `private/config.php` de desteklenir. DSN, veritabanı kullanıcı adı ve şifresini girin. `setup_key` değerini en az 32 karakterlik rastgele bir değerle değiştirin. Örnek anahtar kabul edilmez.
4. HTTPS'in etkin olduğundan emin olun; `secure_cookie` canlıda `true` kalmalıdır. PHP 8.2+ ve `pdo_mysql`, `mbstring` uzantılarını etkinleştirin.
5. Siteyi açın. Kurulum anahtarını girip kendi kullanıcı adı ve en az 12 karakterlik parolanızla tek hesabı oluşturun. Sonraki ziyaretçiler yalnızca giriş ekranını görür; yeni hesap açılamaz.
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

Ayarlar → tüm sayfaları JSON olarak indir. İçe aktarma mevcut notları değiştirmez; kimlikleri yenilenmiş kopyalar ekler ve tek işlemde tamamlanır. Bir içe aktarmada en fazla 500 sayfa ve 4 MB; bir sayfada en fazla 2.000 blok. Kalıcı silme geri alınamaz. Hostinger veritabanı yedeği de düzenli tutulmalıdır.

Kaydedilmeyen düzenlemeler sekmede tutulur; kayıt hatasında uyarı gösterilir ve JSON taslak indirilebilir. Çevrimdışı çalışma/senkronizasyon sunulmaz. Sekmeyi kapatmadan önce kayıt durumunu kontrol edin. Notlar şifreli HTTPS üzerinden taşınır; veritabanında uçtan uca şifreleme yoktur. Dosya yükleme ve çok kullanıcılı paylaşım bu sürüme dahil değildir.

Hesap şifresi Ayarlar → Şifremi değiştir üzerinden değiştirilebilir. Şifre değişikliği diğer oturumları geçersiz kılar.
