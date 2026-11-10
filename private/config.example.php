<?php
// Bu dosyayı private/config.php olarak kopyalayın. config.php Git'e gönderilmez.
return [
    'dsn' => 'mysql:host=localhost;dbname=u123456789_not;charset=utf8mb4',
    'db_user' => 'u123456789_not',
    'db_password' => 'VERITABANI_SIFRESI',
    // En az 32 karakterlik rastgele bir anahtar belirleyin. İlk hesap kurulumunda sorulur.
    'setup_key' => 'BURAYA_EN_AZ_32_KARAKTER_RASTGELE_ANAHTAR',
    // EPA CTX key; alternatively place comptox-config.php beside this config with an api_key field.
    'comptox_api_key' => '',
    'google_translation_api_key' => '', // Or place only the key in google-translation-key.txt beside the private DB config.
    'google_translation_monthly_limit' => 450000,
    'secure_cookie' => true, // Canlıda HTTPS zorunlu. Yalnızca localhost için false.
];
