# API Documentation - Jagat Kawruh (Learning Management System)

Base URL: `http://127.0.0.1:8000/api`

## 📌 Authentication Endpoints

### 1. Login
```
POST /login
```
**Body:**
```json
{
  "email": "guru@smk.sch.id",
  "password": "password123"
}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "guru@smk.sch.id",
      "nama": "Pak Budi",
      "role": "guru",
      "avatar": null
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

### 2. Register (Admin only)
```
POST /register
```
**Body:**
```json
{
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "nama": "Nama User",
  "role": "siswa|guru|admin"
}
```
**Response (201):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "nama": "Nama User",
      "role": "guru",
      "avatar": null
    },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

### 3. Logout
```
POST /auth/logout
Headers: Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "success": true,
  "message": "Logout berhasil"
}
```

### 4. Get Current User
```
GET /auth/me
Headers: Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "email": "guru@smk.sch.id",
      "nama": "Pak Budi",
      "role": "guru",
      "avatar": null
    }
  }
}
```

---

## 👨‍🏫 Guru Endpoints (Admin)

Semua endpoint di bawah ini butuh:
- Header: `Authorization: Bearer {token}`
- Role: `admin`

Catatan:
- Kolom `jurusan_id` di database menggunakan format string `JUR-1`, `JUR-2`, dst.
- Frontend boleh mengirim `jurusan_id` sebagai angka (`1`) atau string (`"JUR-1"`). Backend akan menormalkan.

### 1. Get All Guru
```
GET /guru
Headers: Authorization: Bearer {token}
```
Query params (opsional):
- `per_page` (default 15)
- `search` (cari `nama/email/nip`)

### 2. Create Guru
```
POST /guru
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "nip": "197812312009011001",
  "nama": "Budi",
  "email": "budi@guru.sch.id",
  "password": "password123",
  "jurusan_id": 1,
  "kelas_diampu": [1, 2]
}
```

### 3. Update Guru
```
PUT /guru/{id}
Headers: Authorization: Bearer {token}
```
**Body (boleh partial):**
```json
{
  "nip": "197812312009011001",
  "nama": "Budi Update",
  "email": "budi.update@guru.sch.id",
  "password": "passwordBaru123",
  "jurusan_id": "JUR-2",
  "kelas_diampu": ["1", "2"]
}
```

### 4. Delete Guru
```
DELETE /guru/{id}
Headers: Authorization: Bearer {token}
```

---

## 🏫 Kelas Endpoints (Admin)

Semua endpoint di bawah ini butuh:
- Header: `Authorization: Bearer {token}`
- Role: `admin`

Catatan:
- `jurusan_id` mengikuti ID jurusan (`JUR-1`, `JUR-2`, ...). Backend menerima angka (`1`) atau string (`"JUR-1"`).

### 1. Get All Kelas
```
GET /kelas
Headers: Authorization: Bearer {token}
```
Query params (opsional):
- `per_page` (default 50)
- `search`

### 2. Create Kelas
```
POST /kelas
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "nama": "X RPL 1",
  "tingkat": "X",
  "jurusan_id": 1
}
```

### 3. Update Kelas
```
PUT /kelas/{id}
Headers: Authorization: Bearer {token}
```
**Body (boleh partial):**
```json
{
  "nama": "XI TKJ 2",
  "tingkat": "XI",
  "jurusan_id": "JUR-2"
}
```

### 4. Delete Kelas
```
DELETE /kelas/{id}
Headers: Authorization: Bearer {token}
```

---

## 📚 Jurusan Endpoints

### 1. Get All Jurusan
```
GET /jurusan
Headers: Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "JUR-1",
      "nama": "RPL",
      "deskripsi": "Rekayasa Perangkat Lunak",
      "created_at": "2026-01-12T10:00:00.000Z",
      "updated_at": "2026-01-12T10:00:00.000Z"
    }
  ]
}
```

### 2. Get Jurusan by ID
```
GET /jurusan/{id}
Headers: Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "JUR-1",
    "nama": "RPL",
    "deskripsi": "Rekayasa Perangkat Lunak",
    "created_at": "2026-01-12T10:00:00.000Z",
    "updated_at": "2026-01-12T10:00:00.000Z"
  }
}
```

### 3. Create Jurusan (Admin/Guru)
```
POST /jurusan
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "nama": "RPL",
  "deskripsi": "Rekayasa Perangkat Lunak"
}
```
**Response (201):**
```json
{
  "success": true,
  "message": "Jurusan berhasil dibuat",
  "data": {
    "id": "JUR-1",
    "nama": "RPL",
    "deskripsi": "Rekayasa Perangkat Lunak",
    "created_at": "2026-01-12T10:00:00.000Z",
    "updated_at": "2026-01-12T10:00:00.000Z"
  }
}
```

### 4. Update Jurusan (Admin/Guru)
```
PUT /jurusan/{id}
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "nama": "RPL",
  "deskripsi": "Rekayasa Perangkat Lunak (Updated)"
}
```
**Response (200):**
```json
{
  "success": true,
  "message": "Jurusan berhasil diupdate",
  "data": {
    "id": "JUR-1",
    "nama": "RPL",
    "deskripsi": "Rekayasa Perangkat Lunak (Updated)",
    "created_at": "2026-01-12T10:00:00.000Z",
    "updated_at": "2026-01-12T10:00:00.000Z"
  }
}
```

### 5. Delete Jurusan (Admin/Guru)
```
DELETE /jurusan/{id}
Headers: Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "success": true,
  "message": "Jurusan berhasil dihapus"
}
```

---

## 🧩 PBL Endpoints (Sintaks)

PBL pada backend mendukung sintaks step-by-step melalui tabel `pbl_sintaks`.

### A) Get Detail PBL (termasuk sintaks)
```
GET /pbl/{id}
Headers: Authorization: Bearer {token}
```
Response akan menyertakan `sintaks` (array) dan `jumlah_sintaks`.

Catatan akses:
- Siswa boleh `GET`, tapi hanya untuk PBL dengan `status=Aktif` dan yang sesuai `kelas`/`jurusan_id` siswa.

### B) Create PBL + Sintaks (opsional)
```
POST /pbl
Headers: Authorization: Bearer {token}
```
Body contoh:
```json
{
  "judul": "PBL Aljabar",
  "masalah": "...",
  "tujuan_pembelajaran": "...",
  "panduan": "...",
  "referensi": "...",
  "kelas": "XI",
  "jurusan_id": "JUR-1",
  "status": "Draft",
  "deadline": "2026-02-01",
  "sintaks": [
    {"judul": "Orientasi masalah", "instruksi": "Baca studi kasus...", "urutan": 1},
    {"judul": "Pengumpulan data", "instruksi": "Cari referensi...", "urutan": 2}
  ]
}
```

Catatan:
- Jika FE tidak mengirim field `sintaks`, backend akan otomatis membuat template sintaks default (5 langkah).

### C) Update PBL + Replace Sintaks (jika field `sintaks` dikirim)
```
PUT /pbl/{id}
Headers: Authorization: Bearer {token}
```
Catatan:
- Jika FE mengirim `sintaks` (array), backend akan *replace* (hapus semua sintaks lama lalu insert ulang sesuai array).
- Jika FE tidak mengirim field `sintaks`, sintaks lama tidak berubah.

### D) CRUD Sintaks per-step

**List sintaks**
```
GET /pbl/{id}/sintaks
Headers: Authorization: Bearer {token}
```

**Tambah sintaks (append)**
```
POST /pbl/{id}/sintaks
Headers: Authorization: Bearer {token}
```
Body:
```json
{ "judul": "Analisis", "instruksi": "Tulis analisis...", "urutan": 3 }
```

**Update sintaks**
```
PUT /pbl/{id}/sintaks/{sintaksId}
Headers: Authorization: Bearer {token}
```

**Delete sintaks**
```
DELETE /pbl/{id}/sintaks/{sintaksId}
Headers: Authorization: Bearer {token}
```


## 📝 Kuis Endpoints

### Upload Gambar Soal (Guru/Admin)
```
POST /kuis/{id}/soal-image
Headers: Authorization: Bearer {token}
Content-Type: multipart/form-data
```
**Form Data:**
- `image` (file, required) — jpeg/jpg/png/webp, max 5MB

**Response (200):**
```json
{
  "success": true,
  "message": "Upload gambar berhasil",
  "data": {
    "path": "soal-images/kuis-1/uuid.webp",
    "url": "http://127.0.0.1:8000/storage/soal-images/kuis-1/uuid.webp"
  }
}
```

Catatan:
- Setelah upload, simpan URL tersebut ke field `image` pada item `soal[]` saat `POST /kuis` atau `PUT /kuis/{id}`.


## 🔒 Authorization

- **Admin**: Akses penuh ke semua endpoints
- **Guru**: Akses ke endpoints jurusan, materi, siswa
- **Siswa**: Akses terbatas (read-only untuk materi)
    "phone": null,
    "address": null,
    "is_active": true,
    "created_at": "2025-12-24T16:00:00.000000Z",
    "updated_at": "2025-12-24T16:00:00.000000Z"
  }
}
```

