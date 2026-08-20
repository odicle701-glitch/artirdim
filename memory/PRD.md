# PRD — artirdim.com Laravel Açık Artırma Platformu

## Original Problem Statement
Kullanıcı mevcut Laravel 12 (PHP 8.2) projesini ZIP olarak paylaştı ve şu isteklerde bulundu:
1. Blade dosyalarında inline `<script>` / `<style>` bloklarının **tamamen** temizlenmesi (mevcut `public/assets/{js,css}` yapısına taşınarak)
2. Sayfa yenilemesi hissiyatı yerine AJAX kullanımı (silme, hikaye ekleme vb.)
3. Login/Register UI modernizasyonu
4. Canlı yayın (broadcast) yönetiminde iyileştirmeler (birden fazla alt talep)
5. GitHub'a push için sorunsuz proje yapısı

## Tech Stack
- **Backend**: Laravel 12 · PHP 8.2 · MariaDB (MySQL uyumlu)
- **Frontend**: Blade + Bootstrap 5 + Vite + Vanilla JS + jQuery (sadece bazı yerler)
- **Real-time**: Laravel Reverb (Pusher protokolü)
- **Search**: Meilisearch + Laravel Scout
- **Media**: Spatie MediaLibrary
- **Auth**: Laravel Breeze + Socialite (Google OAuth)
- **Roles**: Spatie Permission (admin/seller/buyer)

## User Personas
- **Admin**: Site yönetimi, onaylar, kategoriler, kullanıcılar, destek talepleri
- **Satıcı (Seller)**: İlan oluşturma, canlı yayın başlatma, sipariş yönetimi, hikaye paylaşma
- **Alıcı (Buyer)**: Teklif verme, bakiye yükleme, sipariş takibi, mesajlaşma, favoriler

## What's Been Implemented (This Session)

### Iterasyon 1 — Inline JS/CSS Temizliği (2026-01-19)
- 15 blade dosyasındaki ~830 satır inline `<script>` ve `<style>` bloğu `public/assets/js/custom/` ve `public/assets/css/` altındaki modüler dosyalara taşındı
- Yeni JS dosyaları: `theme/image-fallback.js`, `story-data.js`, `admin-users-edit.js`, `admin-support-show.js`, `support-show.js`, `admin-settings.js`, `auth-register.js`, `profile-edit.js`, `auctions-config.js`, `auctions-new-config.js`, `old-live-config.js`, `index-sort.js`
- Yeni CSS: `balance-create.css`
- Blade'lere `#*Root` config div'leri eklendi (data-* attribute'larla config aktarımı)
- **Test edildi**: iteration_1.json — 9/9 senaryo geçti

### Iterasyon 2 — Group 1 (2026-01-19)
- **Login/Register UI modernize**: Yeni sağ panel (`.auth-right`) — canlı ilan sayacı, büyük marka başlığı, 3 stat kartı (LIVE/24/7/+1K), hero badge, alt özellikler (SSL/Onaylı Satıcılar/7-24 Destek); glassmorphism form kartı
- **Global AJAX silme yardımcısı** (`public/assets/js/custom/theme/ajax-delete.js`): `window.ajaxDeleteForm(form)` + `window.ajaxToast(icon, msg)` — tüm delete formları sayfa yenilemeden çalışıyor
- **Controller'lar AJAX-ready**: Admin\CategoryController, Admin\UserController, Admin\AuctionController, Seller\AuctionController, General\StoryController — `wantsJson()` check ile JSON response dönüyor
- **Story upload AJAX**: `story-upload.js` fetch ile submit, modal otomatik kapanıyor
- **Seller Dashboard "Canlı Yayına Başla" prominent kart** (`.seller-live-card`) — aktif ilanları listeliyor, hızlı erişim; ayrıca **sidebar'da "Canlı Yayın" kısayolu** (badge ile)
- **Auction detay** "Canlı İzle" sekmesi `is_live=0` ise `d-none` (kamera açılmadıkça gizli)
- **Auction modeli** `HasMedia` interface implementasyonu eklendi (pre-existing bug fix)
- Seed'e test satıcı için `SellerProfile` eklendi (login bug fix)
- **Test edildi**: iteration_2.json — 9/9 senaryo geçti

### GitHub Push Hazırlığı
- `.gitignore` genişletildi (Laravel vendor/, node_modules/, storage logs, .env)
- `README.md` güncellendi (proje yapısı, kurulum, test hesapları)
- `.env.example` sıfırlandı (production template)
- `project.zip` (147MB) silindi

## Test Credentials
`/app/memory/test_credentials.md` içinde. Preview URL: https://feature-boost-82.preview.emergentagent.com

## Prioritized Backlog

### 🟡 Group 2 — Bir sonraki iterasyon
- İlana **lot adet** alanı ekle (migration + form + gösterim)
- Canlı yayın: yayıncıyı izleyici olarak sayma (viewer count +1 bug fix)
- Canlı yayın yönetim sayfası **Twitch tarzı yeni tasarım** (chat video üzerinde overlay)

### 🔴 Group 3 — En zor
- Kamera açma / WebRTC stream **bug fix** (görüntü karşıya gitmiyor)
- İlana **gerçek promo video** yükleme (form + storage + player) — mevcut sistemde sadece URL vardı

### 🔵 Cosmetic (opsiyonel)
- Admin AJAX delete sonrası stat kartları da güncellensin (şu an sayfayı yenileyene kadar eski sayı görünüyor)
- Seed image URL'leri düzenle (403 console noise'u kaldırmak için)

## Preview Notu
Emergent önizleme ortamı **Laravel'i kalıcı barındırmaz** (pod restart olunca PHP/DB sıfırlanır). Production için `laravel_project/project/KURULUM.md`'deki VPS adımlarını takip et.
