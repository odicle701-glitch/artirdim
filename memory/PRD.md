# PRD — artirdim: Laravel Blade → Inertia.js + Vue 3 Dönüşümü

## Problem Statement (orijinal)
Kullanıcının mevcut Laravel + Blade açık artırma projesini (GitHub: cguvencx/artirdim) **Laravel + Inertia.js + Vue 3**'e dönüştürmek. Tüm `.blade.php` → Vue 3 component; controller'larda `return view(...)` → `Inertia::render(...)`. Tailwind EKLENMEYECEK, mevcut CSS/class isimleri korunacak, route isimleri değişmeyecek (Ziggy), form/validation/flash davranışı birebir korunacak. İlerleme `CONVERSION_PROGRESS.md` ile takip edilecek, her aşamada commit.

## Mimari
- Laravel 12 (PHP 8.2) + MySQL/MariaDB, monolit
- Inertia.js + Vue 3 (Composition API), Vite production build
- Ziggy ile JS route helper
- Metronic (KeenThemes) Bootstrap teması korundu (Tailwind yok)
- **Karma mod:** çevrilmemiş sayfalar eski Blade layout ile çalışmaya devam eder

## Ortam / Servisler
- Laravel: supervisor `laravel` programı, port 3000 (preview ingress)
- DB: supervisor `mariadb` programı; db=auction user=auction pass=auction123
- Preview: https://laravel-refresh-1.preview.emergentagent.com

## Test Kullanıcıları (şifre: password)
- admin@test.com, seller@test.com, buyer@test.com

## Tamamlananlar (2026-06)
- **GRUP 0 — Altyapı:** inertia-laravel + ziggy + vue3 kuruldu; `app.blade.php` kök layout, `app.js`, `HandleInertiaRequests`, `AppLayout.vue`, `AuthLayout.vue`, `AuctionCard/StoryBar/Pagination`, `useClock.js`. Metronic KT bileşenleri gezinme sonrası re-init.
- **GRUP 1 — Public (7):** Index, Browse/Auctions, Browse/Live, Browse/Explore, Contact, Corporate, Privacy. Görsel + curl doğrulandı.
- **GRUP 2 — Auth (7):** Login, Register (3-adım wizard), ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail, PendingApproval. Login akışı test edildi (302 → dashboard).
- **GRUP 4 — Alıcı (5/6):** Dashboard, MyBids, Favorites, Orders/Index, Orders/Show (+ OrderProgress/OrderTimeline/ReviewForm). Curl ile component doğrulandı.

## Kalan Backlog (öncelik sırası)
- **P0 — GRUP 3:** Auction detay (`auctionsnew` — canlı teklif/Reverb), Broadcast (WebRTC), Profil (show/edit/follow-list). En kritik/karmaşık (realtime).
- **P1 — GRUP 4 kalan:** `messages/index` (harici polling JS → Vue).
- **P1 — GRUP 5:** Balance (index/create/withdraw/show), Notifications, Support (index/create/show).
- **P2 — GRUP 6:** Satıcı paneli. **P2 — GRUP 7:** Admin paneli (16 sayfa).
- Tüm gruplar bitince eski `layouts/app.blade.php` + `auth/layouts/*` kaldırılabilir.

## Notlar / Riskler
- Story: harici JS korunarak modaller `app.blade.php`'de kalıcı DOM; StoryBar.vue `window.STORY_DATA`'yı besliyor.
- Realtime (Reverb) ve chat polling Vue'ya taşınırken Echo/fetch `onMounted`'a alınmalı.
- `emails/*` ve `errors/*` Blade kalacak.
