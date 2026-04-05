# User Sync Debugging Guide - Slug Comparison Logs

## Overview
Logging telah ditambahkan untuk melacak slug comparison selama proses user sync. Dokumentasi ini menjelaskan setiap log entry dan cara menggunakannya untuk debug.

---

## Log Flow Sequence

### 1. **client_users_fetched** ✅
**Waktu:** Ketika data user berhasil diambil dari client application
**Lokasi:** `ApplicationUserSyncService::fetchClientUsers()`

```json
{
  "app_key": "my-app",
  "application_id": 1,
  "total_users": 2,
  "users": [
    {
      "nip": "123456",
      "email": "john@example.com",
      "name": "John Doe",
      "active": true,
      "role_slugs": ["admin", "manager"]
    },
    {
      "nip": "789012",
      "email": "jane@example.com",
      "name": "Jane Smith",
      "active": true,
      "role_slugs": ["user"]
    }
  ]
}
```

**Untuk debug:**
- Pastikan `total_users` > 0 (jika tidak, client tidak mengirim user)
- Pastikan `role_slugs` ada dan bukan array kosong
- Bandingkan `role_slugs` dengan application roles yang ada di IAM

---

### 2. **user_sync_role_slugs_from_client** ℹ️
**Waktu:** Untuk setiap user yang diproses
**Lokasi:** `ApplicationUserSyncService::syncUsers()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "client_role_slugs": ["admin", "manager"],
  "sync_mode": "auto",
  "allowed_profile_ids": "none"
}
```

**Untuk debug:**
- `client_role_slugs` harus match dengan yang di `client_users_fetched`
- `allowed_profile_ids` = "none" = tanpa restriction, `allowed_profile_ids` = array = dengan restriction dari Filament modal
- Jika sync_mode = "manual", slugs akan diambil dari `manualRoleMapping`

---

### 3. **user_sync_slug_validation** ✓ Paling Penting!
**Waktu:** Validasi slug terhadap database
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "requested_slugs": ["admin", "manager"],
  "found_slugs": ["admin", "manager"],
  "missing_slugs": [],
  "total_requested": 2,
  "total_found": 2,
  "validation_passed": true
}
```

**❌ Jika validation_passed = false:**
```json
{
  "requested_slugs": ["admin", "manager"],
  "found_slugs": ["admin"],
  "missing_slugs": ["manager"],
  "validation_passed": false
}
```

**Untuk debug:**
- **missing_slugs bukan kosong?** → Role dengan slug ini TIDAK ada di `iam_roles` table
- **Solusi:** 
  - Buat role dengan slug "manager" di ApplicationRole
  - Atau ubah slug di client menjadi sesuai yang ada di IAM

---

### 4. **user_sync_profile_matching** 🎯 Slug Cocokkan Profile
**Waktu:** Matching role slugs dengan access profiles
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "requested_role_slugs": ["admin", "manager"],
  "found_profiles": [
    {
      "profile_id": 10,
      "profile_slug": "admin-profile",
      "profile_name": "Administrator Profile",
      "role_slugs": ["admin", "superadmin"]
    },
    {
      "profile_id": 11,
      "profile_slug": "manager-profile",
      "profile_name": "Manager Profile",
      "role_slugs": ["manager"]
    }
  ],
  "covered_role_slugs": ["admin", "superadmin", "manager"],
  "missing_role_slugs": [],
  "profile_count_found": 2,
  "allowed_profile_ids": "none"
}
```

**❌ Jika ada missing_role_slugs:**
```json
{
  "requested_role_slugs": ["admin", "manager", "viewer"],
  "covered_role_slugs": ["admin", "manager"],
  "missing_role_slugs": ["viewer"],
  "note": "These role slugs are not covered by any access profile..."
}
```

