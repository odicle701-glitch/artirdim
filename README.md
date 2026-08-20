# artirdim.com — Laravel Auction Platform

Canlı açık artırma platformu · Laravel 12 (PHP 8.2) + MariaDB + Vite

## 📂 Proje Yapısı

```
/app
├── laravel_project/
│   └── project/          ← Laravel projesi (composer.json, artisan burada)
│       ├── app/
│       ├── resources/
│       ├── routes/
│       ├── public/
│       ├── database/
│       ├── KURULUM.md    ← Detaylı kurulum rehberi
│       └── ...
├── backend/              ← Emergent varsayılan iskele (kullanılmıyor)
├── frontend/             ← Emergent varsayılan iskele (kullanılmıyor)
└── memory/
    ├── PRD.md            ← Ürün gereksinimleri & ilerleme
    └── test_credentials.md
```

## 🚀 Hızlı Başlangıç

Detaylı kurulum için: [`laravel_project/project/KURULUM.md`](./laravel_project/project/KURULUM.md)

Özet:
```bash
cd laravel_project/project
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=AuctionSeeder
php artisan storage:link
npm install && npm run build
php artisan serve
```

## 👥 Test Hesapları

| Rol | E-posta | Şifre |
|-----|---------|-------|
| Admin | `admin@test.com` | `password` |
| Satıcı | `seller@test.com` | `password` |
| Alıcı | `buyer@test.com` | `password` |

## 🎯 Öne Çıkan Özellikler

- 🎯 Canlı açık artırma + WebRTC yayın (Laravel Reverb)
- 💬 Sohbet + mesajlaşma + hikayeler (Instagram tarzı)
- 💳 Bakiye yükleme (kredi kartı / havale / papara)
- 📦 Sipariş & anlaşmazlık yönetimi
- ⭐ Satıcı değerlendirme sistemi
- 👑 Admin paneli (kullanıcı, kategori, müzayede yönetimi)
- 🔐 Rol tabanlı yetkilendirme (admin/seller/buyer)
- 📱 Modern, mobil uyumlu koyu tema

## 🧪 Geliştirme Notları

- **Blade dosyalarında inline `<script>` veya `<style>` YOKTUR.** Tüm JS/CSS `public/assets/js/custom/` ve `public/assets/css/` altında modüler dosyalarda tutulur.
- **AJAX silme yardımcısı**: `public/assets/js/custom/theme/ajax-delete.js` — tüm delete formları sayfa yenilenmeden çalışır.
- **Hikaye ekleme AJAX**: form submit sayfa yenilemesiz gönderilir.
- **Canlı yayın**: Satıcı Paneli'ndeki büyük "Canlı Yayına Başla" kartından hızlı erişim.

## 📄 Lisans

MIT
