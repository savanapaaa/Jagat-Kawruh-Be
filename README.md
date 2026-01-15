# Jagat Kawruh - Backend API

Backend REST API untuk Learning Management System (LMS) "Jagat Kawruh" menggunakan Laravel dengan autentikasi berbasis role (Admin, Guru, Siswa).

## 🚀 Fitur

- ✅ Autentikasi dengan Laravel Sanctum
- ✅ Role-based Access Control (Admin, Guru, Siswa)
- ✅ Manajemen User (CRUD)
- ✅ Manajemen Jurusan (CRUD)
- ✅ Auto-generate custom ID untuk Jurusan (JUR-1, JUR-2, etc)
- ✅ RESTful API
- ✅ Response JSON untuk konsumsi Frontend

## 📋 Role & Permission

### Admin
- Akses penuh ke semua endpoint
- Kelola semua user (Admin, Guru, Siswa)
- Kelola jurusan
- CRUD user & jurusan
- Toggle status aktif/nonaktif user

### Guru
- Kelola akun siswa
- Kelola jurusan (CRUD)
- CRUD siswa
- Toggle status aktif/nonaktif siswa

### Siswa
- Hanya bisa akses dan update profile sendiri
- Tidak bisa registrasi sendiri (dibuat oleh Admin/Guru)
- Read-only access untuk materi pembelajaran

## 📦 Instalasi

### Requirements
- PHP >= 8.2
- Composer
- MySQL
- Node.js & NPM (optional)

### Langkah Instalasi

1. **Clone atau copy project**

2. **Install dependencies**
```bash
composer install
```

3. **Setup environment**
```bash
copy .env.example .env
```

4. **Edit file .env**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_media_pembelajaran
DB_USERNAME=root
DB_PASSWORD=your_password
```

5. **Generate application key**
```bash
php artisan key:generate
```

6. **Buat database**
Buat database MySQL dengan nama `laravel_media_pembelajaran`

7. **Run migration**
```bash
php artisan migrate
```

8. **Run seeder (optional)**
```bash
php artisan db:seed
```

Seeder akan membuat 3 user default:
- **Admin**: admin@example.com / password
- **Guru**: guru@example.com / password
- **Siswa**: siswa@example.com / password

9. **Run server**
```bash
php artisan serve
```

Server akan berjalan di `http://localhost:8000`

## API Endpoints

### Base URL
```
http://localhost:8000/api
```

### Authentication

#### Register (Admin/Guru only)
```http
POST /register
Content-Type: application/json

{
  "name": "Nama User",
  "email": "email@example.com",
  "password": "password",
  "password_confirmation": "password",
  "role": "admin", // atau "guru"
  "nip": "1234567890" // optional untuk guru
}
```

#### Login (Semua Role)
```http
POST /login
Content-Type: application/json

{
  "email": "email@example.com",
  "password": "password"
}
```

Response:
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "user": {
      "id": 1,
      "name": "Nama User",
      "email": "email@example.com",
      "role": "admin"
    },
    "token": "1|xxxxxxxxxxxxx"
  }
}
```

#### Logout
```http
POST /logout
Authorization: Bearer {token}
```

#### Get Profile
```http
GET /profile
Authorization: Bearer {token}
```

#### Update Profile
```http
PUT /profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nama Baru",
  "phone": "08123456789",
  "address": "Alamat lengkap",
  "password": "newpassword", // optional
  "password_confirmation": "newpassword"
}
```

### Admin Routes

Semua endpoint di bawah ini memerlukan role **admin**.

#### Get All Users
```http
GET /admin/users?role=siswa&search=nama&per_page=15
Authorization: Bearer {token}
```

#### Create User
```http
POST /admin/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nama User",
  "email": "email@example.com",
  "password": "password",
  "role": "siswa", // admin, guru, atau siswa
  "nisn": "0001234567", // untuk siswa
  "nip": "1234567890", // untuk guru
  "phone": "08123456789",
  "address": "Alamat lengkap",
  "is_active": true
}
```

#### Get Specific User
```http
GET /admin/users/{id}
Authorization: Bearer {token}
```

#### Update User
```http
PUT /admin/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nama Baru",
  "email": "emailbaru@example.com",
  "role": "guru",
  "is_active": false
}
```

#### Delete User
```http
DELETE /admin/users/{id}
Authorization: Bearer {token}
```

#### Toggle User Status
```http
PATCH /admin/users/{id}/toggle-status
Authorization: Bearer {token}
```

### Guru Routes

Semua endpoint di bawah ini memerlukan role **admin** atau **guru**.

#### Get All Siswa
```http
GET /guru/siswa?search=nama&per_page=15
Authorization: Bearer {token}
```

#### Create Siswa
```http
POST /guru/siswa
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nama Siswa",
  "email": "siswa@example.com",
  "password": "password",
  "nisn": "0001234567",
  "phone": "08123456789",
  "address": "Alamat lengkap"
}
```

#### Get Specific Siswa
```http
GET /guru/siswa/{id}
Authorization: Bearer {token}
```

#### Update Siswa
```http
PUT /guru/siswa/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Nama Baru",
  "email": "emailbaru@example.com",
  "nisn": "0009876543",
  "is_active": true
}
```

#### Delete Siswa
```http
DELETE /guru/siswa/{id}
Authorization: Bearer {token}
```

#### Toggle Siswa Status
```http
PATCH /guru/siswa/{id}/toggle-status
Authorization: Bearer {token}
```

## Response Format

### Success Response
```json
{
  "success": true,
  "message": "Pesan sukses",
  "data": {
    // data object atau array
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Pesan error"
}
```

### Validation Error Response
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

## Testing dengan Postman/Thunder Client

1. Import collection atau buat request manual
2. Untuk protected routes, tambahkan header:
   ```
   Authorization: Bearer {your_token}
   ```
3. Token didapat dari response login

## CORS Configuration

Untuk koneksi dengan React frontend, tambahkan konfigurasi CORS:

1. Install CORS support (sudah terinstall di Laravel 11)
2. Edit `config/cors.php` jika perlu custom configuration
3. Atau tambahkan domain React di `.env`:
```env
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

## Database Schema

### Users Table
- id (primary key)
- name
- email (unique)
- password
- role (enum: admin, guru, siswa)
- nisn (unique, nullable) - untuk siswa
- nip (unique, nullable) - untuk guru
- phone (nullable)
- address (nullable)
- is_active (boolean)
- timestamps

## Security Notes

- Password di-hash menggunakan bcrypt
- Token menggunakan Laravel Sanctum
- Middleware role untuk proteksi endpoint
- Validasi input di semua endpoint
- Admin tidak bisa hapus/nonaktifkan diri sendiri

## Troubleshooting

### Error 500 - Internal Server Error
- Pastikan database sudah dibuat
- Cek `.env` sudah benar
- Run `php artisan migrate`
- Cek log di `storage/logs/laravel.log`

### Token Invalid
- Pastikan header Authorization: Bearer {token}
- Token expired, login ulang untuk dapat token baru

### CORS Error
- Tambahkan domain frontend di SANCTUM_STATEFUL_DOMAINS
- Atau gunakan `php artisan config:clear`

## Development

Untuk development, bisa menggunakan:
```bash
# Artisan tinker
php artisan tinker

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Migrate fresh with seed
php artisan migrate:fresh --seed
```

## License

MIT License

## Author

Sistem Media Pembelajaran Backend
