<div align="center">

# 🤝 TeamMate Finder

**Proje ve ödevler için takım arkadaşı bulmanın en kolay yolu**

Öğrencilerin bölüm, beceri ve ilgi alanlarına göre proje ekibi oluşturmasını sağlayan açık kaynaklı bir web uygulaması.

</div>

---

## ✨ Özellikler

- 🔍 **Akıllı Eşleştirme** — Bölüm, beceri ve ilgi alanlarına göre uyumluluk puanı hesaplayarak en uygun takım arkadaşlarını önerir
- 👥 **Takım & Proje Yönetimi** — Ekip oluşturma, proje ilanı verme ve davet yönetimi
- 💬 **Mesajlaşma** — Gerçek zamanlı çevrimiçi durum takibi ile birebir sohbet
- 🖼️ **Profil Yönetimi** — Profil fotoğrafı, beceriler, ilgi alanları ve hakkında bilgileri
- 🛡️ **Yönetici Paneli** — Kullanıcı ve proje yönetimi

---

## 🚀 Kurulum

### Gereksinimler

- PHP 7.4+ (PDO & MySQL desteği)
- MySQL / MariaDB
- Apache (XAMPP, WAMP, Laragon vb.)
- Composer (opsiyonel)

### Adımlar

1. **Projeyi klonlayın**

   ```bash
   git clone https://github.com/kullanici/teammate-finder.git
   cd teammate-finder
   ```

2. **Veritabanı yapılandırmasını oluşturun**

   ```bash
   cp config/database.example.php config/database.php
   ```

   Ardından `config/database.php` dosyasındaki yer tutucuları kendi bilgilerinizle değiştirin:

   ```php
   $host     = "localhost";
   $dbname   = "teammate_finder";
   $username = "root";
   $password = "sifre_buraya";
   ```

   > 💡 Alternatif olarak `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` ortam değişkenlerini kullanabilirsiniz.

3. **Veritabanını oluşturun** — `teammate_finder` adında bir veritabanı oluşturun (tablolar ilk çalıştırmada otomatik oluşturulur).

4. **Sunucuyu başlatın**

   Projeyi Apache'nin web kök dizinine (örn. `htdocs/`) yerleştirin ve şu adrese gidin:

   ```
   http://localhost/teammate-finder
   ```

---

## 📁 Proje Yapısı

```
teammate-finder/
├── admin/          # Yönetici paneli (dashboard, kullanıcı, proje)
├── api/            # AJAX uç noktaları (mesajlaşma, talepler, arama)
├── assets/         # CSS, JS ve yüklenen dosyalar
├── config/         # Veritabanı yapılandırması
├── includes/       # Ortak bileşenler (header, navbar, session vb.)
├── pages/          # Uygulama sayfaları (profil, ekip, sohbet vb.)
├── index.php       # Ana sayfa
├── login.php       # Giriş
├── register.php    # Kayıt
└── logout.php      # Çıkış
```

---

## 🛡️ Güvenlik

- 🔐 **PDO Hazırlıklı Sorgular** — SQL enjeksiyonuna karşı koruma
- 🔑 **password_hash / password_verify** ile şifre hashing
- 🚫 **Brute-force koruması** — Hatalı giriş denemeleri için kilitlenme
- 🍪 **Güvenli oturum & "Beni hatırla"** — HTTP-only, SameSite öznitelikli çerezler
- 🛡️ **XSS koruması** — Tüm çıktılarda `htmlspecialchars`
- 📁 **.htaccess kuralları** — Gizli dosya ve dizinlerin erişime kapatılması

---

## 🧰 Kullanılan Teknolojiler

| Katman | Teknoloji |
|--------|-----------|
| Backend | PHP (PDO) |
| Veritabanı | MySQL |
| Frontend | HTML, CSS, Vanilla JavaScript |
| İkonlar | Font Awesome |
| Sunucu | Apache (XAMPP) |

---

## 📄 Lisans

Bu proje eğitim amacıyla geliştirilmiştir.

---

<div align="center">

Proje faydalıysa ⭐ vermeyi unutmayın!

</div>
