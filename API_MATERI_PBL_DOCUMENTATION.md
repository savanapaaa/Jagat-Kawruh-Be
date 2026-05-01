# API Documentation - Materi & PBL Endpoints

Base URL: `http://127.0.0.1:8000/api`

## 📚 Materi Endpoints

### 1. Get All Materi (Admin, Guru, Siswa)
```
GET /materi?kelas=XI&jurusan_id=JUR-1&status=Published
```
**Query params (optional):**
- `kelas` - Filter by kelas (X, XI, XII)
- `jurusan_id` - Filter by jurusan
- `status` - Filter by status (Draft, Published, Archived)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "materi-1",
      "judul": "Pemrograman Dasar - Algoritma",
      "deskripsi": "Materi pengenalan algoritma",
      "kelas": ["X", "XI"],
      "jurusan_id": "JUR-1",
      "jurusan": {
        "id": "JUR-1",
        "nama": "Rekayasa Perangkat Lunak"
      },
      "file_name": "algoritma.pdf",
      "file_size": 1048576,
      "status": "Published",
      "created_at": "2026-01-12T10:00:00.000000Z"
    }
  ]
}
```

### 2. Create Materi (Admin, Guru) - Multipart Form Data
```
POST /materi
Content-Type: multipart/form-data
```
**Body:**
```
judul: Pemrograman Dasar
deskripsi: Materi pengenalan algoritma
kelas: ["X","XI"]  (JSON string)
jurusan_id: JUR-1
status: Published
file: (PDF file, max 10MB, nullable untuk Draft)
```

**Response:**
```json
{
  "success": true,
  "message": "Materi berhasil dibuat",
  "data": {
    "id": "materi-1",
    "judul": "Pemrograman Dasar",
    "deskripsi": "Materi pengenalan algoritma",
    "kelas": ["X", "XI"],
    "jurusan_id": "JUR-1",
    "file_name": "algoritma.pdf",
    "file_path": "materi/1234567890_algoritma.pdf",
    "file_size": 1048576,
    "status": "Published"
  }
}
```

### 3. Get Materi by ID (All roles)
```
GET /materi/{id}
```
**Response:**
```json
{
  "success": true,
  "data": {
    "id": "materi-1",
    "judul": "Pemrograman Dasar",
    "deskripsi": "Materi pengenalan algoritma",
    "kelas": ["X", "XI"],
    "jurusan_id": "JUR-1",
    "file_name": "algoritma.pdf",
    "file_size": 1048576,
    "status": "Published",
    "created_at": "2026-01-12T10:00:00.000000Z"
  }
}
```

### 4. Update Materi (Admin, Guru)
```
PUT /materi/{id}
Content-Type: multipart/form-data
```
**Body (semua optional):**
```
judul: Updated Title
deskripsi: Updated description
kelas: ["XI","XII"]
jurusan_id: JUR-2
status: Archived
file: (new PDF file if replacing)
```

### 5. Delete Materi (Admin, Guru)
```
DELETE /materi/{id}
```
**Response:**
```json
{
  "success": true,
  "message": "Materi berhasil dihapus"
}
```

### 6. Download Materi (All roles)
```
GET /materi/{id}/download
```
**Response:** PDF file download

---

## 🎯 PBL (Project-Based Learning) Endpoints

### 1. Get All PBL Projects (Admin, Guru, Siswa)
```
GET /pbl?kelas=XI&jurusan_id=JUR-1&status=Aktif
```
**Query params (optional):**
- `kelas` - Filter by kelas (X, XI, XII)
- `jurusan_id` - Filter by jurusan
- `status` - Filter by status (Draft, Aktif, Selesai)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "pbl-1",
      "judul": "Aplikasi Perpustakaan Digital",
      "masalah": "Perpustakaan masih menggunakan sistem manual",
      "tujuan_pembelajaran": "Siswa mampu membuat aplikasi web CRUD",
      "panduan": "1. Analisis kebutuhan\n2. Buat ERD...",
      "referensi": "https://laravel.com/docs",
      "kelas": "XI",
      "jurusan_id": "JUR-1",
      "jurusan": {
        "id": "JUR-1",
        "nama": "RPL"
      },
      "status": "Aktif",
      "deadline": "2026-02-15",
      "created_by": "guru@example.com",
      "created_at": "2026-01-12T10:00:00.000000Z"
    }
  ]
}
```

