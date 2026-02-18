# SSO Global Logout Planning & Discussion

## Tanggal Diskusi
16 Februari 2026

## Masalah yang Ditemukan
Ketika user logout dari aplikasi IAM, session di aplikasi client (seperti siimut) tidak ikut terhapus. User masih terlihat login sebagai admin di aplikasi client meskipun sudah logout dari IAM.

## Analisis Arsitektur Saat Ini
- **IAM App**: Menggunakan session-based authentication (Laravel guard 'web').
- **Client Apps**: Hybrid token + session - mendapat access token dari IAM via SSO, menyimpan di session lokal.
- **Logout IAM**: Hanya invalidate session server, tidak revoke token atau notify clients.
- **Logout Client**: Hanya clear session lokal, token IAM masih valid.

## Opsi Solusi yang Didiskusikan

### Opsi 1: Token Revocation di Logout IAM
- Modifikasi logout IAM untuk revoke semua tokens user.
- Pro: Simple, token langsung invalid.
- Kontra: Terlalu agresif - logout dari semua devices, bukan hanya current session.

### Opsi 2: Front-Channel Logout (PILIHAN)
- Implementasi OP-Initiated Logout di IAM.
- Ketika logout dari IAM, redirect/call endpoint logout di registered clients.
- Pro: Standard OAuth2/OpenID Connect, user-friendly global logout.
- Kontra: Perlu perubahan di client.

### Opsi 3: Token Introspection di Client
- Client selalu check token validity sebelum request.
- Jika token invalid, force logout.
- Pro: Minimal changes.
- Kontra: Overhead pada setiap request, tidak real-time.

### Opsi 4: Session Sharing via Database/Redis
- Semua apps share session di central storage.
- Logout di satu app invalidate global.
- Pro: Seamless.
- Kontra: Complex infrastructure, performance impact.

## Implementasi yang Dipilih: Opsi 2 (Front-Channel Logout)

### Perubahan di IAM Server
1. **(Default) Derive client logout endpoint**: IAM akan *menurunkan* `logout_uri` dari data aplikasi yang sudah ada (ambil `redirect_uris[0]` atau `callback_url` lalu tambahkan `/iam/logout`). Jika client memakai package `juniyasyos/iam-client`, tidak perlu menambahkan `logout_uri` manual. 
2. **Model Application**: Tambah accessor `logout_uri` yang mengembalikan derived `/iam/logout` untuk aplikasi yang memiliki `redirect_uris`/`callback_url`.
3. **AuthenticatedSessionController::destroy()**: 
   - Invalidate session seperti biasa.
   - Mulai front‑channel logout chain — sequential redirect ke tiap client `/iam/logout` (menggunakan `post_logout_redirect` untuk melanjutkan chain).
4. **(Optional)** Jika organisasi ingin menyimpan `logout_uri` tersendiri, migration/kolom baru tetap dapat ditambahkan — tapi bukan lagi persyaratan bila client menggunakan paket.

### Perubahan di Client Apps
1. Jika aplikasi client menggunakan package `juniyasyos/iam-client` (lihat folder `laravel-iam-client`), plugin sudah menyediakan `GET /iam/logout` (public) yang bertugas membersihkan session IAM (`iam_access_token`, `iam_refresh_token`, `iam_expires_at`, `iam_user`) dan melakukan redirect ke halaman login. Pastikan package routes ter-load di aplikasi client dan `logout_uri` client didaftarkan di IAM.
2. Jika aplikasi tidak memakai package, tambahkan endpoint publik `/iam/logout` seperti pada fallback di bawah.
3. Contoh Implementasi (Laravel, fallback manual):
   ```php
   Route::get('/iam/logout', [AuthController::class, 'iamLogout']);
   
   public function iamLogout() {
       session()->forget(['iam_access_token', 'iam_refresh_token', 'iam_expires_at', 'iam_user']);
       return redirect('/login');
   }
   ```

### Alur Kerja
1. User logout di IAM.
2. IAM invalidate session user.
3. IAM redirect ke logout endpoints clients.
4. Clients clear session dan redirect user ke login.
5. Global logout tercapai.

## Next Steps
1. Implementasi migration dan model di IAM.
2. Update logout controller di IAM.
3. Update dokumentasi client integration.
4. Test dengan aplikasi client.
5. Deploy dan monitor.

## Catatan Tambahan
- Jika client tidak ingin diubah, pertimbangkan back-channel logout (HTTP calls asynchronous).
- Pastikan security: logout_uri harus validated (same domain, HTTPS).
- Consider rate limiting untuk logout calls.</content>
<parameter name="filePath">/home/juni/projects/IAM/laravel-iam/docs/SSO-GLOBAL-LOGOUT-PLANNING.md