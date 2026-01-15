# Testing API - Siswa & Kuis Endpoints

## Setup
Server: `php artisan serve`
Base URL: `http://localhost:8000/api`

---

## 1. LOGIN (Get Token)

```bash
# Login sebagai Guru
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "guru@example.com",
    "password": "password"
  }'
```

**Save the token untuk request selanjutnya!**

---

## 2. SISWA ENDPOINTS

### Get All Siswa
```bash
curl -X GET "http://localhost:8000/api/siswa" \
  -H "Authorization: Bearer {TOKEN}"
```

### Get Siswa dengan Filter
```bash
# Filter by kelas
curl -X GET "http://localhost:8000/api/siswa?kelas=XII" \
  -H "Authorization: Bearer {TOKEN}"

# Filter by jurusan
curl -X GET "http://localhost:8000/api/siswa?jurusan=JUR-1" \
  -H "Authorization: Bearer {TOKEN}"

# Search by nama atau NIS
curl -X GET "http://localhost:8000/api/siswa?search=ahmad" \
  -H "Authorization: Bearer {TOKEN}"

# Kombinasi filter
curl -X GET "http://localhost:8000/api/siswa?kelas=XII&jurusan=JUR-1&search=ahmad" \
  -H "Authorization: Bearer {TOKEN}"
```

### Get Siswa by ID
```bash
curl -X GET "http://localhost:8000/api/siswa/siswa-4" \
  -H "Authorization: Bearer {TOKEN}"
```

### Create Siswa
```bash
curl -X POST http://localhost:8000/api/siswa \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "nis": "12351",
    "nama": "Andi Setiawan",
    "email": "andi@student.sch.id",
    "password": "password",
    "kelas": "X",
    "jurusan_id": "JUR-1"
  }'
```

### Update Siswa
```bash
curl -X PUT http://localhost:8000/api/siswa/siswa-4 \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "nama": "Ahmad Fauzi Updated",
    "kelas": "XII"
  }'
```

### Delete Siswa
```bash
curl -X DELETE http://localhost:8000/api/siswa/siswa-10 \
  -H "Authorization: Bearer {TOKEN}"
```

---

## 3. KUIS ENDPOINTS

### Get All Kuis
```bash
curl -X GET "http://localhost:8000/api/kuis" \
  -H "Authorization: Bearer {TOKEN}"
```

### Get Kuis dengan Filter
```bash
# Filter by kelas
curl -X GET "http://localhost:8000/api/kuis?kelas=XII" \
  -H "Authorization: Bearer {TOKEN}"

# Filter by status
curl -X GET "http://localhost:8000/api/kuis?status=Aktif" \
  -H "Authorization: Bearer {TOKEN}"
```

### Get Kuis by ID (dengan soal)
```bash
curl -X GET "http://localhost:8000/api/kuis/kuis-1" \
  -H "Authorization: Bearer {TOKEN}"
```

### Create Kuis
```bash
curl -X POST http://localhost:8000/api/kuis \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "judul": "Kuis Pemrograman Web",
    "kelas": ["XII"],
    "batas_waktu": 45,
    "status": "Draft",
    "soal": [
      {
        "pertanyaan": "Apa kepanjangan HTML?",
        "pilihan": {
          "A": "Hyper Text Markup Language",
          "B": "High Tech Modern Language",
          "C": "Home Tool Markup Language",
          "D": "Hyperlinks and Text Markup Language",
          "E": "None"
        },
        "jawaban": "A"
      },
      {
        "pertanyaan": "Tag untuk paragraph?",
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
  }'
```

### Update Kuis
```bash
curl -X PUT http://localhost:8000/api/kuis/kuis-1 \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "judul": "Kuis Algoritma Dasar (Updated)",
    "status": "Aktif"
  }'
```

### Delete Kuis
```bash
curl -X DELETE http://localhost:8000/api/kuis/kuis-3 \
  -H "Authorization: Bearer {TOKEN}"
```

---

## 4. SUBMIT KUIS (Siswa)

### Submit Jawaban Kuis
```bash
# Login sebagai siswa terlebih dahulu
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "ahmad@student.sch.id",
    "password": "password"
  }'

# Submit kuis
curl -X POST http://localhost:8000/api/kuis/kuis-1/submit \
  -H "Authorization: Bearer {SISWA_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "siswa_id": 4,
    "jawaban": {
      "soal-1": "A",
      "soal-2": "B",
      "soal-3": "B"
    },
    "waktu_mulai": "2026-01-12T10:00:00.000Z",
    "waktu_selesai": "2026-01-12T10:15:00.000Z"
  }'
```

Expected Response:
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

---

## 5. GET NILAI KUIS

### Get All Nilai for Kuis
```bash
curl -X GET "http://localhost:8000/api/kuis/kuis-1/nilai" \
  -H "Authorization: Bearer {TOKEN}"
```

### Get Nilai with Filter
```bash
# Filter by kelas
curl -X GET "http://localhost:8000/api/kuis/kuis-1/nilai?kelas=XII" \
  -H "Authorization: Bearer {TOKEN}"

# Filter by siswa_id
curl -X GET "http://localhost:8000/api/kuis/kuis-1/nilai?siswa_id=4" \
  -H "Authorization: Bearer {TOKEN}"
```

---

## Quick Test Workflow

1. **Login sebagai Guru** → Get TOKEN
2. **Get All Siswa** → Lihat daftar siswa
3. **Get All Kuis** → Lihat kuis yang tersedia
4. **Create Kuis Baru** → Buat kuis dengan soal
5. **Login sebagai Siswa** → Get SISWA_TOKEN
6. **Submit Kuis** → Kerjakan kuis
7. **Login kembali sebagai Guru**
8. **Get Nilai Kuis** → Lihat hasil siswa

---

## Default Credentials

**Guru:**
- Email: guru@example.com
- Password: password

**Siswa:**
- ahmad@student.sch.id / password
- siti@student.sch.id / password
- budi@student.sch.id / password
- dewi@student.sch.id / password
- rizki@student.sch.id / password
- maya@student.sch.id / password