### 5. Update Profile
```
PUT /profile
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "name": "Nama Baru",
  "phone": "08123456789",
  "address": "Alamat lengkap",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}
```

---

## 👨‍💼 Admin Endpoints (Role: admin)

### 1. Get All Users
```
GET /admin/users?role=siswa&search=nama&per_page=15
Headers: Authorization: Bearer {token}
```
**Query Parameters:**
- `role` (optional): filter by role (admin/guru/siswa)
- `search` (optional): search by name, email, nisn, nip
- `per_page` (optional): items per page (default: 15)

**Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Admin Utama",
        "email": "admin@example.com",
        "role": "admin",
        "nisn": null,
        "nip": null,
        "phone": null,
        "address": null,
        "is_active": true,
        "created_at": "2025-12-24T16:00:00.000000Z",
        "updated_at": "2025-12-24T16:00:00.000000Z"
      }
    ],
    "first_page_url": "http://127.0.0.1:8000/api/admin/users?page=1",
    "from": 1,
    "last_page": 1,
    "per_page": 15,
    "to": 1,
    "total": 1
  }
}
```

### 2. Create User
```
POST /admin/users
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "name": "Nama User",
  "email": "user@example.com",
  "password": "password",
  "role": "siswa",
  "nisn": "0001234567",
  "nip": null,
  "phone": "08123456789",
  "address": "Alamat lengkap",
  "is_active": true
}
```

### 3. Get User by ID
```
GET /admin/users/{id}
Headers: Authorization: Bearer {token}
```

### 4. Update User
```
PUT /admin/users/{id}
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "name": "Nama Baru",
  "email": "emailbaru@example.com",
  "password": "newpassword",
  "role": "guru",
  "is_active": true
}
```

### 5. Delete User
```
DELETE /admin/users/{id}
Headers: Authorization: Bearer {token}
```

### 6. Toggle User Status (Active/Inactive)
```
PATCH /admin/users/{id}/toggle-status
Headers: Authorization: Bearer {token}
```

---

## 👨‍🏫 Guru Endpoints (Role: admin, guru)

### 1. Get All Siswa
```
GET /guru/siswa?search=nama&per_page=15
Headers: Authorization: Bearer {token}
```
**Query Parameters:**
- `search` (optional): search by name, email, nisn
- `per_page` (optional): items per page (default: 15)

### 2. Create Siswa
```
POST /guru/siswa
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "name": "Nama Siswa",
  "email": "siswa@example.com",
  "password": "password",
  "nisn": "0001234567",
  "phone": "08123456789",
  "address": "Alamat lengkap"
}
```

### 3. Get Siswa by ID
```
GET /guru/siswa/{id}
Headers: Authorization: Bearer {token}
```

### 4. Update Siswa
```
PUT /guru/siswa/{id}
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "name": "Nama Baru",
  "email": "emailbaru@example.com",
  "password": "newpassword",
  "nisn": "0009876543",
  "phone": "08198765432",
  "address": "Alamat baru",
  "is_active": true
}
```

### 5. Delete Siswa
```
DELETE /guru/siswa/{id}
Headers: Authorization: Bearer {token}
```

### 6. Toggle Siswa Status
```
PATCH /guru/siswa/{id}/toggle-status
Headers: Authorization: Bearer {token}
```

---

## 🔐 Authorization

Semua endpoint yang memerlukan autentikasi harus menyertakan header:
```
Authorization: Bearer {token}
```

Token didapat dari response login/register.

---

## 📝 Response Format

### Success Response
```json
{
  "success": true,
  "message": "Pesan sukses",
  "data": { }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Pesan error"
}
```

### Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

---

## 👥 User Roles

1. **admin** - Full access ke semua endpoint
2. **guru** - Akses ke endpoint siswa dan profile
3. **siswa** - Hanya akses profile sendiri

---

## 🧪 Testing Accounts

```
Admin:
- Email: admin@example.com
- Password: password

