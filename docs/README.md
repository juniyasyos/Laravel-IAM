# Documentation Index

## 📚 Dokumentasi IAM Server - NIP-Based Authentication

Selamat datang di dokumentasi IAM (Identity and Access Management) Server. Sistem telah dioptimalkan untuk menggunakan **NIP (Nomor Induk Pegawai)** sebagai identifier utama.

---

## 🆕 NIP-Based Authentication (Version 2.0)

### Quick Start & Integration
1. **[SSO Quick Start - NIP](./SSO-NIP-QUICK-START.md)** ⚡
   - Setup dalam 5 menit
   - Login form examples
   - Token structure
   - API endpoints
   - Common issues & solutions

2. **[SSO Client Integration - NIP](./SSO-CLIENT-NIP-INTEGRATION.md)** 📖
   - Panduan lengkap integrasi
   - Authentication flow diagram
   - OAuth2-like flow
   - Client implementations (Laravel, React, JavaScript)
   - Migration guide
   - Security best practices
   - Troubleshooting

3. **[NIP Migration Summary](./NIP-MIGRATION-SUMMARY.md)** 🔄
   - Summary perubahan sistem
   - Files yang dimodifikasi
   - Token structure baru
   - API response format
   - Backward compatibility
   - Migration checklist

---

## 📖 Core Documentation

### System Architecture & RBAC
4. **[IAM + SSO RBAC Documentation](./IAM-SSO-RBAC-DOCUMENTATION.md)** 🏗️
   - Architecture overview
   - Database schema
   - JWT token structure
   - SSO flow
   - RBAC management
   - Security considerations

### API & Response Format
5. **[API Response Format](./API-RESPONSE-FORMAT.md)** 📊
   - Response structure
   - Error handling
   - Status codes
   - Example responses

### Client Integration
6. **[Client Integration Guide](./CLIENT-INTEGRATION.md)** 🔌
   - OAuth2 flow
   - Token exchange
   - API calls
   - Error handling

### Setup & Deployment
7. **[Setup Guide](./SETUP.md)** ⚙️
   - Installation steps
   - Configuration
   - Environment setup
   - Database migration
   - Production deployment

### Quick Reference
8. **[Quick Reference](./QUICK-REFERENCE.md)** 📝
   - Command cheat sheet
   - API endpoints
   - Common tasks
   - Troubleshooting

---

## 🎯 Documentation by Use Case

### Untuk Developer Baru
**Mulai dari sini:**
1. [Setup Guide](./SETUP.md) - Install & konfigurasi
2. [SSO Quick Start - NIP](./SSO-NIP-QUICK-START.md) - Test SSO flow
3. [IAM + SSO RBAC Documentation](./IAM-SSO-RBAC-DOCUMENTATION.md) - Pahami arsitektur

### Untuk Integrasi Client App
**Ikuti urutan ini:**
1. [SSO Quick Start - NIP](./SSO-NIP-QUICK-START.md) - Pahami flow
2. [SSO Client Integration - NIP](./SSO-CLIENT-NIP-INTEGRATION.md) - Implementation guide
3. [API Response Format](./API-RESPONSE-FORMAT.md) - Pahami response structure

### Untuk Migration dari Email ke NIP
**Langkah migration:**
1. [NIP Migration Summary](./NIP-MIGRATION-SUMMARY.md) - Pahami perubahan
2. [SSO Client Integration - NIP](./SSO-CLIENT-NIP-INTEGRATION.md) - Section "Migration Guide"
3. [SSO Quick Start - NIP](./SSO-NIP-QUICK-START.md) - Section "Migration dari Email ke NIP"

### Untuk Troubleshooting
**Resources:**
1. [SSO Quick Start - NIP](./SSO-NIP-QUICK-START.md) - Section "Common Issues"
2. [SSO Client Integration - NIP](./SSO-CLIENT-NIP-INTEGRATION.md) - Section "Troubleshooting"
3. [Quick Reference](./QUICK-REFERENCE.md) - Quick fixes

---

## 🔑 Key Concepts

### NIP vs Email
- **NIP**: Primary identifier, unique, required
- **Email**: Optional, nullable, untuk compatibility
- **Migration**: Dual support - both available di token

### Token Structure
```json
{
  "iss": "https://iam.example.com",
  "sub": "123",
  "nip": "198501012010121001",  // PRIMARY
  "email": "user@example.com",  // OPTIONAL
  "name": "Ahmad Ilyas",
  "app": "portal-mahasiswa",
  "roles": ["mahasiswa", "admin"],
  "iat": 1700294400,
  "exp": 1700298000
}
```

