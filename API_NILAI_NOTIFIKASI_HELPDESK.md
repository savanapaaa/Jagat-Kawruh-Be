# API Documentation - Nilai, Notifikasi & Helpdesk

Base URL: `http://127.0.0.1:8000/api`

## 📊 Nilai Endpoints

### 1. Get Nilai Siswa (Aggregate dari Kuis & PBL)
```
GET /nilai?type=all&siswa_id=3&kelas=XI
```
**Query Parameters:**
- `type` (optional): `kuis`, `pbl`, `all` (default: all)
- `siswa_id` (optional for guru/admin, auto for siswa)
- `kelas` (optional for guru/admin)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "kuis": [
      {
        "id": "nilai-kuis-1",
        "kuis_id": "kuis-1",
        "kuis_judul": "Kuis Algoritma",
        "siswa_id": "siswa-1",
        "siswa_nama": "Ahmad Rizki",
        "kelas": "XI",
        "nilai": 80,
        "tanggal": "2026-01-12T10:00:00.000000Z"
      }
    ],
    "pbl": [
      {
        "id": "nilai-pbl-1",
        "project_id": "pbl-1",
        "project_judul": "Aplikasi Perpustakaan",
        "kelompok_id": "kelompok-1",
        "kelompok_nama": "Kelompok A1",
        "nilai": 85,
        "feedback": "Bagus, UI menarik",
        "tanggal": "2026-01-12T10:00:00.000000Z"
      }
    ]
  }
}
```

**Notes:**
- Siswa hanya bisa lihat nilai sendiri (auto filter by auth user)
- Guru/Admin bisa filter by siswa_id atau kelas
- Type filter untuk memilih jenis nilai yang ditampilkan

### 2. Get Nilai by Kelas (Guru/Admin only)
```
GET /nilai/kelas/XI
```
**Path Parameters:**
- `kelas`: X, XI, XII

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "siswa_id": "siswa-3",
      "nama": "Ahmad Rizki",
      "nis": "001234",
      "kelas": "XI",
      "nilai_kuis": [
        {
          "kuis_id": "kuis-1",
          "kuis_judul": "Algoritma Dasar",
          "nilai": 80,
          "tanggal": "2026-01-12T10:00:00.000000Z"
        }
      ],
      "nilai_pbl": [
        {
          "project_id": "pbl-1",
          "project_judul": "Aplikasi Perpustakaan",
          "nilai": 85,
          "tanggal": "2026-01-12T10:00:00.000000Z"
        }
      ],
      "rata_rata_kuis": 80,
      "rata_rata_pbl": 85
    }
  ]
}
```

**Notes:**
- Menampilkan semua siswa di kelas tersebut
- Termasuk rata-rata nilai kuis dan PBL
- Hanya untuk role admin dan guru

---

## 🔔 Notifikasi Endpoints

