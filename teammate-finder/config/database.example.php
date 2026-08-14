<?php
/**
 * TEAMMATE FINDER — VERİTABANI YAPILANDIRMA ŞABLONU (ÖRNEK)
 * -----------------------------------------------------------
 * KURULUM:
 *   1. Bu dosyayı kopyalayıp `config/database.php` olarak oluşturun:
 *        cp config/database.example.php config/database.php
 *   2. Aşağıdaki değerleri kendi ortamınıza göre doldurun.
 *   3. `config/database.php` .gitignore ile hariç tutulduğu için
 *      repoya commit edilmez; gerçek şifre asla sürüm kontrolüne girmez.
 *
 * Alternatif: DB_* ortam değişkenlerini (getenv) kullanabilirsiniz.
 */

date_default_timezone_set("Europe/Istanbul");

// ---- GERÇEK DEĞERLERİNİZİ BURAYA YAZIN (placeholder'ları değiştirin) ----
$host     = getenv("DB_HOST")     ?: "YOUR_DB_HOST";        // örn. localhost
$dbname   = getenv("DB_NAME")     ?: "YOUR_DB_NAME";        // örn. teammate_finder
$username = getenv("DB_USER")     ?: "YOUR_DB_USER";        // örn. root
$password = getenv("DB_PASS")     ?: "YOUR_DB_PASSWORD";    // örn. sifre_buraya

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Gerçek hata yalnızca sunucu tarafında loglanır; client'a iç yapı bilgisi (host,
    // dbname, SQL state vb.) sızdırılmaz.
    error_log("[Veritabanı] Bağlantı hatası: " . $e->getMessage());
    die("Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.");
}