### Authentication Flow
```
Client App → IAM Login (NIP) → Token → Verify → User Data
```

---

## 📦 API Endpoints Summary

### SSO Endpoints
- `POST /api/sso/verify` - Verify token & get user data
- `POST /api/sso/introspect` - Token introspection
- `GET /api/sso/userinfo` - Get user info from token
- `POST /api/sso/token/issue` - Issue new token
- `POST /api/sso/token` - Exchange authorization code
- `POST /api/sso/token/refresh` - Refresh token

### OAuth2-like Endpoints
- `GET /oauth/authorize` - Authorization endpoint
- `POST /oauth/token` - Token exchange
- `POST /oauth/introspect` - Token validation
- `GET /oauth/userinfo` - User information
- `POST /oauth/revoke` - Token revocation

---

## 🔒 Security Best Practices

### Token Management
- Always use HTTPS
- Verify tokens server-side
- Check expiration
- Store securely (HTTP-only cookies, secure storage)

### NIP Security
- Treat as PII (Personal Identifiable Information)
- Don't expose in logs unnecessarily
- Validate format on input
- Use rate limiting on login

### Client App Security
- Register callback URLs
- Validate state parameter
- Implement CSRF protection
- Use secure token storage

---

## 🆘 Getting Help

### Quick Links
- **Quick Start**: [SSO-NIP-QUICK-START.md](./SSO-NIP-QUICK-START.md)
- **Full Guide**: [SSO-CLIENT-NIP-INTEGRATION.md](./SSO-CLIENT-NIP-INTEGRATION.md)
- **API Reference**: [API-RESPONSE-FORMAT.md](./API-RESPONSE-FORMAT.md)
- **Troubleshooting**: Search "troubleshooting" in docs

### Common Questions

**Q: Bagaimana cara login dengan NIP?**
A: Gunakan field `nip` dan `password` di login form. Lihat [SSO Quick Start](./SSO-NIP-QUICK-START.md#login-credentials).

**Q: Email masih diperlukan?**
A: Email bersifat optional (nullable). NIP adalah primary identifier.

**Q: Bagaimana migrate dari email ke NIP?**
A: Lihat [Migration Guide](./SSO-CLIENT-NIP-INTEGRATION.md#migration-guide) dan [NIP Migration Summary](./NIP-MIGRATION-SUMMARY.md).

**Q: Token structure apa yang digunakan?**
A: JWT dengan NIP sebagai primary identifier. Lihat [Token Structure](./SSO-NIP-QUICK-START.md#token-structure).

**Q: Bagaimana test SSO flow?**
A: Ikuti [Quick Start Guide](./SSO-NIP-QUICK-START.md#quick-integration-5-menit).

---

## 📝 Document Status

| Document | Status | Last Updated | Version |
|----------|--------|--------------|---------|
| SSO-NIP-QUICK-START.md | ✅ Current | Nov 18, 2024 | 2.0 |
| SSO-CLIENT-NIP-INTEGRATION.md | ✅ Current | Nov 18, 2024 | 2.0 |
| NIP-MIGRATION-SUMMARY.md | ✅ Current | Nov 18, 2024 | 2.0 |
| IAM-SSO-RBAC-DOCUMENTATION.md | ✅ Valid | - | 1.x |
| API-RESPONSE-FORMAT.md | ✅ Valid | - | 1.x |
| CLIENT-INTEGRATION.md | ⚠️ Legacy | - | 1.x |
| SETUP.md | ✅ Valid | - | 1.x |
| QUICK-REFERENCE.md | ✅ Valid | - | 1.x |

**Legend:**
- ✅ Current: Fully updated untuk NIP-based auth
- ✅ Valid: Masih relevan, minor updates needed
- ⚠️ Legacy: Perlu update untuk NIP support

---

## 🔄 Changelog

### Version 2.0 (November 18, 2024)
- ✅ NIP-based authentication implementation
- ✅ Email menjadi nullable/optional
- ✅ Updated token structure
- ✅ New documentation (3 files)
- ✅ Backward compatibility maintained

### Version 1.0 (Previous)
- Email-based authentication
- OAuth2-like flow
- RBAC with Spatie Permission
- Filament admin panel

---

## 📞 Support

Untuk pertanyaan atau bantuan:
- Check documentation di folder `/docs`
- Review example implementations di dokumentasi
- Check API response examples

---

**Documentation Version**: 2.0  
**Last Updated**: November 18, 2024  
**IAM Server Version**: 2.0.0