Guru:
- Email: guru@example.com
- Password: password

Siswa:
- Email: siswa@example.com
- Password: password
```

---

## ⚠️ Important Notes

1. **Siswa tidak bisa registrasi sendiri** - harus dibuat oleh Admin atau Guru
2. **Token expired** - Login ulang untuk dapat token baru
3. **CORS** - Sudah dikonfigurasi untuk accept semua origin
4. **Password** - Minimal 8 karakter
5. **Email** - Harus unique

---

## 🔄 Flow Aplikasi

### Login Flow:
1. POST `/api/login` dengan email & password
2. Simpan token dari response
3. Gunakan token untuk request selanjutnya di header Authorization

### Logout Flow:
1. POST `/api/logout` dengan token
2. Hapus token dari localStorage/sessionStorage

### Create Siswa (untuk Guru/Admin):
1. Login sebagai guru/admin
2. POST `/api/guru/siswa` atau `/api/admin/users` dengan data siswa
3. Siswa bisa login dengan email & password yang dibuat

---

## 📱 Frontend Integration Example (React/JavaScript)

### Login
```javascript
const login = async (email, password) => {
  const response = await fetch('http://127.0.0.1:8000/api/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  if (data.success) {
    localStorage.setItem('token', data.data.token);
    localStorage.setItem('user', JSON.stringify(data.data.user));
  }
  
  return data;
};
```

### Get Profile (Authenticated Request)
```javascript
const getProfile = async () => {
  const token = localStorage.getItem('token');
  
  const response = await fetch('http://127.0.0.1:8000/api/profile', {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    }
  });
  
  return await response.json();
};
```

### Create Siswa
```javascript
const createSiswa = async (siswaData) => {
  const token = localStorage.getItem('token');
  
  const response = await fetch('http://127.0.0.1:8000/api/guru/siswa', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify(siswaData)
  });
  
  return await response.json();
};
```

### Logout
```javascript
const logout = async () => {
  const token = localStorage.getItem('token');
  
  await fetch('http://127.0.0.1:8000/api/logout', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    }
  });
  
  localStorage.removeItem('token');
  localStorage.removeItem('user');
};
```

---

## 🛠️ Axios Example

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api',
  headers: {
    'Content-Type': 'application/json',
  }
});

// Intercept request untuk tambahkan token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Login
export const login = (email, password) => 
  api.post('/login', { email, password });

// Get Profile
export const getProfile = () => 
  api.get('/profile');

// Get All Siswa
export const getAllSiswa = (params) => 
  api.get('/guru/siswa', { params });

// Create Siswa
export const createSiswa = (data) => 
  api.post('/guru/siswa', data);

// Update Siswa
export const updateSiswa = (id, data) => 
  api.put(`/guru/siswa/${id}`, data);

// Delete Siswa
export const deleteSiswa = (id) => 
  api.delete(`/guru/siswa/${id}`);
```
