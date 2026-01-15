# Testing API Jagat Kawruh

## Setup
1. Pastikan server Laravel berjalan: `php artisan serve`
2. Base URL: `http://localhost:8000/api`

## Test Cases

### 1. Test Login
```bash
# Login sebagai Guru
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "guru@example.com",
    "password": "password"
  }'
```

Expected Response:
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
    "token": "..."
  }
}
```

### 2. Test Get Current User
```bash
# Ganti {TOKEN} dengan token dari response login
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer {TOKEN}"
```

### 3. Test Get All Jurusan
```bash
curl -X GET http://localhost:8000/api/jurusan \
  -H "Authorization: Bearer {TOKEN}"
```

Expected Response:
```json
{
  "success": true,
  "data": [
    {
      "id": "JUR-1",
      "nama": "RPL",
      "deskripsi": "Rekayasa Perangkat Lunak - Jurusan yang mempelajari pengembangan software dan aplikasi",
      "created_at": "...",
      "updated_at": "..."
    },
    {
      "id": "JUR-2",
      "nama": "TKJ",
      "deskripsi": "Teknik Komputer dan Jaringan - Jurusan yang mempelajari jaringan komputer dan infrastruktur IT",
      "created_at": "...",
      "updated_at": "..."
    }
    // ...
  ]
}
```

### 4. Test Create Jurusan
```bash
curl -X POST http://localhost:8000/api/jurusan \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "nama": "TBSM",
    "deskripsi": "Teknik Bisnis Sepeda Motor"
  }'
```

### 5. Test Get Jurusan by ID
```bash
curl -X GET http://localhost:8000/api/jurusan/JUR-1 \
  -H "Authorization: Bearer {TOKEN}"
```

### 6. Test Update Jurusan
```bash
curl -X PUT http://localhost:8000/api/jurusan/JUR-1 \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "nama": "RPL",
    "deskripsi": "Rekayasa Perangkat Lunak (Updated)"
  }'
```

### 7. Test Delete Jurusan
```bash
curl -X DELETE http://localhost:8000/api/jurusan/JUR-5 \
  -H "Authorization: Bearer {TOKEN}"
```

## Default Credentials

### Admin
- Email: admin@example.com
- Password: password

### Guru
- Email: guru@example.com
- Password: password

### Siswa
- Email: siswa@example.com
- Password: password

## Authorization Rules
- **Jurusan endpoints**: Hanya Admin dan Guru yang bisa akses (create, update, delete)
- **Siswa**: Tidak memiliki akses ke endpoints jurusan
