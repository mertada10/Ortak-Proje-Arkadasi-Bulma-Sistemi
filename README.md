# 🚀 TeamMate Finder

TeamMate Finder, üniversite öğrencilerinin ortak projeler için takım arkadaşları bulmasını, ekip oluşturmasını ve ekip içi iletişim kurmasını sağlayan **PHP & MySQL tabanlı** bir web uygulamasıdır.

---

## 📖 Proje Hakkında

TeamMate Finder, öğrencilerin ortak projelerde birlikte çalışabilecekleri ekip arkadaşlarını kolayca bulabilmelerini amaçlayan bir platformdur.

Kullanıcılar;

- 👤 Hesap oluşturabilir.
- 📢 Proje ilanı paylaşabilir.
- 🔍 Diğer kullanıcıların projelerini inceleyebilir.
- 🤝 Takım daveti gönderebilir.
- 👥 Takım oluşturabilir.
- 💬 Gerçek zamanlı mesajlaşabilir.
- 🙍 Kullanıcı profillerini görüntüleyebilir.

---

## ✨ Özellikler

### 👤 Kullanıcı Sistemi

- Kayıt olma
- Giriş yapma
- Güvenli şifre saklama (`password_hash`)
- Çıkış yapma
- Profil düzenleme
- Profil fotoğrafı yükleme
- Hakkımda alanı
- Teknoloji ve ilgi alanı seçimi

---

### 📁 Proje Sistemi

- Proje ilanı oluşturma
- Tüm projeleri listeleme
- AJAX ile anlık proje arama
- Proje detay sayfası
- Proje sahibi bilgileri
- Proje silme (Sadece proje sahibi)

---

### 👥 Takım Sistemi

- Takım oluşturma
- Takımı görüntüleme
- Takım daveti gönderme
- Gelen davetleri kabul etme
- Gelen davetleri reddetme
- Takımdan ayrılma
- Takım boş kaldığında otomatik silinmesi

---

### 💬 Mesajlaşma

- Kullanıcılar arası özel mesajlaşma
- AJAX ile anlık mesaj güncelleme
- Okunmamış mesaj bildirimi
- Sohbet ekranı

---

### 🔔 Bildirimler

- Okunmamış mesaj sayısı
- Bekleyen takım istekleri
- Navbar bildirim rozetleri

---

# 🛠 Kullanılan Teknolojiler

| Backend | Frontend | Veritabanı | Geliştirme |
|----------|-----------|------------|-------------|
| PHP 8 | HTML5 | MySQL | XAMPP |
| PDO | CSS3 | phpMyAdmin | Visual Studio Code |
| | JavaScript | | |
| | AJAX | | |

---

# 🗄 Veritabanı

Projede **MySQL** kullanılmaktadır.

### Tablolar

- `users`
- `projects`
- `teams`
- `team_members`
- `team_requests`
- `messages`

---

# 🚀 Kurulum

### 1. Projeyi klonlayın

```bash
git clone https://github.com/kullaniciadi/teammate-finder.git
```

### 2. XAMPP içerisine taşıyın

```text
xampp/htdocs/teammate-finder
```

### 3. Veritabanını oluşturun

phpMyAdmin üzerinden yeni bir veritabanı oluşturun.

```text
teammate_finder
```

Ardından SQL dosyasını içe aktarın.

---

### 4. Veritabanı bağlantısını düzenleyin

`config/database.php`

```php
$host = "localhost";
$dbname = "teammate_finder";
$user = "root";
$pass = "";
```

---

### 5. Apache ve MySQL'i başlatın

XAMPP Control Panel üzerinden;

- Apache
- MySQL

servislerini çalıştırın.

---

### 6. Uygulamayı açın

```text
http://localhost/teammate-finder
```

---

# 🔒 Güvenlik

- Şifreler `password_hash()` ile saklanmaktadır.
- Giriş işlemleri Session yönetimi ile gerçekleştirilmektedir.
- SQL Injection saldırılarına karşı PDO Prepared Statements kullanılmaktadır.
- XSS saldırılarına karşı `htmlspecialchars()` kullanılmaktadır.

---

# 👨‍💻 Geliştirici

**Mert Ada**

Yapay Zeka Operatörlüğü Bölümü

---

# 📄 Lisans

Bu proje eğitim amacıyla geliştirilmiştir.
