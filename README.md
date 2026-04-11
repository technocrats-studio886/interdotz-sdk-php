# Interdotz PHP SDK

Official PHP SDK untuk ekosistem Interdotz. Integrasikan SSO, manajemen akun, dan pembayaran Dots Unit (DU) ke produk kamu dalam hitungan menit.

---

## Daftar Isi

1. [Tentang SDK Ini](#tentang-sdk-ini)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Setup](#setup)
5. [Use Case 1 — SSO Login & Register](#use-case-1--sso-login--register)
6. [Use Case 2 — Direct Charge](#use-case-2--direct-charge)
7. [Use Case 3 — Charge dengan Konfirmasi User](#use-case-3--charge-dengan-konfirmasi-user)
8. [Use Case 4 — Cek Saldo Sebelum Charge](#use-case-4--cek-saldo-sebelum-charge)
9. [Use Case 5 — Webhook Handler](#use-case-5--webhook-handler)
10. [Error Handling](#error-handling)
11. [API Reference](#api-reference)
12. [Changelog](#changelog)

---

## Tentang SDK Ini

Interdotz adalah platform terpusat yang menyediakan:

- **Akun tunggal** — user cukup daftar sekali, bisa pakai semua produk dalam ekosistem
- **Dots Unit (DU)** — mata uang digital ekosistem yang bisa diisi ulang dan digunakan di semua produk

SDK ini adalah jembatan resmi antara produk PHP kamu dengan layanan Interdotz. Tidak perlu implementasi auth atau payment dari nol — cukup integrasikan SDK ini.

```
Produk kamu  ──────────────────▶  Interdotz SDK  ──────────────▶  Interdotz API
(Laravel App)                     (Package ini)                   (Auth + Payment)
```

### Apa yang bisa dilakukan SDK ini?

| Fitur | Deskripsi |
|-------|-----------|
| **SSO** | Redirect user ke halaman login/register Interdotz, terima token balik |
| **Client Auth** | Dapatkan access token untuk operasi payment atas nama user |
| **Direct Charge** | Potong DU user langsung tanpa konfirmasi |
| **Charge + Konfirmasi** | Tampilkan halaman konfirmasi ke user sebelum DU dipotong |
| **Cek Saldo** | Cek saldo DU user sebelum melakukan charge |
| **Webhook** | Parse dan handle notifikasi charge dari Interdotz |

---

## Requirements

- PHP **^8.2**
- [Guzzle HTTP](https://docs.guzzlephp.org) **^7.0**

---

## Installation

### Via Composer (Public Packagist)

```bash
composer require interdotz/sdk-php
```

### Via Private Git Repository

Tambahkan repository berikut di `composer.json` project kamu:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/interdotz/sdk-php"
        }
    ],
    "require": {
        "interdotz/sdk-php": "^1.0"
    }
}
```

```bash
composer install
```

---

## Setup

Sebelum mulai, pastikan kamu sudah punya:

- `client_id` — ID unik produk kamu, didapat dari admin Interdotz
- `client_secret` — Secret key produk kamu (simpan di `.env`, jangan di-commit)

### Inisialisasi Client

```php
use Interdotz\Sdk\InterdotzClient;

$client = new InterdotzClient(
    clientId:     env('INTERDOTZ_CLIENT_ID'),
    clientSecret: env('INTERDOTZ_CLIENT_SECRET'),
);
```

Satu instance `InterdotzClient` cukup untuk semua operasi. URL API dan SSO sudah hardcode di dalam SDK, tidak perlu konfigurasi tambahan.

---

## Use Case 1 — SSO Login & Register

User tidak perlu buat akun baru di produk kamu. Cukup login/register sekali di Interdotz, token langsung balik ke produk kamu.

### Flow

```
User klik "Login"
    │
    ▼
Produk kamu generate URL → redirect ke interdotz.com/login?client_id=xxx&redirect_url=...
    │
    ▼
User login di Interdotz
    │
    ▼
Interdotz redirect balik ke redirect_url?access_token=...&refresh_token=...
    │
    ▼
Produk kamu panggil handleCallback() → dapat token
```

### Login via SSO

```php
// Step 1 — Generate URL dan redirect user ke Interdotz
$loginUrl = $client->sso()->getLoginUrl(
    redirectUrl: 'https://myapp.com/auth/callback',
);

header("Location: $loginUrl");
exit;
```

### Register via SSO

```php
// Sama seperti login, tapi arahkan ke halaman register
$registerUrl = $client->sso()->getRegisterUrl(
    redirectUrl: 'https://myapp.com/auth/callback',
    state:       'dashboard', // opsional — dikembalikan di callback
);

header("Location: $registerUrl");
exit;
```

### Handle Callback

Setelah user berhasil login/register, Interdotz redirect browser ke `redirectUrl` kamu dengan token sebagai query params:

```
https://myapp.com/auth/callback
  ?access_token=eyJhbGci...
  &refresh_token=eyJhbGci...
  &token_type=Bearer
  &state=dashboard
```

```php
use Interdotz\Sdk\Exceptions\AuthException;

try {
    $tokens = $client->sso()->handleCallback($_GET);

    // Simpan ke session
    $_SESSION['interdotz_access_token']  = $tokens->accessToken;
    $_SESSION['interdotz_refresh_token'] = $tokens->refreshToken;

    // Redirect ke halaman tujuan
    $destination = $tokens->state === 'dashboard' ? '/dashboard' : '/';
    header("Location: $destination");
    exit;

} catch (AuthException $e) {
    // Token tidak ada atau invalid di query params
    header("Location: /login?error=sso_failed");
    exit;
}
```

**Properties yang tersedia di `SsoCallbackResponse`:**

| Property | Tipe | Deskripsi |
|----------|------|-----------|
| `accessToken` | `string` | Token untuk API calls |
| `refreshToken` | `string` | Token untuk refresh sesi |
| `tokenType` | `string` | Selalu `"Bearer"` |
| `state` | `string\|null` | State yang kamu kirim saat generate URL |

---

## Use Case 2 — Direct Charge

Potong saldo DU user langsung tanpa konfirmasi tambahan. Cocok untuk transaksi kecil atau produk yang sudah dipercaya user.

### Flow

```
User trigger transaksi di produk kamu
    │
    ▼
Produk panggil auth()->authenticate() → dapat access token
    │
    ▼
Produk panggil payment()->directCharge() → DU langsung terpotong
    │
    ▼
Interdotz kirim webhook notifikasi ke produk kamu
```

### Implementasi

```php
use Interdotz\Sdk\Exceptions\AuthException;
use Interdotz\Sdk\Exceptions\InsufficientBalanceException;
use Interdotz\Sdk\Exceptions\PaymentException;

try {
    // Step 1 — Dapatkan token untuk user ini
    $token = $client->auth()->authenticate(userId: $user->interdotz_id);

    // Step 2 — Lakukan charge
    $charge = $client->payment()->directCharge(
        accessToken:   $token->accessToken,
        amount:        50,               // jumlah DU yang dipotong
        referenceType: 'PURCHASE',       // kategori transaksi, bebas didefinisikan
        referenceId:   'order-001',      // ID unik dari sisi produk kamu — HARUS UNIK
    );

    // Charge berhasil
    echo $charge->transactionId;  // ID transaksi di Interdotz
    echo $charge->amountCharged;  // 50
    echo $charge->balanceBefore;  // saldo sebelum charge
    echo $charge->balanceAfter;   // saldo setelah charge
    echo $charge->createdAt;      // timestamp transaksi

} catch (InsufficientBalanceException $e) {
    // Saldo DU user tidak cukup — arahkan user untuk topup
} catch (AuthException $e) {
    // Kredensial salah atau token expired
} catch (PaymentException $e) {
    // Error lainnya — cek $e->getCode() untuk HTTP status
    // HTTP 409 = referenceId duplikat
}
```

> **Penting:** `referenceId` harus unik per transaksi. Request dengan `referenceId` yang sama akan ditolak dengan HTTP 409.

**Properties yang tersedia di `ChargeResponse`:**

| Property | Tipe | Deskripsi |
|----------|------|-----------|
| `transactionId` | `string` | ID transaksi di Interdotz |
| `userId` | `string` | ID user yang di-charge |
| `coinType` | `string` | Selalu `"DU"` |
| `amountCharged` | `int` | Jumlah DU yang dipotong |
| `balanceBefore` | `int` | Saldo DU sebelum charge |
| `balanceAfter` | `int` | Saldo DU setelah charge |
| `referenceType` | `string` | Kategori transaksi |
| `referenceId` | `string` | ID transaksi dari sisi produk |
| `createdAt` | `string` | Timestamp transaksi (ISO 8601) |

---

## Use Case 3 — Charge dengan Konfirmasi User

User diarahkan ke halaman konfirmasi Interdotz sebelum DU dipotong. Lebih aman dan transparan — user bisa lihat detail transaksi dan memilih untuk konfirmasi atau tolak.

### Flow

```
Produk buat charge request → dapat redirectUrl
    │
    ▼
User diarahkan ke halaman konfirmasi Interdotz
(tampil: nama produk, jumlah DU, deskripsi, saldo saat ini)
    │
    ├── User klik Konfirmasi → DU dipotong → redirect ke callbackUrl?status=confirmed
    │
    └── User klik Tolak → redirect ke callbackUrl?status=rejected
    │
    ▼
Produk update status order (jangan andalkan redirect, tunggu webhook)
```

### Step 1 — Buat Charge Request

```php
$token = $client->auth()->authenticate($user->interdotz_id);

$chargeRequest = $client->payment()->createChargeRequest(
    accessToken:   $token->accessToken,
    userId:        $user->interdotz_id,
    amount:        100,
    referenceType: 'SUBSCRIPTION',
    referenceId:   'sub-premium-april-2024',    // harus unik
    callbackUrl:   'https://myapp.com/payment/callback',
    description:   'Langganan Plan Premium — April 2024',  // ditampilkan ke user
    productLogo:   'https://myapp.com/logo.png',           // logo di halaman konfirmasi
);

// Redirect user ke halaman konfirmasi
header("Location: {$chargeRequest->redirectUrl}");
exit;
```

> Token charge berlaku **15 menit**. Jika expired, user perlu mulai ulang proses.

**Properties yang tersedia di `ChargeRequestResponse`:**

| Property | Tipe | Deskripsi |
|----------|------|-----------|
| `token` | `string` | Token charge |
| `redirectUrl` | `string` | URL halaman konfirmasi — redirect user ke sini |
| `expiresAt` | `string` | Waktu kedaluwarsa token (ISO 8601) |

### Step 2 — Terima Callback Redirect

Setelah user konfirmasi atau tolak:

```
https://myapp.com/payment/callback?status=confirmed&referenceId=sub-premium-april-2024
https://myapp.com/payment/callback?status=rejected&referenceId=sub-premium-april-2024
```

```php
$status      = $_GET['status'];       // 'confirmed' atau 'rejected'
$referenceId = $_GET['referenceId'];

if ($status === 'rejected') {
    // Tandai order sebagai cancelled
    // Redirect ke halaman order
    return;
}

// Status confirmed — tandai order sebagai pending
// Jangan langsung fulfill — tunggu konfirmasi final via webhook
```

> **Jangan** langsung proses fulfillment dari callback redirect. Redirect bisa dimanipulasi. Gunakan **webhook** sebagai sumber kebenaran.

---

## Use Case 4 — Cek Saldo Sebelum Charge

Tampilkan saldo DU user sebelum checkout, atau validasi saldo cukup sebelum melakukan charge.

```php
$token   = $client->auth()->authenticate($user->interdotz_id);
$balance = $client->payment()->getBalance(
    accessToken: $token->accessToken,
    userId:      $user->interdotz_id,
);

// Ambil saldo DU langsung
$duBalance = $balance->getDotsUnitBalance(); // int | null

if ($duBalance < $requiredAmount) {
    // Saldo tidak cukup, arahkan user untuk topup
}

// Atau iterasi semua jenis koin
foreach ($balance->balances as $coin) {
    echo "{$coin['coinTypeName']}: {$coin['balance']} {$coin['symbol']}";
    // contoh: "Dots Unit: 150 DU"
}
```

**Properties yang tersedia di `BalanceResponse`:**

| Property / Method | Tipe | Deskripsi |
|-------------------|------|-----------|
| `userId` | `string` | ID user |
| `balances` | `array` | Semua jenis koin dan saldonya |
| `getDotsUnitBalance()` | `int\|null` | Shortcut untuk saldo DU — `null` jika tidak ditemukan |

---

## Use Case 5 — Webhook Handler

Setiap charge yang berhasil atau gagal akan dikirim sebagai HTTP POST ke `webhook_url` yang kamu daftarkan saat registrasi client. Gunakan webhook ini sebagai **satu-satunya sumber kebenaran** untuk memproses transaksi.

### Setup Route

```php
// Pastikan route ini tidak diproteksi CSRF
Route::post('/webhook/interdotz', [WebhookController::class, 'handle']);
```

### Parse Payload

```php
use Interdotz\Sdk\Exceptions\InterdotzException;

$rawBody = file_get_contents('php://input');

try {
    $payload = $client->webhook()->parse($rawBody);

    if ($payload->isSuccess()) {
        $transactionId = $payload->data['transactionId'];
        $referenceId   = $payload->data['referenceId'];
        $amountCharged = $payload->data['amount'];
        $balanceAfter  = $payload->data['balanceAfter'];

        // Proses fulfillment order di sini
        // Contoh: aktifkan langganan, kirim produk digital, dll.
    }

    if ($payload->isFailed()) {
        $referenceId  = $payload->data['referenceId'];
        $errorMessage = $payload->data['errorMessage'];

        // Batalkan order, notifikasi user
    }

    http_response_code(200);
    echo json_encode(['message' => 'OK']);

} catch (InterdotzException $e) {
    // Payload tidak valid atau bukan JSON
    http_response_code(400);
}
```

> Endpoint webhook **harus** mengembalikan HTTP `2xx`. Jika tidak, server Interdotz akan menganggap pengiriman gagal dan melakukan retry.

### Struktur Payload Webhook

**Event `charge.success`:**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| `event` | `string` | `"charge.success"` |
| `timestamp` | `string` | Waktu kejadian (ISO 8601) |
| `data.transactionId` | `string` | ID transaksi di Interdotz |
| `data.userId` | `string` | ID user yang di-charge |
| `data.amount` | `int` | Jumlah DU yang dipotong |
| `data.referenceType` | `string` | Kategori transaksi |
| `data.referenceId` | `string` | ID transaksi dari sisi produk |
| `data.balanceBefore` | `int` | Saldo sebelum charge |
| `data.balanceAfter` | `int` | Saldo setelah charge |
| `data.errorMessage` | `null` | Selalu null jika success |

**Event `charge.failed`:**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| `data.transactionId` | `null` | Null karena transaksi tidak terjadi |
| `data.errorMessage` | `string` | Pesan error, contoh: `"Insufficient balance. Current: 30, required: 50"` |

**Methods yang tersedia di `WebhookPayload`:**

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `isSuccess()` | `bool` | `true` jika event adalah `charge.success` |
| `isFailed()` | `bool` | `true` jika event adalah `charge.failed` |

---

## Error Handling

Semua exception di SDK mewarisi dari `InterdotzException`, sehingga bisa di-catch secara spesifik atau sekaligus.

### Hierarki Exception

```
InterdotzException
├── AuthException              — masalah autentikasi
└── PaymentException           — masalah payment
    └── InsufficientBalanceException  — saldo DU tidak cukup
```

### Tabel Exception

| Exception | HTTP Code | Kapan Terjadi |
|-----------|-----------|---------------|
| `AuthException` | 401, 403, 404 | Kredensial salah, client tidak aktif, user tidak ditemukan |
| `InsufficientBalanceException` | 422 | Saldo DU user kurang dari jumlah yang di-charge |
| `PaymentException` | 400, 409, 500 | Validasi gagal, referenceId duplikat, server error |
| `InterdotzException` | — | Base exception, termasuk error webhook parsing |

### Contoh Penggunaan

```php
use Interdotz\Sdk\Exceptions\AuthException;
use Interdotz\Sdk\Exceptions\InsufficientBalanceException;
use Interdotz\Sdk\Exceptions\PaymentException;
use Interdotz\Sdk\Exceptions\InterdotzException;

try {
    $token  = $client->auth()->authenticate($user->interdotz_id);
    $charge = $client->payment()->directCharge(
        accessToken:   $token->accessToken,
        amount:        50,
        referenceType: 'PURCHASE',
        referenceId:   'order-001',
    );

} catch (InsufficientBalanceException $e) {
    // Saldo tidak cukup — arahkan user untuk topup
    redirect('/topup?required=' . $e->getContext()['required'] ?? 0);

} catch (AuthException $e) {
    // Log dan handle autentikasi gagal
    logger()->error('Interdotz auth failed', ['error' => $e->getMessage()]);

} catch (PaymentException $e) {
    if ($e->getCode() === 409) {
        // referenceId duplikat — order mungkin sudah diproses sebelumnya
    }

} catch (InterdotzException $e) {
    // Fallback untuk semua error SDK
    logger()->error('Interdotz SDK error', [
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
        'context' => $e->getContext(),
    ]);
}
```

### Method Exception

Setiap exception menyediakan tiga method:

```php
$e->getMessage();   // pesan error dari server Interdotz
$e->getCode();      // HTTP status code
$e->getContext();   // full response body sebagai array, berguna untuk debugging
```

---

## API Reference

### `InterdotzClient`

Entry point utama SDK.

```php
new InterdotzClient(
    clientId:     string,   // wajib
    clientSecret: string,   // wajib
    httpOptions:  array,    // opsional — diteruskan ke Guzzle
)
```

### `SsoClient` — `$client->sso()`

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `getLoginUrl()` | `redirectUrl: string` | `string` | Generate URL login SSO |
| `getRegisterUrl()` | `redirectUrl: string`, `state: ?string` | `string` | Generate URL register SSO |
| `handleCallback()` | `queryParams: array` | `SsoCallbackResponse` | Parse query params dari callback |

### `AuthClient` — `$client->auth()`

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `authenticate()` | `userId: string` | `TokenResponse` | Dapatkan access token untuk user |

### `PaymentClient` — `$client->payment()`

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `directCharge()` | `accessToken`, `amount`, `referenceType`, `referenceId` | `ChargeResponse` | Charge DU langsung |
| `createChargeRequest()` | `accessToken`, `userId`, `amount`, `referenceType`, `referenceId`, `callbackUrl`, `?description`, `?productLogo` | `ChargeRequestResponse` | Buat charge request dengan konfirmasi |
| `getBalance()` | `accessToken`, `userId` | `BalanceResponse` | Cek saldo DU user |

### `WebhookHandler` — `$client->webhook()`

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `parse()` | `rawBody: string` | `WebhookPayload` | Parse raw request body dari webhook |

---

## Changelog

### v0.1.0
- Initial release
- SSO: `getLoginUrl()`, `getRegisterUrl()`, `handleCallback()`
- Auth: `authenticate()`
- Payment: `directCharge()`, `createChargeRequest()`, `getBalance()`
- Webhook: `parse()`