### 1. Get All Notifikasi
```
GET /notifikasi?tipe=kuis
```
**Query Parameters:**
- `tipe` (optional): `kuis`, `materi`, `pbl`, `pengumuman`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "notif-1",
      "judul": "Kuis Baru",
      "pesan": "Ada kuis baru: Algoritma Dasar",
      "tipe": "kuis",
      "read": false,
      "created_at": "2026-01-12T10:00:00.000000Z"
    },
    {
      "id": "notif-2",
      "judul": "Pengumuman Libur",
      "pesan": "Libur semester tanggal 20 Juni",
      "tipe": "pengumuman",
      "read": true,
      "created_at": "2026-01-10T08:00:00.000000Z"
    }
  ]
}
```

**Notes:**
- Menampilkan notifikasi untuk user yang sedang login
- Termasuk broadcast notifikasi (user_id = null)
- Bisa filter by tipe

### 2. Create Notifikasi (Admin/Guru only)
```
POST /notifikasi
```
**Request Body:**
```json
{
  "judul": "Pengumuman Penting",
  "pesan": "Libur tanggal 17 Agustus",
  "tipe": "pengumuman",
  "user_id": null
}
```

**Fields:**
- `judul` (required): Judul notifikasi
- `pesan` (required): Isi pesan
- `tipe` (required): `kuis`, `materi`, `pbl`, `pengumuman`
- `user_id` (optional): ID user penerima. Null = broadcast ke semua

**Response (201):**
```json
{
  "success": true,
  "message": "Notifikasi berhasil dibuat",
  "data": {
    "id": "notif-5",
    "judul": "Pengumuman Penting",
    "pesan": "Libur tanggal 17 Agustus",
    "tipe": "pengumuman",
    "user_id": null,
    "read": false,
    "created_at": "2026-01-13T10:00:00.000000Z"
  }
}
```

### 3. Mark as Read
```
PUT /notifikasi/{id}/read
```
**Response (200):**
```json
{
  "success": true,
  "message": "Notifikasi ditandai sudah dibaca",
  "data": {
    "id": "notif-1",
    "read": true
  }
}
```

**Notes:**
- User hanya bisa mark notifikasi miliknya sendiri
- Broadcast notifikasi bisa di-mark semua user

### 4. Delete Notifikasi
```
DELETE /notifikasi/{id}
```
**Response (200):**
```json
{
  "success": true,
  "message": "Notifikasi berhasil dihapus"
}
```

**Notes:**
- User bisa delete notifikasi miliknya
- Admin/Guru bisa delete semua notifikasi

---

## 🎫 Helpdesk Endpoints

### 1. Get All Tickets
```
GET /helpdesk?status=open
```
**Query Parameters:**
- `status` (optional): `open`, `progress`, `solved`

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": "ticket-1",
      "siswa_id": "siswa-1",
      "siswa": {
        "nama": "Ahmad Rizki",
        "kelas": "XI",
        "nis": "001234"
      },
      "kategori": "Akun",
      "judul": "Lupa Password",
      "pesan": "Saya lupa password akun",
      "status": "solved",
      "balasan": "Password sudah direset",
      "created_at": "2026-01-12T10:00:00.000000Z",
      "updated_at": "2026-01-12T14:00:00.000000Z"
    }
  ]
}
```

**Notes:**
- Siswa hanya bisa lihat ticket miliknya
- Guru/Admin bisa lihat semua tickets
- Bisa filter by status

### 2. Get Ticket by ID
```
GET /helpdesk/{id}
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "ticket-1",
    "siswa_id": "siswa-1",
    "siswa": {
      "nama": "Ahmad Rizki",
      "kelas": "XI",
      "nis": "001234"
    },
    "kategori": "Akun",
    "judul": "Lupa Password",
    "pesan": "Saya lupa password akun",
    "status": "solved",
    "balasan": "Password sudah direset",
    "created_at": "2026-01-12T10:00:00.000000Z",
    "updated_at": "2026-01-12T14:00:00.000000Z"
  }
}
```

### 3. Create Ticket (Siswa)
```
POST /helpdesk
```
**Request Body:**
```json
{
  "kategori": "Akun",
  "judul": "Lupa Password",
  "pesan": "Saya lupa password akun saya. Mohon bantuannya."
}
```

**Fields:**
- `kategori` (required): `Akun`, `Kuis`, `Materi`, `PBL`, `Lainnya`
- `judul` (required): Judul ticket
- `pesan` (required): Deskripsi masalah

**Response (201):**
```json
{
  "success": true,
  "message": "Ticket berhasil dibuat",
  "data": {
    "id": "ticket-6",
    "siswa_id": 3,
    "kategori": "Akun",
    "judul": "Lupa Password",
    "pesan": "Saya lupa password akun saya",
    "status": "open",
    "balasan": null,
    "created_at": "2026-01-13T10:00:00.000000Z"
  }
}
```

### 4. Update Ticket Status (Guru/Admin)
```
PUT /helpdesk/{id}/status
```
**Request Body:**
```json
{
  "status": "progress",
  "balasan": "Sedang kami proses. Terima kasih."
}
```

**Fields:**
- `status` (required): `open`, `progress`, `solved`
- `balasan` (optional): Response dari guru/admin

