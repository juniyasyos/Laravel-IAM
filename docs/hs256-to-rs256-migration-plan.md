# HS256 ke RS256 Migration Plan

## Tujuan
Mengganti JWT signing dari `HS256` ke `RS256` agar IAM server memegang private key dan client hanya perlu public key untuk verifikasi.

## Alasan perubahan
- `HS256` memakai shared secret yang sama untuk signing dan verification.
- Jika satu client bocor, seluruh ekosistem ikut berisiko.
- `RS256` memisahkan private key dan public key, sehingga client tidak perlu tahu secret signing.

## Cakupan saat ini
Komponen yang perlu diperiksa dan kemungkinan diubah:
- [app/Domain/Iam/Services/TokenBuilder.php](../app/Domain/Iam/Services/TokenBuilder.php)
- [app/Services/JWTTokenService.php](../app/Services/JWTTokenService.php)
- [app/Services/Sso/TokenService.php](../app/Services/Sso/TokenService.php)
- [config/iam.php](../config/iam.php)
- semua client yang memverifikasi token JWT IAM

## Kondisi saat ini
- `TokenBuilder` masih hardcode `HS256`.
- `JWTTokenService` masih hardcode `HS256`.
- `TokenService` membuat JWT manual dengan `hash_hmac('sha256', ...)`.
- `config/iam.php` sudah punya opsi `IAM_JWT_ALGORITHM`, tetapi implementasi belum sepenuhnya menggunakannya.

## Rencana migrasi
1. Tambahkan konfigurasi key RS256 di IAM.
   - `IAM_JWT_PRIVATE_KEY`
   - `IAM_JWT_PUBLIC_KEY`
   - `IAM_JWT_ALGORITHM=RS256`
2. Ubah issuer/token builder agar membaca algoritma dari config, bukan hardcode `HS256`.
3. Ganti proses signing di IAM agar memakai private key RSA.
4. Ganti proses verification di client agar memakai public key RSA.
5. Hapus fallback ke `APP_KEY` untuk JWT signing/verifying.
6. Pastikan token lama masih ditangani selama masa transisi jika diperlukan.

## Area kode yang kemungkinan berubah
- `config/iam.php`: tambah key config dan default algoritma.
- `app/Domain/Iam/Services/TokenBuilder.php`: gunakan algoritma dari config.
- `app/Services/JWTTokenService.php`: gunakan algoritma dan key yang sesuai.
- `app/Services/Sso/TokenService.php`: ubah dari HMAC manual ke RS256-compatible signing.
- client apps: ubah verifier agar memakai public key.

## Risiko
- Token lama akan gagal diverifikasi jika cutover dilakukan tanpa masa transisi.
- Client yang masih memakai HS256 akan langsung mismatch.
- Key RSA yang salah format akan menyebabkan signature verification gagal.

## Verifikasi
- Issued token dari IAM dapat diverifikasi oleh client dengan public key.
- Token dengan algoritma lama ditolak setelah cutover.
- Refresh, logout, dan backchannel flow tetap jalan.

## Output yang diharapkan
- IAM menandatangani token dengan RSA private key.
- Client memverifikasi token dengan RSA public key.
- Tidak ada lagi ketergantungan JWT SSO pada shared secret tunggal.