# SSO — Konsistensi Session antar Aplikasi

Tujuan: menjelaskan pendekatan yang direkomendasikan untuk memastikan pengguna yang login lewat SSO **sama** di seluruh aplikasi (tidak muncul dua session berbeda seperti `yogi` di IAM tetapi tetap `admin` di client).

Ringkasan singkat ✅
- Rekomendasi utama: **SSO as source of truth** — client selalu verifikasi token SSO dan *replace* sesi lokal pada callback. Mudah diimplementasikan dan paling sedikit risiko.
- Opsi opsional (lebih invasif): **Shared session store** (Redis / DB) dengan `APP_KEY` yang sama — menjadikan "satu sesi nyata" untuk semua app.
- Untuk global logout: gunakan **front‑channel** (redirect ke `/iam/logout` di client) atau **back‑channel** (server→server notify).

---

## 1. Rekomendasi (paling praktis): Replace sesi di SSO callback 🔁
Inti: setelah client menerima token dari IAM, verifikasi token → jika session lokal ada dan NIP berbeda → logout session lokal dan login user yang sesuai dari token.

Contoh langkah (Laravel client):

1. Terima callback dari IAM: `GET /auth/callback?token={sso_token}`
2. Verifikasi token ke IAM (`POST /api/sso/verify`) dan ambil `nip` + `user` payload
3. Jika ada user yang sedang login dan `auth()->user()->nip !== $sso->nip` → `auth()->logout()` + `session()->invalidate()`
4. Buat atau ambil user pada DB berdasarkan `nip` → `Auth::loginUsingId($user->id)` → `session()->regenerate()`

Contoh implementasi singkat (callback controller):

```php
// routes/web.php
Route::get('/auth/callback', [SSOController::class, 'callback']);

// app/Http/Controllers/SSOController.php
public function callback(Request $request)
{
    $token = $request->query('token');

    $resp = Http::post(config('services.iam.url').'/api/sso/verify', [
        'token' => $token,
        'include_user_data' => true,
    ]);

    if (! $resp->successful() || empty($resp['nip'])) {
        return redirect('/login')->withErrors('Invalid SSO token');
    }

    $sso = $resp->json();

    // Jika ada sesi lama dengan user berbeda, hapus
    if (auth()->check() && auth()->user()->nip !== $sso['nip']) {
        auth()->logout();
        session()->invalidate();
    }

    // Ambil atau buat user lokal berdasarkan NIP
    $user = \App\Models\User::firstOrCreate([
        'nip' => $sso['nip']
    ], [
        'name' => $sso['name'] ?? $sso['nip'],
        'email' => $sso['email'] ?? null,
        'password' => bcrypt(Str::random(40)), // placeholder
    ]);

    Auth::loginUsingId($user->id);
    session()->regenerate();

    // Simpan token/session spesifik IAM bila perlu
    session()->put('iam_access_token', $token);
    session()->put('iam_user', $sso['user'] ?? null);

    return redirect()->intended('/');
}
```

Kenapa ini baik:
- Langkah ini menyelesaikan kasus: user login di IAM sebagai `yogi` (NIP `0315.01166`) → client otomatis menggantikan session `admin` lama.
- Tidak perlu menyamakan APP_KEY / konfigurasi cookie domain di semua aplikasi.

---

## 2. Opsional: Shared session ("satu session untuk semua app") 🧩
Syarat keras:
- Semua aplikasi menggunakan **SAME APP_KEY**
- Shared session driver (database — Redis optional) yang bisa diakses oleh semua app
- SESSION_COOKIE name + SESSION_DOMAIN identik di setiap app
- User provider & model kompatibel (agar sesi dapat unserialize)

Contoh `.env` (semua app pakai sama — gunakan `database` jika Redis ditunda):

```
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXX
SESSION_DRIVER=database
SESSION_CONNECTION=mysql
SESSION_COOKIE=iam_session
SESSION_DOMAIN=.local.test
SESSION_SAME_SITE=lax
SESSION_SECURE=false
```

Contoh potongan `docker-compose.yml` (development) — database‑backed shared session:

