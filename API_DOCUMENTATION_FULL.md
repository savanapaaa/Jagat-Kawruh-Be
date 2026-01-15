# API Documentation - Jagat Kawruh (Learning Management System)

Base URL: `http://127.0.0.1:8000/api`

## 📌 Authentication Endpoints

### 1. Login
```http
POST /login
```
**Body:**
```json
{
  "email": "guru@example.com",
  "password": "password"
}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 2,
      "email": "guru@example.com",
      "nama": "Guru Contoh",
      "role": "guru",
      "avatar": null
    },
    "token": "1|xxxxx..."
  }
}
```

### 2. Register (Admin only)
```http
POST /register
```

### 3. Logout
```http
POST /auth/logout
Headers: Authorization: Bearer {token}
```

### 4. Get Current User
```http
GET /auth/me
Headers: Authorization: Bearer {token}
```

---

## 📚 Jurusan Endpoints

### Get All Jurusan
```http
GET /jurusan
Headers: Authorization: Bearer {token}
```

### Get Jurusan by ID
```http
GET /jurusan/{id}
Headers: Authorization: Bearer {token}
```

### Create Jurusan (Admin/Guru)
```http
POST /jurusan
Headers: Authorization: Bearer {token}
```

### Update Jurusan (Admin/Guru)
```http
PUT /jurusan/{id}
Headers: Authorization: Bearer {token}
```

### Delete Jurusan (Admin/Guru)
```http
DELETE /jurusan/{id}
Headers: Authorization: Bearer {token}
```

---

## 👨‍🎓 Siswa Endpoints

### Get All Siswa
```http
GET /siswa?kelas=XII&jurusan=JUR-1&search=ahmad
Headers: Authorization: Bearer {token}
```
**Query Parameters:**
- `kelas` (optional): X, XI, XII
- `jurusan` (optional): Filter by jurusan ID
- `search` (optional): Search by nama or NIS

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "siswa-4",
      "nis": "12345",
      "nama": "Ahmad Fauzi",
      "email": "ahmad@student.sch.id",
      "kelas": "XII",
      "jurusan_id": "JUR-1",
      "jurusan": {
        "id": "JUR-1",
        "nama": "RPL"
      },
      "avatar": null,
      "created_at": "2026-01-12T16:42:15.000000Z"
    }
  ]
}
```

### Get Siswa by ID
```http
GET /siswa/{id}
Headers: Authorization: Bearer {token}
```

### Create Siswa (Admin/Guru)
```http
POST /siswa
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "nis": "12351",
  "nama": "Andi Setiawan",
  "email": "andi@student.sch.id",
  "password": "password",
  "kelas": "X",
  "jurusan_id": "JUR-1"
}
```

### Update Siswa (Admin/Guru)
```http
PUT /siswa/{id}
Headers: Authorization: Bearer {token}
```

### Delete Siswa (Admin/Guru)
```http
DELETE /siswa/{id}
Headers: Authorization: Bearer {token}
```

### Import Siswa Bulk (Admin/Guru)
```http
POST /siswa/import
Headers: Authorization: Bearer {token}
Content-Type: multipart/form-data
```
**Body:**
```
file: excel/csv file
```

---

## 📝 Kuis Endpoints

### Get All Kuis
```http
GET /kuis?kelas=XII&status=Aktif
Headers: Authorization: Bearer {token}
```
**Query Parameters:**
- `kelas` (optional): X, XI, XII
- `status` (optional): Draft, Aktif, Selesai

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "kuis-1",
      "judul": "Kuis Algoritma Dasar",
      "kelas": ["X", "XI"],
      "batas_waktu": 30,
      "jumlah_soal": 3,
      "status": "Aktif",
      "created_at": "2026-01-12T16:42:15.000000Z"
    }
  ]
}
```

### Get Kuis by ID (with questions)
```http
GET /kuis/{id}
Headers: Authorization: Bearer {token}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "kuis-1",
    "judul": "Kuis Algoritma Dasar",
    "kelas": ["X", "XI"],
    "batas_waktu": 30,
    "status": "Aktif",
    "soal": [
      {
        "id": "soal-1",
        "pertanyaan": "Apa itu algoritma?",
        "image": null,
        "pilihan": {
          "A": "Langkah-langkah sistematis untuk menyelesaikan masalah",
          "B": "Bahasa pemrograman",
          "C": "Kode program",
          "D": "Syntax",
          "E": "Compiler"
        },
        "jawaban": "A"
      }
    ]
  }
}
```

