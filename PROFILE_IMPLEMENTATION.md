# ✅ Profile Management - IMPLEMENTATION COMPLETED

## 🎯 Yang Sudah Diimplementasi

### 1. ProfileController
File: [app/Http/Controllers/ProfileController.php](app/Http/Controllers/ProfileController.php)

**3 Methods:**
1. ✅ `show()` - Get current user profile
2. ✅ `update()` - Update profile (nama, avatar, kelas, jurusan)
3. ✅ `changePassword()` - Change password with security

**Key Features:**
- Avatar upload dengan validation (max 2MB, jpeg/jpg/png)
- Auto-delete avatar lama saat upload baru
- Password change dengan current password verification
- Token revocation setelah change password (security)
- Role-specific updates (siswa bisa update kelas/jurusan)
- Indonesian error messages

---

### 2. Routes
File: [routes/api.php](routes/api.php)

**3 New Routes:**
```php
GET  /api/profile                  # Get profile
PUT  /api/profile                  # Update profile
PUT  /api/profile/password         # Change password
```

**Total API Routes: 69** (dari 66 sebelumnya)

---

### 3. User Model Enhancement
File: [app/Models/User.php](app/Models/User.php)

**Tambahan:**
- ✅ Accessor `getNamaAttribute()` - Mapping `name` → `nama`
- ✅ Mutator `setNamaAttribute()` - Mapping `nama` → `name`

Ini memastikan konsistensi API response menggunakan `nama` meskipun database pakai `name`.

---

### 4. Database Schema
Table: `users`

**Kolom yang digunakan:**
```sql
id               # User ID
name             # Nama user (accessible as 'nama')
email            # Email
password         # Hashed password
role             # admin, guru, siswa
nis              # For siswa
nip              # For guru
kelas            # X, XI, XII (siswa)
jurusan_id       # FK to jurusans (siswa)
avatar           # Avatar file path
created_at       # Timestamp
updated_at       # Timestamp
```

**Avatar Storage:**
- Path: `storage/app/public/avatars/`
- Public URL: `http://localhost:8000/storage/avatars/{filename}`
- Auto cleanup on delete

---

## 📋 API Endpoints

### 1. GET /profile
**Auth:** Required  
**Role:** All (admin, guru, siswa)

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "siswa@example.com",
    "nama": "Ahmad Siswa",
    "role": "siswa",
    "avatar": "http://localhost:8000/storage/avatars/abc.jpg",
    "kelas": "XII",
    "jurusan_id": "JUR-1",
    "nis": "12345",
    "nip": null,
    "created_at": "2026-01-13T10:00:00.000000Z"
  }
}
```

---

### 2. PUT /profile
**Auth:** Required  
**Content-Type:** multipart/form-data

**Request:**
```
nama: "Updated Name"
avatar: [image file]              # Optional, max 2MB
kelas: "XII"                      # Optional, siswa only
jurusan_id: "JUR-1"               # Optional, siswa only
```

**Response:**
```json
{
  "success": true,
  "message": "Profil berhasil diupdate",
  "data": {
    "id": 1,
    "email": "siswa@example.com",
    "nama": "Updated Name",
    "role": "siswa",
    "avatar": "http://localhost:8000/storage/avatars/new.jpg",
    "kelas": "XII",
    "jurusan_id": "JUR-1",
    "nis": "12345",
    "nip": null
  }
}
```

**Validasi:**
- `nama`: optional, string, max 255 chars
- `avatar`: optional, image (jpeg/jpg/png), max 2MB
- `kelas`: optional, must be X/XI/XII (siswa only)
- `jurusan_id`: optional, must exist in jurusans (siswa only)

---

### 3. PUT /profile/password
**Auth:** Required  
**Content-Type:** application/json

**Request:**
```json
{
  "current_password": "oldpass123",
  "new_password": "newpass123",
  "new_password_confirmation": "newpass123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Password berhasil diubah. Silakan login kembali"
}
```

**Validasi:**
- `current_password`: required, must match current password
- `new_password`: required, min 8 chars, must match confirmation
- `new_password_confirmation`: required

**⚠️ Important:**
- All user tokens will be revoked after password change
- User must login again with new password
- This is for security (old sessions cannot access anymore)

---

## 🔒 Security Features

1. **Avatar Upload:**
   - Max 2MB file size
   - Only jpeg, jpg, png allowed
   - Auto-delete old avatar when uploading new
   - Stored in `storage/app/public/avatars/`

2. **Password Change:**
   - Requires current password verification
   - New password min 8 characters
   - Must have confirmation match
   - **All tokens revoked** after change (force re-login)

3. **Authorization:**
   - All endpoints require authentication
   - Auto-filter profile by authenticated user
   - Role-based field updates (siswa can update kelas/jurusan)

---

## 🧪 Testing Guide

### Test 1: Get Profile (as Siswa)
```bash
POST http://localhost:8000/api/login
{
  "email": "siswa@example.com",
  "password": "password"
}

# Get token, then:
GET http://localhost:8000/api/profile
Headers: Authorization: Bearer {token}
```

### Test 2: Update Profile with Avatar
```bash
PUT http://localhost:8000/api/profile
Headers:
  Authorization: Bearer {token}
  Content-Type: multipart/form-data
Body (form-data):
  nama: "New Name"
  avatar: [select image file]
```

### Test 3: Change Password
```bash
PUT http://localhost:8000/api/profile/password
Headers:
  Authorization: Bearer {token}
  Content-Type: application/json
Body:
{
  "current_password": "password",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}

# After success, login again with new password
```

---

## 📁 File Structure

```
app/
├── Http/Controllers/
│   └── ProfileController.php    ✨ NEW (3 methods)
├── Models/
│   └── User.php                 ✅ UPDATED (added nama accessor/mutator)

routes/
└── api.php                      ✅ UPDATED (3 new routes)

storage/
└── app/public/
    └── avatars/                 📁 Avatar storage directory

Documentation/
└── API_PROFILE.md              ✨ NEW (Complete documentation)
```

---

## 🎊 Implementation Summary

✅ **ProfileController** created with 3 methods  
✅ **3 Routes** registered (`/profile`, `/profile`, `/profile/password`)  
✅ **User Model** enhanced with nama accessor/mutator  
✅ **Avatar Upload** with validation and auto-cleanup  
✅ **Password Change** with security (token revocation)  
✅ **API Documentation** created with examples  
✅ **React Examples** included for frontend integration  

**Total API Endpoints: 69** 🚀

---

## 📖 Documentation

Complete API documentation with examples:
- [API_PROFILE.md](API_PROFILE.md)

Includes:
- ✅ All endpoint details
- ✅ Request/Response examples
- ✅ JavaScript/Axios examples
- ✅ React component examples
- ✅ Validation rules
- ✅ Error handling
- ✅ Testing guide

---

## ✨ Ready for Frontend!

Backend Jagat Kawruh sekarang memiliki **69 API endpoints** lengkap:

**Feature Modules:**
1. ✅ Authentication (4 endpoints)
2. ✅ Jurusan (5 endpoints)
3. ✅ Siswa (6 endpoints)
4. ✅ Kuis (7 endpoints)
5. ✅ Materi (6 endpoints)
6. ✅ PBL (10 endpoints)
7. ✅ Nilai (2 endpoints)
8. ✅ Notifikasi (4 endpoints)
9. ✅ Helpdesk (5 endpoints)
10. ✅ **Profile (3 endpoints)** 🎉 NEW
11. ✅ Admin/Guru Routes (17 endpoints)

Siap untuk diintegrasikan dengan frontend React/Vue! 🚀✨