```yaml
services:
  db:
    image: mysql:8
    environment:
      MYSQL_DATABASE: iam_shared
      MYSQL_ROOT_PASSWORD: secret
    ports: ['3306:3306']

  iam:
    build: .
    environment:
      - APP_KEY=base64:XXXXXXXXXXXXXXXX
      - SESSION_DRIVER=database
      - SESSION_CONNECTION=mysql
      - SESSION_DOMAIN=.local.test
    depends_on: [db]

  client-app:
    build: ../client-app
    environment:
      - APP_KEY=base64:XXXXXXXXXXXXXXXX
      - SESSION_DRIVER=database
      - SESSION_CONNECTION=mysql
      - SESSION_DOMAIN=.local.test
    depends_on: [db]
```

Langkah singkat untuk DB-backed shared session:

1. Pastikan `APP_KEY`, `SESSION_COOKIE`, dan `SESSION_DOMAIN` identik di semua app.
2. Set `SESSION_DRIVER=database` dan `SESSION_CONNECTION` ke database yang dapat diakses bersama.
3. Jalankan `php artisan session:table` lalu `php artisan migrate` pada database yang sama.
4. Pastikan user provider & model kompatibel agar sesi dapat di-unserialize.
5. Uji: login via SSO → cek cookie `iam_session` dan session record pada tabel `sessions`.

Catatan: penggunaan Redis ditunda untuk sekarang jika Anda khawatir tentang blast radius / keamanan; DB-backed session adalah alternatif yang lebih mudah untuk dikontrol.

Catatan penting:
- Shared session bekerja pada level cookie domain (port tidak penting). Gunakan hostnames (contoh `iam.local.test`, `app.local.test`) untuk stabilitas.
- Risiko: jika salah config -> semua app berisiko diambil alih bila APP_KEY bocor.

---

## 3. Global logout (sinkronisasi logout antar app) 🔐
Pilihan implementasi:
- Front‑channel: IAM redirect ke `https://app.example.com/iam/logout` untuk menghapus session client.
- Back‑channel: IAM POST ke registered `backchannel_logout` endpoint pada client.

Di repo: lihat `docs/SSO-GLOBAL-LOGOUT-PLANNING.md` untuk alur dan contoh.

Contoh route client untuk menerima front‑channel logout:

```php
Route::get('/iam/logout', function () {
    session()->forget(['iam_access_token','iam_user','iam_refresh_token']);
    auth()->logout();
    session()->invalidate();
    return redirect('/login');
});
```

---

## 4. Cara pengujian cepat (verifikasi masalah Anda)
1. Reproduce masalah: login IAM sebagai `0315.01166` lalu redirect ke app client — lihat apakah client masih menampilkan `admin`.
2. Pasang kode callback (section 1) di client.
3. Login ulang via SSO dan periksa `auth()->user()->nip` di client.
4. Di browser devtools → Application → Cookies: pastikan cookie `iam_session`/session cookie berubah saat callback.

Perintah berguna:
- `php artisan migrate:fresh --seed`
- `php artisan config:clear && php artisan cache:clear`

---

## 5. Checklist migrasi & tindakan
- [ ] Terapkan callback logic yang *replace session* di semua client
- [ ] Tambahkan test integrasi (end‑to‑end) untuk SSO callback
- [ ] Implementasikan front‑channel logout di IAM jika butuh global logout
- [ ] (Opsional) Jika ingin shared‑session: set APP_KEY konsisten, pindahkan session ke Redis / DB, uji deserialization
- [ ] Update dokumentasi integrasi client (`docs/SSO-CLIENT-NIP-INTEGRATION.md`)

---

## 6. Risiko & catatan keamanan ⚠️
- Menyamakan `APP_KEY` → tingkat akses tinggi jika bocor
- Shared session membuat blast radius lebih besar
- Pastikan `SESSION_SAME_SITE` dan `SESSION_SECURE` sesuai kebutuhan (untuk cross‑site SSO, `same_site=none` + `secure=true` pada HTTPS)

---

## Referensi di repo
- `config/session.php`
- `config/sso.php`
- `docs/SSO-GLOBAL-LOGOUT-PLANNING.md`
- `docs/SSO-CLIENT-NIP-INTEGRATION.md`

---

Jika Anda mau, saya bisa:
- membuatkan `PR` contoh kode callback di paket client, atau
- tambahkan file `docker-compose` contoh untuk shared session.

Pilih salah satu tindakan lanjutan: _contoh kode callback_, _docker‑compose shared session_, atau _implementasi front‑channel logout_.