### Create Kuis (Admin/Guru)
```http
POST /kuis
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "judul": "Kuis Pemrograman Web",
  "kelas": ["XII"],
  "batas_waktu": 45,
  "status": "Draft",
  "soal": [
    {
      "pertanyaan": "Apa kepanjangan HTML?",
      "image": null,
      "pilihan": {
        "A": "Hyper Text Markup Language",
        "B": "High Tech Modern Language",
        "C": "Home Tool Markup Language",
        "D": "Hyperlinks and Text Markup Language",
        "E": "None of the above"
      },
      "jawaban": "A"
    },
    {
      "pertanyaan": "Tag untuk membuat paragraph?",
      "pilihan": {
        "A": "<para>",
        "B": "<p>",
        "C": "<paragraph>",
        "D": "<text>",
        "E": "<div>"
      },
      "jawaban": "B"
    }
  ]
}
```

### Update Kuis (Admin/Guru)
```http
PUT /kuis/{id}
Headers: Authorization: Bearer {token}
```

### Delete Kuis (Admin/Guru)
```http
DELETE /kuis/{id}
Headers: Authorization: Bearer {token}
```

### Submit Kuis (Siswa)
```http
POST /kuis/{id}/submit
Headers: Authorization: Bearer {token}
```
**Body:**
```json
{
  "siswa_id": 4,
  "jawaban": {
    "soal-1": "A",
    "soal-2": "B",
    "soal-3": "B"
  },
  "waktu_mulai": "2026-01-12T10:00:00.000Z",
  "waktu_selesai": "2026-01-12T10:15:00.000Z"
}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "nilai": 100,
    "benar": 3,
    "salah": 0,
    "detail": [
      {
        "soal_id": "soal-1",
        "jawaban_siswa": "A",
        "jawaban_benar": "A",
        "benar": true
      },
      {
        "soal_id": "soal-2",
        "jawaban_siswa": "B",
        "jawaban_benar": "B",
        "benar": true
      },
      {
        "soal_id": "soal-3",
        "jawaban_siswa": "B",
        "jawaban_benar": "B",
        "benar": true
      }
    ]
  }
}
```

### Get Nilai Kuis
```http
GET /kuis/{id}/nilai?kelas=XII&siswa_id=4
Headers: Authorization: Bearer {token}
```
**Query Parameters:**
- `kelas` (optional): Filter by kelas
- `siswa_id` (optional): Filter by specific siswa

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "siswa_id": 4,
      "siswa_nama": "Ahmad Fauzi",
      "siswa_nis": "12345",
      "siswa_kelas": "XII",
      "nilai": 100,
      "benar": 3,
      "salah": 0,
      "waktu_mulai": "2026-01-12T10:00:00.000000Z",
      "waktu_selesai": "2026-01-12T10:15:00.000000Z",
      "created_at": "2026-01-12T16:42:15.000000Z"
    }
  ]
}
```

---

## 🔑 Default Credentials

### Admin
- Email: admin@example.com
- Password: password

### Guru
- Email: guru@example.com
- Password: password

### Siswa
- Email: ahmad@student.sch.id (atau siswa lainnya)
- Password: password

---

## 🔒 Authorization Rules

- **Admin**: Akses penuh ke semua endpoints
- **Guru**: Akses ke jurusan, siswa, kuis (CRUD)
- **Siswa**: Hanya bisa submit kuis dan lihat profile sendiri

---

## 📊 Sample Data

### Jurusan
- JUR-1: RPL (Rekayasa Perangkat Lunak)
- JUR-2: TKJ (Teknik Komputer dan Jaringan)
- JUR-3: MM (Multimedia)
- JUR-4: AKL (Akuntansi dan Keuangan Lembaga)

### Siswa Sample
- Ahmad Fauzi - XII RPL (nis: 12345)
- Siti Nurhaliza - XII RPL (nis: 12346)
- Budi Santoso - XI TKJ (nis: 12347)
- Dewi Lestari - XI MM (nis: 12348)
- Rizki Pratama - X RPL (nis: 12349)
- Maya Safitri - X AKL (nis: 12350)

### Kuis Sample
- kuis-1: Kuis Algoritma Dasar (3 soal, Aktif)
- kuis-2: Kuis Database MySQL (2 soal, Aktif)
- kuis-3: Kuis OOP (Draft, belum ada soal)