### 2. Create PBL Project (Admin, Guru)
```
POST /pbl
```
**Body:**
```json
{
  "judul": "Aplikasi Perpustakaan Digital",
  "masalah": "Perpustakaan masih menggunakan sistem manual",
  "tujuan_pembelajaran": "Siswa mampu membuat aplikasi web CRUD",
  "panduan": "1. Analisis kebutuhan\n2. Buat ERD\n3. Implementasi",
  "referensi": "https://laravel.com/docs",
  "kelas": "XI",
  "jurusan_id": "JUR-1",
  "status": "Draft",
  "deadline": "2026-02-15"
}
```

### 3. Get PBL by ID (All roles)
```
GET /pbl/{id}
```

### 4. Update PBL (Admin, Guru)
```
PUT /pbl/{id}
```
**Body (all optional):**
```json
{
  "judul": "Updated Title",
  "masalah": "Updated problem",
  "tujuan_pembelajaran": "Updated objectives",
  "panduan": "Updated guidelines",
  "referensi": "Updated references",
  "kelas": "XII",
  "jurusan_id": "JUR-2",
  "status": "Aktif",
  "deadline": "2026-03-01"
}
```

### 5. Delete PBL (Admin, Guru)
```
DELETE /pbl/{id}
```
**Response:**
```json
{
  "success": true,
  "message": "Project PBL berhasil dihapus"
}
```

### 6. Get Kelompok by PBL (Admin, Guru)
```
GET /pbl/{id}/kelompok
```
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "kelompok-1",
      "pbl_id": "pbl-1",
      "nama_kelompok": "Kelompok A1",
      "anggota": ["siswa-3", "siswa-4", "siswa-5"],
      "anggota_details": [
        {
          "siswa_id": "siswa-3",
          "nama": "Ahmad Rizki",
          "nis": "001234"
        },
        {
          "siswa_id": "siswa-4",
          "nama": "Budi Santoso",
          "nis": "001235"
        },
        {
          "siswa_id": "siswa-5",
          "nama": "Citra Dewi",
          "nis": "001236"
        }
      ],
      "created_at": "2026-01-12T10:00:00.000000Z"
    }
  ]
}
```

### 7. Create Kelompok (Admin, Guru)
```
POST /pbl/{id}/kelompok
```
**Body:**
```json
{
  "nama_kelompok": "Kelompok A1",
  "anggota": ["siswa-3", "siswa-4", "siswa-5"]
}
```
**Response:**
```json
{
  "success": true,
  "message": "Kelompok berhasil dibuat",
  "data": {
    "id": "kelompok-1",
    "pbl_id": "pbl-1",
    "nama_kelompok": "Kelompok A1",
    "anggota": ["siswa-3", "siswa-4", "siswa-5"],
    "created_at": "2026-01-12T10:00:00.000000Z"
  }
}
```

### 8. Submit PBL Project (Siswa)
```
POST /pbl/{id}/submit
Content-Type: multipart/form-data
```
**Body:**
```
kelompok_id: kelompok-1
file: (ZIP/RAR/7Z file, max 50MB)
catatan: Project sudah selesai dikerjakan (optional)
```

**Response:**
```json
{
  "success": true,
  "message": "Project berhasil dikumpulkan",
  "data": {
    "id": "submit-1",
    "pbl_id": "pbl-1",
    "kelompok_id": "kelompok-1",
    "file_name": "project.zip",
    "file_path": "pbl_submissions/1234567890_project.zip",
    "file_size": 5242880,
    "catatan": "Project sudah selesai dikerjakan",
    "submitted_at": "2026-01-12T10:00:00.000000Z"
  }
}
```

### 9. Get Submissions by PBL (Admin, Guru)
```
GET /pbl/{id}/submissions
```
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "submit-1",
      "pbl_id": "pbl-1",
      "kelompok_id": "kelompok-1",
      "kelompok": {
        "nama_kelompok": "Kelompok A1",
        "anggota": ["Ahmad Rizki", "Budi Santoso", "Citra Dewi"]
      },
      "file_name": "project.zip",
      "file_size": 5242880,
      "catatan": "Project sudah selesai",
      "nilai": 85,
      "feedback": "Bagus, tapi perlu perbaikan UI",
      "submitted_at": "2026-01-12T10:00:00.000000Z"
    }
  ]
}
```