**Untuk debug:**
- **covered_role_slugs vs requested_role_slugs:** Mana yang tidak match?
- **found_profiles:** Lihat role_slugs setiap profile
- **missing_role_slugs:** Role ini tidak di-cover oleh profile manapun
- **Solusi:**
  - Buat AccessProfile baru
  - Atau link role ke existing profile

---

### 5. **user_sync_missing_role_coverage** ⚠️ Warning
**Waktu:** Jika ada role slugs yang tidak di-cover oleh profile
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "missing_slugs": ["viewer"],
  "note": "These role slugs are not covered by any access profile. Create access profiles or link them to existing profiles."
}
```

**Untuk debug:**
- Ada log ini = **user tidak akan di-assign ke profile untuk role ini**
- Harus buat AccessProfile atau link role ke existing profile

---

### 6. **user_sync_auto_created_profile** 📦 Auto-Created
**Waktu:** Jika profile auto-created untuk missing role
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "role_slug": "viewer",
  "profile_id": 12,
  "profile_slug": "viewer",
  "profile_name": "Viewer"
}
```

**Untuk debug:**
- Profile auto-created saat sync (hanya jika allowed_profile_ids kosong)
- Lebih baik create profile dulu sebelum sync untuk kontrol yang lebih baik

---

### 7. **user_sync_profile_attachment** 📌 Attachment Plan
**Waktu:** Sebelum attach profiles ke user
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "all_profiles_to_attach": [10, 11, 12],
  "current_profile_ids": [10],
  "new_profiles_to_attach": [11, 12],
  "attachment_count": 2
}
```

**Untuk debug:**
- **current_profile_ids:** Profile yang user sudah punya
- **new_profiles_to_attach:** Profile yang akan di-add
- **attachment_count:** Berapa profile yang akan di-attach
- Jika = 0, user sudah punya semua profile yang diperlukan

---

### 8. **user_sync_profile_attached_success** ✅ Sukses!
**Waktu:** Setelah berhasil attach profiles
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "attached_profile_ids": [11, 12],
  "attached_count": 2
}
```

**Untuk debug:**
- Log ini = sync berhasil untuk user ini
- Profiles sudah di-attach ke user via pivot table

---

### 9. **user_sync_no_new_profiles** ℹ️
**Waktu:** Jika user sudah punya semua profiles yang diperlukan
**Lokasi:** `UserRoleAssignmentService::syncProfilesForUserAndApp()`

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "message": "User already has all required profiles"
}
```

**Untuk debug:**
- Tidak ada error, user sudah complete

---

### 10. **user_role_sync_failed** ❌ Error!
**Waktu:** Jika ada exception saat sync
**Lokasi:** `ApplicationUserSyncService::syncUsers()` (catch block)

```json
{
  "application_id": 1,
  "app_key": "my-app",
  "user_id": 42,
  "user_email": "john@example.com",
  "user_nip": "123456",
  "error": "Invalid role slugs: manager",
  "client_roles": ["admin", "manager"],
  "sync_mode": "auto",
  "allowed_profile_ids": "none"
}
```

**Untuk debug:**
- Error message menjelaskan masalahnya
- Paling umum: Invalid role slugs = role tidak ada di database

---

## Cara Membaca Logs

### Via Log File
```bash
# Lihat real-time logs
tail -f storage/logs/laravel.log

# Search untuk logs user sync
grep "user_sync\|client_users_fetched" storage/logs/laravel.log

# Lihat untuk user specific
grep "john@example.com" storage/logs/laravel.log
```

### Via Database (jika menggunakan database logging)
```bash
php artisan tinker

# Lihat logs terbaru
>>> Log::all(); // atau query dari tabel logs

# Filter specific user
>>> Log::where('message', 'like', '%john@example.com%')->get();
```

### Via Laravel Log Viewer (jika installed)
```bash
# Install log viewer
composer require opcodesio/log-viewer

php artisan vendor:publish --provider="Opcodesio\LogViewer\LogViewerServiceProvider"

