# 🔄 Laravel Blade → Inertia.js + Vue 3 Dönüşüm Takibi

> Bu dosya oturum/kredi kesintisine karşı ilerlemeyi kalıcı tutar.
> Her yeniden başlarken ÖNCE bu dosyayı oku, kaldığın yerden devam et.
> Her partiden sonra `git add . && git commit` yap.

**Proje:** artirdim.com — Laravel 12 açık artırma platformu
**Hedef:** Tüm Blade view'ları Inertia.js + Vue 3'e çevir. Controller `view()` → `Inertia::render()`.
**Kurallar:** Tailwind EKLENMEYECEK. Mevcut `public/assets/css/*` ve class isimleri AYNEN korunacak. Route isimleri DEĞİŞMEYECEK (Ziggy ile çağrılacak). Form/validation/flash davranışı BİREBİR korunacak.

**Toplam Blade dosyası:** 79
- Vue Page'e çevrilecek: ~52
- Vue Component'e çevrilecek (partial/layout): ~16
- Blade olarak KALACAK (server-render): 5 → `emails/*` (3) + `errors/*` (2)

**Legend:** `[ ]` yapılmadı · `[x]` tamam · `[~]` devam ediyor

> ## ✅ TAMAMLANAN (son oturum)
> - **GRUP 0 — Altyapı: TAMAM** → Inertia + Vue 3 + Ziggy kuruldu, `HandleInertiaRequests` middleware, root `app.blade.php`, `resources/js/app.js`, `AppLayout.vue` (header+sidebar+footer), `AuthLayout.vue`, `AuctionCard.vue`, `StoryBar.vue`, `Pagination.vue`, `useClock.js`. KT (Metronic) bileşenleri her Inertia gezinmesinde yeniden başlatılıyor.
> - **GRUP 1 — Public: TAMAM** → Index, Browse/Auctions, Browse/Live, Browse/Explore, Contact, Corporate, Privacy. Controller'lar `Inertia::render`'a çevrildi (HomeController, BrowseController, PageController).
> - **GRUP 2 — Auth: TAMAM** → Login, Register (3-adım wizard), ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail, PendingApproval. Auth controller'ları + `EnsureUserIsVerified` middleware + web.php verify-email route Inertia'ya çevrildi. Login akışı test edildi (302 → dashboard ✅).
> - **GRUP 4 — Alıcı: KISMEN (5/6)** → Dashboard, Buyer/MyBids, Buyer/Favorites, Buyer/Orders/Index, Buyer/Orders/Show TAMAM (+ OrderProgress, OrderTimeline, ReviewForm bileşenleri). Controller/route'lar Inertia'ya çevrildi, curl ile component doğrulandı. **KALAN: messages/index** (harici `messages-index.js` polling içerdiğinden sonraki oturuma bırakıldı).
>
> ### 🔑 ÖNEMLİ: KARMA MOD (Mixed mode) çalışıyor
> Eski `resources/views/layouts/app.blade.php` **SİLİNMEDİ**. Henüz çevrilmemiş sayfalar (GRUP 3-7) hâlâ eski Blade layout ile sorunsuz render ediliyor. Yani site şu an %100 çalışır durumda. Bir grubu bitirince ilgili controller'ı `Inertia::render`'a çevir; gerisi otomatik.
>
> ### ▶️ SONRAKİ OTURUM NASIL DEVAM EDER
> 1. `cd /app/laravel_project/project`
> 2. İlgili blade + controller + route üçlüsünü aç (tablodaki eşleme hazır)
> 3. `resources/js/Pages/...vue` oluştur (`layout: AppLayout` pattern'i — bkz. Index.vue)
> 4. Controller'da `return view(...)` → `return Inertia::render(...)`, tüm compact/with verilerini props yap
> 5. `npm run build` → `sudo supervisorctl restart laravel` → test → commit
> 6. Auction listeleri için `\$auction->toCard()` + `<AuctionCard>` kullan (hazır)

---

## 🟦 GRUP 0 — Altyapı (ÖNCE bu bitmeli)
- [ ] `composer require inertiajs/inertia-laravel tightenco/ziggy`
- [ ] `npm i @inertiajs/vue3 vue @vitejs/plugin-vue`
- [ ] `vite.config.js` → vue plugin + `resources/js/app.js` giriş noktası
- [ ] `resources/views/app.blade.php` → tek Inertia kök layout (`@inertia`, `@routes`, `@vite`)
- [ ] `app/Http/Middleware/HandleInertiaRequests.php` (auth user, flash, csrf, ziggy share)
- [ ] `bootstrap/app.php`'a Inertia middleware kaydı
- [ ] `resources/js/app.js` → `createInertiaApp` + Vue + Ziggy `ZiggyVue`
- [ ] **Layout:** `resources/js/Layouts/AppLayout.vue` ← `layouts/app` + `partials/header` + `partials/sidebar`
- [ ] **Layout:** `resources/js/Layouts/AuthLayout.vue` ← `auth/layouts/{master,header,footer}`

## 🟩 GRUP 1 — Public / Front Sayfalar (7)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| Ana Sayfa | `index` | `HomeController@index` | `index` | [ ] | [ ] | [ ] | [ ] |
| Müzayedeler | `browse/auctions` | `BrowseController@auctions` | `browse.auctions` | [ ] | [ ] | [ ] | [ ] |
| Canlı | `browse/live` | `BrowseController@live` | `browse.live` | [ ] | [ ] | [ ] | [ ] |
| Keşfet | `browse/explore` | `BrowseController@explore` | `browse.explore` | [ ] | [ ] | [ ] | [ ] |
| Kurumsal | `corporta` | `PageController@corporate` | `corporate` | [ ] | [ ] | [ ] | [ ] |
| Gizlilik | `privay-policy` | `PageController@privacy_policy` | `privacy` | [ ] | [ ] | [ ] | [ ] |
| İletişim | `contact` | `PageController@contact` | `contact` | [ ] | [ ] | [ ] | [ ] |

## 🟨 GRUP 2 — Auth Sayfalar (7)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| Giriş | `auth/login` | `AuthenticatedSessionController@create` | `login` | [ ] | [ ] | [ ] | [ ] |
| Kayıt | `auth/register` | `RegisteredUserController@create` | `register` | [ ] | [ ] | [ ] | [ ] |
| Şifremi Unuttum | `auth/forgot-password` | `PasswordResetLinkController@create` | `password.request` | [ ] | [ ] | [ ] | [ ] |
| Şifre Sıfırla | `auth/reset-password` | `NewPasswordController@create` | `password.reset` | [ ] | [ ] | [ ] | [ ] |
| Şifre Onayla | `auth/confirm-password` | `ConfirmablePasswordController@show` | `password.confirm` | [ ] | [ ] | [ ] | [ ] |
| E-posta Doğrula | `auth/verify-email` | `EmailVerificationPromptController` | `verification.notice` | [ ] | [ ] | [ ] | [ ] |
| Onay Bekliyor | `auth/pending-approval` | (verified.account middleware) | — | [ ] | [ ] | [ ] | [ ] |

## 🟧 GRUP 3 — İlan Detay & Profil (5)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| İlan Detay | `auctionsnew` | `BidController@show` | `auctions.show` | [ ] | [ ] | [ ] | [ ] |
| Canlı Yayın (satıcı) | `auctions` | `BroadcastController@show` | `seller.auctions.broadcast` | [ ] | [ ] | [ ] | [ ] |
| Profil (public) | `profile/show` | `ProfileController@show` | `profile.public` | [ ] | [ ] | [ ] | [ ] |
| Profil Düzenle | `profile/edit` | `ProfileController@edit` | `profile.edit` | [ ] | [ ] | [ ] | [ ] |
| Takip Listesi | `profile/follow-list` | `FollowController@followers/following` | `profile.followers/following` | [ ] | [ ] | [ ] | [ ] |

## 🟪 GRUP 4 — Alıcı & Mesajlaşma (6)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| Dashboard | `dashboard` | (inline route) | `dashboard` | [ ] | [ ] | [ ] | [ ] |
| Tekliflerim | `buyer/my-bids` | (inline route) | `my-bids` | [ ] | [ ] | [ ] | [ ] |
| Favoriler | `buyer/favorites` | (inline route) | `favorites` | [ ] | [ ] | [ ] | [ ] |
| Siparişlerim | `buyer/orders/index` | `OrderController@index` | `orders.index` | [ ] | [ ] | [ ] | [ ] |
| Sipariş Detay | `buyer/orders/show` | `OrderController@show` | `orders.show` | [ ] | [ ] | [ ] | [ ] |
| Mesajlar | `messages/index` | `MessageController@index` | `messages.index` | [ ] | [ ] | [ ] | [ ] |

## 🟫 GRUP 5 — Bakiye / Bildirim / Destek (8)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| Bakiye | `general/balance/index` | `BalanceController@index` | `general.balance.index` | [ ] | [ ] | [ ] | [ ] |
| Bakiye Yükle | `general/balance/create` | `BalanceController@create` | `general.balance.create` | [ ] | [ ] | [ ] | [ ] |
| Para Çek | `general/balance/withdraw` | `BalanceController@withdrawCreate` | `general.balance.withdraw.create` | [ ] | [ ] | [ ] | [ ] |
| İşlem Detay | `general/balance/show` | `BalanceController@show` | `general.balance.show` | [ ] | [ ] | [ ] | [ ] |
| Bildirimler | `general/notifications` | `NotificationController@index` | `notifications.index` | [ ] | [ ] | [ ] | [ ] |
| Destek | `general/support/index` | `SupportController@index` | `support.index` | [ ] | [ ] | [ ] | [ ] |
| Destek Oluştur | `general/support/create` | `SupportController@create` | `support.create` | [ ] | [ ] | [ ] | [ ] |
| Destek Detay | `general/support/show` | `SupportController@show` | `support.show` | [ ] | [ ] | [ ] | [ ] |

## 🟥 GRUP 6 — Satıcı Paneli (7)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| Satıcı Dashboard | `seller/panel/dashboard` | `Seller\DashboardController@index` | `seller.dashboard` | [ ] | [ ] | [ ] | [ ] |
| İlanlarım | `seller/auctions/index` | `Seller\AuctionController@index` | `seller.auctions.index` | [ ] | [ ] | [ ] | [ ] |
| İlan Oluştur | `seller/auctions/create` | `Seller\AuctionController@create` | `seller.auctions.create` | [ ] | [ ] | [ ] | [ ] |
| İlan Düzenle | `seller/auctions/edit` | `Seller\AuctionController@edit` | `seller.auctions.edit` | [ ] | [ ] | [ ] | [ ] |
| İlan Göster | `seller/auctions/show` | `Seller\AuctionController@show` | `seller.auctions.show` | [ ] | [ ] | [ ] | [ ] |
| Satışlarım | `seller/sales/index` | `Seller\SaleController@index` | `seller.sales.index` | [ ] | [ ] | [ ] | [ ] |
| Satış Detay | `seller/sales/show` | `Seller\SaleController@show` | `seller.sales.show` | [ ] | [ ] | [ ] | [ ] |
| Satıcı Profil | `seller/profile/edit` | `Seller\ProfileController@edit` | `seller.profile.edit` | [ ] | [ ] | [ ] | [ ] |

## ⬛ GRUP 7 — Admin Paneli (16)
| Sayfa | Blade | Controller | Route | Vue | Ctrl→Inertia | Route ✓ | Test ✓ |
|---|---|---|---|---|---|---|---|
| Admin Dashboard | `admin/dashboard` | `Admin\DashboardController@index` | `admin.dashboard` | [ ] | [ ] | [ ] | [ ] |
| Kullanıcılar | `admin/users/index` | `Admin\UserController@index` | `admin.users.index` | [ ] | [ ] | [ ] | [ ] |
| Kullanıcı Göster | `admin/users/show` | `Admin\UserController@show` | `admin.users.show` | [ ] | [ ] | [ ] | [ ] |
| Kullanıcı Düzenle | `admin/users/edit` | `Admin\UserController@edit` | `admin.users.edit` | [ ] | [ ] | [ ] | [ ] |
| Kategoriler | `admin/categories/index` | `Admin\CategoryController@index` | `admin.categories.index` | [ ] | [ ] | [ ] | [ ] |
| Kategori Oluştur | `admin/categories/create` | `Admin\CategoryController@create` | `admin.categories.create` | [ ] | [ ] | [ ] | [ ] |
| Kategori Düzenle | `admin/categories/edit` | `Admin\CategoryController@edit` | `admin.categories.edit` | [ ] | [ ] | [ ] | [ ] |
| Kategori Göster | `admin/categories/show` | `Admin\CategoryController@show` | `admin.categories.show` | [ ] | [ ] | [ ] | [ ] |
| Admin İlanlar | `admin/auctions/index` | `Admin\AuctionController@index` | `admin.auctions.index` | [ ] | [ ] | [ ] | [ ] |
| Admin İlan Göster | `admin/auctions/show` | `Admin\AuctionController@show` | `admin.auctions.show` | [ ] | [ ] | [ ] | [ ] |
| Admin İlan Düzenle | `admin/auctions/edit` | `Admin\AuctionController@edit` | `admin.auctions.edit` | [ ] | [ ] | [ ] | [ ] |
| Admin Siparişler | `admin/orders/index` | `Admin\OrderController@index` | `admin.orders.index` | [ ] | [ ] | [ ] | [ ] |
| Admin Sipariş Detay | `admin/orders/show` | `Admin\OrderController@show` | `admin.orders.show` | [ ] | [ ] | [ ] | [ ] |
| Admin Destek | `admin/support/index` | `Admin\SupportController@index` | `admin.support.index` | [ ] | [ ] | [ ] | [ ] |
| Admin Destek Detay | `admin/support/show` | `Admin\SupportController@show` | `admin.support.show` | [ ] | [ ] | [ ] | [ ] |
| Ayarlar | `admin/settings/index` | `Admin\SettingsController@index` | `admin.settings.index` | [ ] | [ ] | [ ] | [ ] |

## 🧩 Ortak Component'ler (sayfalarla birlikte yapılacak)
- [ ] `partials/stars` → `Components/Stars.vue`
- [ ] `partials/order-progress` → `Components/OrderProgress.vue`
- [ ] `partials/order-timeline` → `Components/OrderTimeline.vue`
- [ ] `partials/review-form` → `Components/ReviewForm.vue`
- [ ] `partials/story-bar` → `Components/StoryBar.vue`
- [ ] `partials/story-viewer` → `Components/StoryViewer.vue`
- [ ] `partials/story-upload` → `Components/StoryUpload.vue`
- [ ] `partials/profile-stories` → `Components/ProfileStories.vue`
- [ ] `partials/category-select-options` → `Components/CategorySelectOptions.vue`
- [ ] `browse/card` → `Components/AuctionCard.vue`

## 📭 Blade olarak KALACAK (dönüştürülmeyecek)
- `emails/contact`, `emails/reset-password`, `emails/verify-custom` (mail render)
- `errors/404`, `errors/maintenance` (Laravel error render)
- `auctions.blade.php` yerine geçen `old-live.blade.php` → LEGACY (kullanılmıyorsa dokunma)

---

## 🐞 Bilinen sorunlar / notlar
- Reverb (WebSocket) canlı yayın var; Inertia'ya geçerken Echo entegrasyonu component `onMounted`'a taşınacak.
- Bazı sayfalar AJAX polling kullanıyor (chat, live-state, messages poll) — bunlar Inertia partial reload veya mevcut fetch mantığıyla korunacak.
- `public/assets/js/custom/*` içindeki mevcut JS davranışları component'lere taşınacak, silinmeyecek.

## ✅ Genel İlerleme
- [x] GRUP 0 · [x] GRUP 1 · [x] GRUP 2 · [ ] GRUP 3 · [~] GRUP 4 (5/6, messages kaldı) · [ ] GRUP 5 · [ ] GRUP 6 · [ ] GRUP 7
