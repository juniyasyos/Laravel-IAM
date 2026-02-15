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
1. **Database Migration**: Tambah field `logout_uri` di tabel `applications`.
2. **Model Application**: Tambah accessor untuk `logout_uri`.
3. **AuthenticatedSessionController::destroy()**: 
   - Invalidate session seperti biasa.
   - Loop melalui registered applications.
   - Redirect ke `logout_uri` masing-masing client (menggunakan iframe atau sequential).
4. **Application Registration**: Pastikan client mendaftarkan `logout_uri` saat register.

### Perubahan di Client Apps
1. **Tambah Endpoint**: `/iam/logout` (public, tidak perlu auth).
2. **Fungsi Endpoint**:
   - Clear session lokal (`iam_access_token`, `iam_user`, dll).
   - Optional: Revoke token di IAM.
   - Redirect ke login page client.
3. **Contoh Implementasi** (Laravel):
   ```php
   Route::get('/iam/logout', [AuthController::class, 'iamLogout']);
   
   public function iamLogout() {
       session()->forget(['iam_access_token', 'iam_refresh_token', 'iam_user']);
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