### 10. Nilai Submission (Admin, Guru)
```
PUT /pbl/submissions/{id}/nilai
```
**Body:**
```json
{
  "nilai": 85,
  "feedback": "Bagus, tapi perlu perbaikan UI"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Nilai berhasil diberikan",
  "data": {
    "id": "submit-1",
    "pbl_id": "pbl-1",
    "kelompok_id": "kelompok-1",
    "nilai": 85,
    "feedback": "Bagus, tapi perlu perbaikan UI"
  }
}
```

---

## 🛠️ JavaScript/Axios Examples

### Upload Materi (Multipart Form Data)
```javascript
const uploadMateri = async (data) => {
  const formData = new FormData();
  formData.append('judul', data.judul);
  formData.append('deskripsi', data.deskripsi);
  formData.append('kelas', JSON.stringify(data.kelas)); // Convert array to JSON string
  formData.append('jurusan_id', data.jurusan_id);
  formData.append('status', data.status);
  formData.append('file', data.file); // File object from input

  const response = await api.post('/materi', formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  });
  return response.data;
};

// Usage:
const materiData = {
  judul: 'Pemrograman Dasar',
  deskripsi: 'Materi algoritma',
  kelas: ['X', 'XI'],
  jurusan_id: 'JUR-1',
  status: 'Published',
  file: fileFromInput // File from <input type="file">
};
await uploadMateri(materiData);
```

### Download Materi
```javascript
const downloadMateri = async (id) => {
  const response = await api.get(`/materi/${id}/download`, {
    responseType: 'blob'
  });
  
  // Create blob link to download
  const url = window.URL.createObjectURL(new Blob([response.data]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', 'materi.pdf');
  document.body.appendChild(link);
  link.click();
  link.remove();
};
```

### Create PBL Project
```javascript
const createPBL = async () => {
  const data = {
    judul: 'Aplikasi Perpustakaan Digital',
    masalah: 'Perpustakaan masih manual',
    tujuan_pembelajaran: 'Siswa mampu membuat CRUD',
    panduan: '1. Analisis\n2. Design\n3. Code',
    referensi: 'https://laravel.com/docs',
    kelas: 'XI',
    jurusan_id: 'JUR-1',
    status: 'Aktif',
    deadline: '2026-02-15'
  };
  
  const response = await api.post('/pbl', data);
  return response.data;
};
```

### Create Kelompok
```javascript
const createKelompok = async (pblId) => {
  const data = {
    nama_kelompok: 'Kelompok A1',
    anggota: ['siswa-3', 'siswa-4', 'siswa-5']
  };
  
  const response = await api.post(`/pbl/${pblId}/kelompok`, data);
  return response.data;
};
```

### Submit PBL Project
```javascript
const submitPBL = async (pblId, kelompokId, file, catatan) => {
  const formData = new FormData();
  formData.append('kelompok_id', kelompokId);
  formData.append('file', file); // ZIP/RAR file from input
  if (catatan) {
    formData.append('catatan', catatan);
  }
  
  const response = await api.post(`/pbl/${pblId}/submit`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' }
  });
  return response.data;
};
```

### Nilai Submission
```javascript
const nilaiSubmission = async (submissionId, nilai, feedback) => {
  const data = {
    nilai: nilai,
    feedback: feedback
  };
  
  const response = await api.put(`/pbl/submissions/${submissionId}/nilai`, data);
  return response.data;
};
```

---

## 📝 Notes

1. **File Upload Limits:**
   - Materi: PDF only, max 10MB
   - PBL Submission: ZIP/RAR/7Z, max 50MB

2. **Status Values:**
   - Materi: `Draft`, `Published`, `Archived`
   - PBL: `Draft`, `Aktif`, `Selesai`

3. **Kelas Array:**
   - When sending as multipart form data, convert array to JSON string: `JSON.stringify(['X', 'XI'])`
   - API will parse it back to array

4. **Auto-generated IDs:**
   - Materi: `materi-1`, `materi-2`, ...
   - PBL: `pbl-1`, `pbl-2`, ...
   - Kelompok: `kelompok-1`, `kelompok-2`, ...
   - Submission: `submit-1`, `submit-2`, ...

5. **File Storage:**
   - Files stored in `storage/app/public/materi/` and `storage/app/public/pbl_submissions/`
   - Accessible via `/storage/materi/filename.pdf` after running `php artisan storage:link`
   - Files auto-deleted when model is deleted

6. **Authentication:**
   - All endpoints require `Bearer {token}` in Authorization header
   - Obtain token from `/login` endpoint