# Access via /log-viewer
```

---

## Debug Workflow

### Problem: User tidak di-assign ke profile

**Step 1: Cek client_users_fetched**
- Apakah user ada di log?
- Apakah role_slugs ada dan benar?

**Step 2: Cek user_sync_slug_validation**
- validation_passed = true atau false?
- Jika false, lihat missing_slugs
- **Solusi:** Buat role dengan slug yang missing

**Step 3: Cek user_sync_profile_matching**
- Apakah profiles ditemukan?
- covered_role_slugs vs requested_role_slugs cocok?
- Jika tidak cocok, missing_slugs apa?
- **Solusi:** Buat/link AccessProfile untuk missing slugs

**Step 4: Cek user_sync_profile_attachment**
- attachment_count > 0?
- Jika 0, user mungkin sudah punya profile

**Step 5: Verify di database**
```sql
-- Cek user.access_profiles
SELECT * FROM user_access_profiles WHERE user_id = 42;

-- Cek role di access_profile
SELECT * FROM access_profile_iam_role 
WHERE access_profile_id IN (
  SELECT access_profile_id FROM user_access_profiles WHERE user_id = 42
);
```

---

## Summary

| Log | Purpose | Fix When |
|-----|---------|----------|
| `client_users_fetched` | Users dari client | total_users = 0 → client tidak kirim user |
| `user_sync_role_slugs_from_client` | Slugs untuk user | Cocok dengan client_users_fetched |
| `user_sync_slug_validation` | Slug ada di iam_roles? | missing_slugs bukan kosong → buat role |
| `user_sync_profile_matching` | Profile cover slugs? | missing_role_slugs → buat/link profile |
| `user_sync_profile_attachment` | Mau attach profile | attachment_count = 0 → sudah lengkap |
| `user_sync_profile_attached_success` | ✅ BERHASIL | attached_count > 0 → sukses |
| `user_role_sync_failed` | ❌ ERROR | Lihat error message |

---

## Example: Complete Success Flow

```
[2024-01-15 10:30:15] client_users_fetched
  └─ total_users: 1, nip: "123456", role_slugs: ["admin", "manager"]

[2024-01-15 10:30:15] user_sync_role_slugs_from_client
  └─ user_id: 42, client_role_slugs: ["admin", "manager"]

[2024-01-15 10:30:15] user_sync_slug_validation
  └─ requested_slugs: ["admin", "manager"]
  └─ found_slugs: ["admin", "manager"]
  └─ missing_slugs: []
  └─ validation_passed: true ✅

[2024-01-15 10:30:15] user_sync_profile_matching
  └─ requested_role_slugs: ["admin", "manager"]
  └─ found_profiles: 2
  └─ covered_role_slugs: ["admin", "manager"]
  └─ missing_role_slugs: [] ✅

[2024-01-15 10:30:15] user_sync_profile_attachment
  └─ new_profiles_to_attach: [10, 11]
  └─ attachment_count: 2

[2024-01-15 10:30:15] user_sync_profile_attached_success ✅
  └─ attached_profile_ids: [10, 11]
  └─ attached_count: 2
```

---

## Example: Failed Flow (Missing Slugs)

```
[2024-01-15 10:30:15] client_users_fetched
  └─ total_users: 1, role_slugs: ["admin", "viewer"]

[2024-01-15 10:30:15] user_sync_role_slugs_from_client
  └─ client_role_slugs: ["admin", "viewer"]

[2024-01-15 10:30:15] user_sync_slug_validation
  └─ requested_slugs: ["admin", "viewer"]
  └─ found_slugs: ["admin"]
  └─ missing_slugs: ["viewer"] ❌
  └─ validation_passed: false

[2024-01-15 10:30:15] user_role_sync_failed ❌
  └─ error: "Invalid role slugs: viewer"
  └─ Client roles: ["admin", "viewer"]
  
⚠️ → Solusi: Buat ApplicationRole dengan slug "viewer"
```