**Response (200):**
```json
{
  "success": true,
  "message": "Status ticket berhasil diupdate",
  "data": {
    "id": "ticket-1",
    "status": "progress",
    "balasan": "Sedang kami proses. Terima kasih.",
    "updated_at": "2026-01-13T10:00:00.000000Z"
  }
}
```

**Notes:**
- Hanya guru/admin yang bisa update status
- Siswa tidak bisa update ticket setelah dibuat

### 5. Delete Ticket
```
DELETE /helpdesk/{id}
```
**Response (200):**
```json
{
  "success": true,
  "message": "Ticket berhasil dihapus"
}
```

**Notes:**
- Siswa bisa delete ticket miliknya sendiri
- Admin/Guru bisa delete semua tickets

---

## 🛠️ JavaScript/Axios Examples

### Get Nilai Siswa
```javascript
// Siswa lihat nilai sendiri
const getNilaiSiswa = async () => {
  const response = await api.get('/nilai', {
    params: { type: 'all' }
  });
  return response.data;
};

// Guru lihat nilai by kelas
const getNilaiKelas = async (kelas) => {
  const response = await api.get(`/nilai/kelas/${kelas}`);
  return response.data;
};
```

### Create Notifikasi (Broadcast)
```javascript
const createNotifikasi = async () => {
  const data = {
    judul: 'Pengumuman Libur',
    pesan: 'Libur semester dimulai 20 Juni 2026',
    tipe: 'pengumuman',
    user_id: null // Broadcast ke semua
  };
  
  const response = await api.post('/notifikasi', data);
  return response.data;
};

// Mark notifikasi as read
const markAsRead = async (notifId) => {
  const response = await api.put(`/notifikasi/${notifId}/read`);
  return response.data;
};
```

### Create Helpdesk Ticket (Siswa)
```javascript
const createTicket = async () => {
  const data = {
    kategori: 'Kuis',
    judul: 'Error Submit Kuis',
    pesan: 'Saat submit kuis muncul error 500'
  };
  
  const response = await api.post('/helpdesk', data);
  return response.data;
};

// Update ticket status (Guru)
const updateTicketStatus = async (ticketId) => {
  const data = {
    status: 'solved',
    balasan: 'Masalah sudah diperbaiki. Silakan coba lagi.'
  };
  
  const response = await api.put(`/helpdesk/${ticketId}/status`, data);
  return response.data;
};
```

---

## 📝 Summary

**Total Endpoints Baru: 12**

**Nilai (2):**
- GET `/nilai` - Get nilai siswa (kuis + PBL)
- GET `/nilai/kelas/{kelas}` - Get nilai by kelas

**Notifikasi (4):**
- GET `/notifikasi` - Get all notifikasi
- POST `/notifikasi` - Create notifikasi
- PUT `/notifikasi/{id}/read` - Mark as read
- DELETE `/notifikasi/{id}` - Delete notifikasi

**Helpdesk (5):**
- GET `/helpdesk` - Get all tickets
- GET `/helpdesk/{id}` - Get ticket by ID
- POST `/helpdesk` - Create ticket
- PUT `/helpdesk/{id}/status` - Update status
- DELETE `/helpdesk/{id}` - Delete ticket

**Total Endpoints API: 66**

---

## 🗂️ Database Schema

### Tabel `notifikasis`
```
id (string, PK): notif-1, notif-2, ...
user_id (FK → users, nullable): null = broadcast
judul (string)
pesan (text)
tipe (enum): kuis, materi, pbl, pengumuman
read (boolean): default false
```

### Tabel `helpdesks`
```
id (string, PK): ticket-1, ticket-2, ...
siswa_id (FK → users)
kategori (enum): Akun, Kuis, Materi, PBL, Lainnya
judul (string)
pesan (text)
status (enum): open, progress, solved
balasan (text, nullable)
```

**Sample Data Seeded:**
- 5 Notifikasi (2 broadcast, 3 untuk siswa tertentu)
- 5 Helpdesk tickets (berbagai kategori dan status)
