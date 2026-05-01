# Class Diagram - Sistem E-Learning Jagat Kawruh

## Diagram UML dengan Method OOP Standar

```mermaid
classDiagram
    direction TB

    %% ============================================
    %% CLASS DEFINITIONS WITH ATTRIBUTES AND METHODS
    %% ============================================

    class User {
        -int id
        -string name
        -string email
        -string password
        -string role
        -string nisn
        -string nis
        -string nip
        -string kelas
        -int kelas_id
        -int jurusan_id
        -string kelas_diampu
        -string phone
        -string address
        -string avatar
        -boolean is_active
        -datetime created_at
        -datetime updated_at
        +create() User
        +read() User
        +update() boolean
        +delete() boolean
        +getId() int
        +setId(int id) void
        +getName() string
        +setName(string name) void
        +getEmail() string
        +setEmail(string email) void
        +getPassword() string
        +setPassword(string password) void
        +getRole() string
        +setRole(string role) void
        +getNisn() string
        +setNisn(string nisn) void
        +getNis() string
        +setNis(string nis) void
        +getNip() string
        +setNip(string nip) void
        +getKelas() string
        +setKelas(string kelas) void
        +getKelasId() int
        +setKelasId(int kelas_id) void
        +getJurusanId() int
        +setJurusanId(int jurusan_id) void
        +getKelasDiampu() string
        +setKelasDiampu(string kelas_diampu) void
        +getPhone() string
        +setPhone(string phone) void
        +getAddress() string
        +setAddress(string address) void
        +getAvatar() string
        +setAvatar(string avatar) void
        +getIsActive() boolean
        +setIsActive(boolean is_active) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Jurusan {
        -string id
        -string nama
        -string deskripsi
        -datetime created_at
        -datetime updated_at
        +create() Jurusan
        +read() Jurusan
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getNama() string
        +setNama(string nama) void
        +getDeskripsi() string
        +setDeskripsi(string deskripsi) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Kelas {
        -int id
        -string nama
        -int tingkat
        -int jurusan_id
        -datetime created_at
        -datetime updated_at
        +create() Kelas
        +read() Kelas
        +update() boolean
        +delete() boolean
        +getId() int
        +setId(int id) void
        +getNama() string
        +setNama(string nama) void
        +getTingkat() int
        +setTingkat(int tingkat) void
        +getJurusanId() int
        +setJurusanId(int jurusan_id) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Materi {
        -string id
        -string judul
        -string deskripsi
        -string kelas
        -int jurusan_id
        -string file_name
        -string file_path
        -int file_size
        -string status
        -int created_by
        -datetime created_at
        -datetime updated_at
        +create() Materi
        +read() Materi
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getJudul() string
        +setJudul(string judul) void
        +getDeskripsi() string
        +setDeskripsi(string deskripsi) void
        +getKelas() string
        +setKelas(string kelas) void
        +getJurusanId() int
        +setJurusanId(int jurusan_id) void
        +getFileName() string
        +setFileName(string file_name) void
        +getFilePath() string
        +setFilePath(string file_path) void
        +getFileSize() int
        +setFileSize(int file_size) void
        +getStatus() string
        +setStatus(string status) void
        +getCreatedBy() int
        +setCreatedBy(int created_by) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Kuis {
        -string id
        -string judul
        -string kelas
        -int batas_waktu
        -string status
        -int created_by
        -datetime created_at
        -datetime updated_at
        +create() Kuis
        +read() Kuis
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getJudul() string
        +setJudul(string judul) void
        +getKelas() string
        +setKelas(string kelas) void
        +getBatasWaktu() int
        +setBatasWaktu(int batas_waktu) void
        +getStatus() string
        +setStatus(string status) void
        +getCreatedBy() int
        +setCreatedBy(int created_by) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Soal {
        -string id
        -string kuis_id
        -string pertanyaan
        -string image
        -array pilihan
        -string jawaban
        -int urutan
        -datetime created_at
        -datetime updated_at
        +create() Soal
        +read() Soal
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getKuisId() string
        +setKuisId(string kuis_id) void
        +getPertanyaan() string
        +setPertanyaan(string pertanyaan) void
        +getImage() string
        +setImage(string image) void
        +getPilihan() array
        +setPilihan(array pilihan) void
        +getJawaban() string
        +setJawaban(string jawaban) void
        +getUrutan() int
        +setUrutan(int urutan) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class KuisAttempt {
        -int id
        -string kuis_id
        -int siswa_id
        -string token
        -datetime started_at
        -datetime ends_at
        -datetime submitted_at
        -string status
        -float score
        -int benar
        -int salah
        -int total_soal
        -array answers
        -datetime created_at
        -datetime updated_at
        +create() KuisAttempt
        +read() KuisAttempt
        +update() boolean
        +delete() boolean
        +getId() int
        +setId(int id) void
        +getKuisId() string
        +setKuisId(string kuis_id) void
        +getSiswaId() int
        +setSiswaId(int siswa_id) void
        +getToken() string
        +setToken(string token) void
        +getStartedAt() datetime
        +setStartedAt(datetime started_at) void
        +getEndsAt() datetime
        +setEndsAt(datetime ends_at) void
        +getSubmittedAt() datetime
        +setSubmittedAt(datetime submitted_at) void
        +getStatus() string
        +setStatus(string status) void
        +getScore() float
        +setScore(float score) void
        +getBenar() int
        +setBenar(int benar) void
        +getSalah() int
        +setSalah(int salah) void
        +getTotalSoal() int
        +setTotalSoal(int total_soal) void
        +getAnswers() array
        +setAnswers(array answers) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class HasilKuis {
        -int id
        -string kuis_id
        -int siswa_id
        -array jawaban
        -float nilai
        -int benar
        -int salah
        -datetime waktu_mulai
        -datetime waktu_selesai
        -datetime created_at
        -datetime updated_at
        +create() HasilKuis
        +read() HasilKuis
        +update() boolean
        +delete() boolean
        +getId() int
        +setId(int id) void
        +getKuisId() string
        +setKuisId(string kuis_id) void
        +getSiswaId() int
        +setSiswaId(int siswa_id) void
        +getJawaban() array
        +setJawaban(array jawaban) void
        +getNilai() float
        +setNilai(float nilai) void
        +getBenar() int
        +setBenar(int benar) void
        +getSalah() int
        +setSalah(int salah) void
        +getWaktuMulai() datetime
        +setWaktuMulai(datetime waktu_mulai) void
        +getWaktuSelesai() datetime
        +setWaktuSelesai(datetime waktu_selesai) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class PBL {
        -string id
        -string judul
        -string masalah
        -string tujuan_pembelajaran
        -string panduan
        -string referensi
        -string kelas
        -int jurusan_id
        -string status
        -date deadline
        -int created_by
        -datetime created_at
        -datetime updated_at
        +create() PBL
        +read() PBL
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getJudul() string
        +setJudul(string judul) void
        +getMasalah() string
        +setMasalah(string masalah) void
        +getTujuanPembelajaran() string
        +setTujuanPembelajaran(string tujuan_pembelajaran) void
        +getPanduan() string
        +setPanduan(string panduan) void
        +getReferensi() string
        +setReferensi(string referensi) void
        +getKelas() string
        +setKelas(string kelas) void
        +getJurusanId() int
        +setJurusanId(int jurusan_id) void
        +getStatus() string
        +setStatus(string status) void
        +getDeadline() date
        +setDeadline(date deadline) void
        +getCreatedBy() int
        +setCreatedBy(int created_by) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class PBLSintaks {
        -string id
        -string pbl_id
        -string judul
        -string instruksi
        -int urutan
        -datetime created_at
        -datetime updated_at
        +create() PBLSintaks
        +read() PBLSintaks
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getPblId() string
        +setPblId(string pbl_id) void
        +getJudul() string
        +setJudul(string judul) void
        +getInstruksi() string
        +setInstruksi(string instruksi) void
        +getUrutan() int
        +setUrutan(int urutan) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class PBLProgress {
        -int id
        -string pbl_id
        -string sintaks_id
        -string kelompok_id
        -string catatan
        -string file_path
        -string file_name
        -datetime submitted_at
        -datetime created_at
        -datetime updated_at
        +create() PBLProgress
        +read() PBLProgress
        +update() boolean
        +delete() boolean
        +getId() int
        +setId(int id) void
        +getPblId() string
        +setPblId(string pbl_id) void
        +getSintaksId() string
        +setSintaksId(string sintaks_id) void
        +getKelompokId() string
        +setKelompokId(string kelompok_id) void
        +getCatatan() string
        +setCatatan(string catatan) void
        +getFilePath() string
        +setFilePath(string file_path) void
        +getFileName() string
        +setFileName(string file_name) void
        +getSubmittedAt() datetime
        +setSubmittedAt(datetime submitted_at) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class PBLSubmission {
        -string id
        -string pbl_id
        -string kelompok_id
        -string file_name
        -string file_path
        -int file_size
        -string catatan
        -float nilai
        -string feedback
        -datetime submitted_at
        -datetime created_at
        -datetime updated_at
        +create() PBLSubmission
        +read() PBLSubmission
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getPblId() string
        +setPblId(string pbl_id) void
        +getKelompokId() string
        +setKelompokId(string kelompok_id) void
        +getFileName() string
        +setFileName(string file_name) void
        +getFilePath() string
        +setFilePath(string file_path) void
        +getFileSize() int
        +setFileSize(int file_size) void
        +getCatatan() string
        +setCatatan(string catatan) void
        +getNilai() float
        +setNilai(float nilai) void
        +getFeedback() string
        +setFeedback(string feedback) void
        +getSubmittedAt() datetime
        +setSubmittedAt(datetime submitted_at) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Kelompok {
        -string id
        -string pbl_id
        -string nama_kelompok
        -array anggota
        -datetime created_at
        -datetime updated_at
        +create() Kelompok
        +read() Kelompok
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getPblId() string
        +setPblId(string pbl_id) void
        +getNamaKelompok() string
        +setNamaKelompok(string nama_kelompok) void
        +getAnggota() array
        +setAnggota(array anggota) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Notifikasi {
        -string id
        -int user_id
        -string judul
        -string pesan
        -string tipe
        -boolean read
        -datetime created_at
        -datetime updated_at
        +create() Notifikasi
        +read() Notifikasi
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getUserId() int
        +setUserId(int user_id) void
        +getJudul() string
        +setJudul(string judul) void
        +getPesan() string
        +setPesan(string pesan) void
        +getTipe() string
        +setTipe(string tipe) void
        +getRead() boolean
        +setRead(boolean read) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    class Helpdesk {
        -string id
        -int siswa_id
        -string kategori
        -string judul
        -string pesan
        -string status
        -string balasan
        -datetime created_at
        -datetime updated_at
        +create() Helpdesk
        +read() Helpdesk
        +update() boolean
        +delete() boolean
        +getId() string
        +setId(string id) void
        +getSiswaId() int
        +setSiswaId(int siswa_id) void
        +getKategori() string
        +setKategori(string kategori) void
        +getJudul() string
        +setJudul(string judul) void
        +getPesan() string
        +setPesan(string pesan) void
        +getStatus() string
        +setStatus(string status) void
        +getBalasan() string
        +setBalasan(string balasan) void
        +getCreatedAt() datetime
        +getUpdatedAt() datetime
    }

    %% ============================================
    %% PIVOT TABLES (MANY-TO-MANY)
    %% ============================================

    class MateriKelas {
        <<Pivot Table>>
        -int id
        -string materi_id
        -int kelas_id
        -datetime created_at
        -datetime updated_at
    }

    class KuisKelas {
        <<Pivot Table>>
        -int id
        -string kuis_id
        -int kelas_id
        -datetime created_at
        -datetime updated_at
    }

    class PBLKelas {
        <<Pivot Table>>
        -int id
        -string pbl_id
        -int kelas_id
        -datetime created_at
        -datetime updated_at
    }

    %% ============================================
    %% RELATIONSHIPS
    %% ============================================

    %% User Relationships
    Jurusan "1" --> "*" User : memiliki
    Kelas "1" --> "*" User : memiliki siswa

    %% Kelas - Jurusan
    Jurusan "1" --> "*" Kelas : memiliki

    %% Materi Relationships
    User "1" --> "*" Materi : membuat
    Jurusan "1" --> "*" Materi : untuk
    Materi "*" --> "*" Kelas : melalui MateriKelas

    %% Kuis Relationships
    User "1" --> "*" Kuis : membuat
    Kuis "*" --> "*" Kelas : melalui KuisKelas
    Kuis "1" --> "*" Soal : memiliki
    Kuis "1" --> "*" KuisAttempt : memiliki
    Kuis "1" --> "*" HasilKuis : memiliki

    %% KuisAttempt & HasilKuis
    User "1" --> "*" KuisAttempt : mengerjakan
    User "1" --> "*" HasilKuis : mendapat

    %% PBL Relationships
    User "1" --> "*" PBL : membuat
    Jurusan "1" --> "*" PBL : untuk
    PBL "*" --> "*" Kelas : melalui PBLKelas
    PBL "1" --> "*" PBLSintaks : memiliki tahapan
    PBL "1" --> "*" Kelompok : memiliki
    PBL "1" --> "*" PBLSubmission : memiliki
    PBL "1" --> "*" PBLProgress : memiliki

    %% Kelompok Relationships
    Kelompok "1" --> "*" PBLSubmission : mengumpulkan
    Kelompok "1" --> "*" PBLProgress : mengerjakan

    %% PBLSintaks & PBLProgress
    PBLSintaks "1" --> "*" PBLProgress : memiliki

    %% Notifikasi & Helpdesk
    User "1" --> "*" Notifikasi : menerima
    User "1" --> "*" Helpdesk : membuat tiket
```

---

## Detail Class dengan Penjelasan

### 1. User (Pengguna)

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | int | Primary Key (auto increment) |
| name | string | Nama lengkap pengguna |
| email | string | Email unik untuk login |
| password | string | Password terenkripsi |
| role | string | Role: admin/guru/siswa |
| nisn | string | Nomor Induk Siswa Nasional (untuk siswa) |
| nis | string | Nomor Induk Siswa (untuk siswa) |
| nip | string | Nomor Induk Pegawai (untuk guru) |
| kelas | string | Nama kelas (deprecated) |
| kelas_id | int | Foreign Key ke Kelas |
| jurusan_id | int | Foreign Key ke Jurusan |
| kelas_diampu | string | Kelas yang diampu (untuk guru) |
| phone | string | Nomor telepon |
| address | string | Alamat |
| avatar | string | Path foto profil |
| is_active | boolean | Status aktif |

**Method CRUD:**
- `create()` - Membuat user baru
- `read()` - Membaca data user
- `update()` - Memperbarui data user
- `delete()` - Menghapus user

---

### 2. Jurusan

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: JUR-X) |
| nama | string | Nama jurusan |
| deskripsi | string | Deskripsi jurusan |

**Method CRUD:**
- `create()` - Membuat jurusan baru
- `read()` - Membaca data jurusan
- `update()` - Memperbarui data jurusan
- `delete()` - Menghapus jurusan

---

### 3. Kelas

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | int | Primary Key (auto increment) |
| nama | string | Nama kelas |
| tingkat | int | Tingkat kelas (10/11/12) |
| jurusan_id | int | Foreign Key ke Jurusan |

**Method CRUD:**
- `create()` - Membuat kelas baru
- `read()` - Membaca data kelas
- `update()` - Memperbarui data kelas
- `delete()` - Menghapus kelas

---

### 4. Materi

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: materi-X) |
| judul | string | Judul materi |
| deskripsi | string | Deskripsi materi |
| kelas | string | Target kelas |
| jurusan_id | int | Foreign Key ke Jurusan |
| file_name | string | Nama file |
| file_path | string | Path penyimpanan file |
| file_size | int | Ukuran file dalam bytes |
| status | string | Status: draft/published |
| created_by | int | Foreign Key ke User (guru) |

**Method CRUD:**
- `create()` - Membuat materi baru
- `read()` - Membaca data materi
- `update()` - Memperbarui data materi
- `delete()` - Menghapus materi

---

### 5. Kuis

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: kuis-X) |
| judul | string | Judul kuis |
| kelas | string | Target kelas |
| batas_waktu | int | Batas waktu dalam menit |
| status | string | Status: draft/published |
| created_by | int | Foreign Key ke User (guru) |

**Method CRUD:**
- `create()` - Membuat kuis baru
- `read()` - Membaca data kuis
- `update()` - Memperbarui data kuis
- `delete()` - Menghapus kuis

---

### 6. Soal

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: soal-X) |
| kuis_id | string | Foreign Key ke Kuis |
| pertanyaan | string | Teks pertanyaan |
| image | string | Path gambar (opsional) |
| pilihan | array/json | Array pilihan jawaban |
| jawaban | string | Kunci jawaban benar |
| urutan | int | Urutan soal |

**Method CRUD:**
- `create()` - Membuat soal baru
- `read()` - Membaca data soal
- `update()` - Memperbarui data soal
- `delete()` - Menghapus soal

---

### 7. KuisAttempt (Percobaan Kuis)

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | int | Primary Key (auto increment) |
| kuis_id | string | Foreign Key ke Kuis |
| siswa_id | int | Foreign Key ke User (siswa) |
| token | string | Token unik pengerjaan |
| started_at | datetime | Waktu mulai |
| ends_at | datetime | Waktu berakhir |
| submitted_at | datetime | Waktu submit |
| status | string | Status: started/submitted/completed |
| score | float | Nilai akhir |
| benar | int | Jumlah jawaban benar |
| salah | int | Jumlah jawaban salah |
| total_soal | int | Total soal |
| answers | array/json | Array jawaban siswa |

**Method CRUD:**
- `create()` - Membuat attempt baru
- `read()` - Membaca data attempt
- `update()` - Memperbarui data attempt
- `delete()` - Menghapus attempt

---

### 8. HasilKuis

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | int | Primary Key (auto increment) |
| kuis_id | string | Foreign Key ke Kuis |
| siswa_id | int | Foreign Key ke User (siswa) |
| jawaban | array/json | Array jawaban |
| nilai | float | Nilai akhir |
| benar | int | Jumlah jawaban benar |
| salah | int | Jumlah jawaban salah |
| waktu_mulai | datetime | Waktu mulai |
| waktu_selesai | datetime | Waktu selesai |

**Method CRUD:**
- `create()` - Membuat hasil kuis baru
- `read()` - Membaca data hasil kuis
- `update()` - Memperbarui data hasil kuis
- `delete()` - Menghapus hasil kuis

---

### 9. PBL (Project Based Learning)

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: pbl-X) |
| judul | string | Judul proyek |
| masalah | string | Deskripsi masalah |
| tujuan_pembelajaran | string | Tujuan pembelajaran |
| panduan | string | Panduan pengerjaan |
| referensi | string | Referensi |
| kelas | string | Target kelas |
| jurusan_id | int | Foreign Key ke Jurusan |
| status | string | Status: draft/published |
| deadline | date | Batas waktu pengumpulan |
| created_by | int | Foreign Key ke User (guru) |

**Method CRUD:**
- `create()` - Membuat PBL baru
- `read()` - Membaca data PBL
- `update()` - Memperbarui data PBL
- `delete()` - Menghapus PBL

---

### 10. PBLSintaks (Tahapan PBL)

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: sintaks-X) |
| pbl_id | string | Foreign Key ke PBL |
| judul | string | Judul tahapan |
| instruksi | string | Instruksi pengerjaan |
| urutan | int | Urutan tahapan |

**Method CRUD:**
- `create()` - Membuat sintaks baru
- `read()` - Membaca data sintaks
- `update()` - Memperbarui data sintaks
- `delete()` - Menghapus sintaks

---

### 11. PBLProgress (Progress per Tahapan)

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | int | Primary Key (auto increment) |
| pbl_id | string | Foreign Key ke PBL |
| sintaks_id | string | Foreign Key ke PBLSintaks |
| kelompok_id | string | Foreign Key ke Kelompok |
| catatan | string | Catatan pengerjaan |
| file_path | string | Path file |
| file_name | string | Nama file |
| submitted_at | datetime | Waktu submit |

**Method CRUD:**
- `create()` - Membuat progress baru
- `read()` - Membaca data progress
- `update()` - Memperbarui data progress
- `delete()` - Menghapus progress

---

### 12. PBLSubmission (Pengumpulan PBL Final)

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: submit-X) |
| pbl_id | string | Foreign Key ke PBL |
| kelompok_id | string | Foreign Key ke Kelompok |
| file_name | string | Nama file |
| file_path | string | Path file |
| file_size | int | Ukuran file |
| catatan | string | Catatan |
| nilai | float | Nilai dari guru |
| feedback | string | Feedback dari guru |
| submitted_at | datetime | Waktu submit |

**Method CRUD:**
- `create()` - Membuat submission baru
- `read()` - Membaca data submission
- `update()` - Memperbarui data submission
- `delete()` - Menghapus submission

---

### 13. Kelompok

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: kelompok-X) |
| pbl_id | string | Foreign Key ke PBL |
| nama_kelompok | string | Nama kelompok |
| anggota | array/json | Array ID anggota siswa |

**Method CRUD:**
- `create()` - Membuat kelompok baru
- `read()` - Membaca data kelompok
- `update()` - Memperbarui data kelompok
- `delete()` - Menghapus kelompok

---

### 14. Notifikasi

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: notif-X) |
| user_id | int | Foreign Key ke User |
| judul | string | Judul notifikasi |
| pesan | string | Isi pesan |
| tipe | string | Tipe notifikasi |
| read | boolean | Status sudah dibaca |

**Method CRUD:**
- `create()` - Membuat notifikasi baru
- `read()` - Membaca data notifikasi
- `update()` - Memperbarui data notifikasi
- `delete()` - Menghapus notifikasi

---

### 15. Helpdesk

| Atribut | Tipe Data | Keterangan |
|---------|-----------|------------|
| id | string | Primary Key (format: ticket-X) |
| siswa_id | int | Foreign Key ke User (siswa) |
| kategori | string | Kategori tiket |
| judul | string | Judul tiket |
| pesan | string | Isi pesan |
| status | string | Status: open/closed |
| balasan | string | Balasan dari admin |

**Method CRUD:**
- `create()` - Membuat tiket baru
- `read()` - Membaca data tiket
- `update()` - Memperbarui data tiket
- `delete()` - Menghapus tiket

---

## Kardinalitas Relasi

| Relasi | Tipe | Keterangan |
|--------|------|------------|
| Jurusan → User | 1:N | Satu jurusan memiliki banyak user |
| Jurusan → Kelas | 1:N | Satu jurusan memiliki banyak kelas |
| Kelas → User | 1:N | Satu kelas memiliki banyak siswa |
| Materi ↔ Kelas | N:M | Many-to-many melalui materi_kelas |
| Kuis ↔ Kelas | N:M | Many-to-many melalui kuis_kelas |
| PBL ↔ Kelas | N:M | Many-to-many melalui pbl_kelas |
| Kuis → Soal | 1:N | Satu kuis memiliki banyak soal |
| Kuis → KuisAttempt | 1:N | Satu kuis memiliki banyak attempt |
| User → KuisAttempt | 1:N | Satu siswa memiliki banyak attempt |
| Kuis → HasilKuis | 1:N | Satu kuis memiliki banyak hasil |
| PBL → PBLSintaks | 1:N | Satu PBL memiliki banyak tahapan |
| PBL → Kelompok | 1:N | Satu PBL memiliki banyak kelompok |
| Kelompok → PBLSubmission | 1:N | Satu kelompok memiliki banyak submission |
| Kelompok → PBLProgress | 1:N | Satu kelompok memiliki banyak progress |
| PBLSintaks → PBLProgress | 1:N | Satu sintaks memiliki banyak progress |
| User → Notifikasi | 1:N | Satu user memiliki banyak notifikasi |
| User → Helpdesk | 1:N | Satu siswa memiliki banyak tiket |

---

## Pivot Tables

### materi_kelas
- `id` (int) - Primary Key
- `materi_id` (string) - Foreign Key ke Materi
- `kelas_id` (int) - Foreign Key ke Kelas

### kuis_kelas
- `id` (int) - Primary Key
- `kuis_id` (string) - Foreign Key ke Kuis
- `kelas_id` (int) - Foreign Key ke Kelas

### pbl_kelas
- `id` (int) - Primary Key
- `pbl_id` (string) - Foreign Key ke PBL
- `kelas_id` (int) - Foreign Key ke Kelas

---

## Catatan Teknis

1. **Format Primary Key:**
   - User, Kelas, KuisAttempt, HasilKuis, PBLProgress: Auto increment (integer)
   - Jurusan: `JUR-{number}`
   - Materi: `materi-{number}`
   - Kuis: `kuis-{number}`
   - Soal: `soal-{number}`
   - PBL: `pbl-{number}`
   - PBLSintaks: `sintaks-{number}`
   - PBLSubmission: `submit-{number}`
   - Kelompok: `kelompok-{number}`
   - Notifikasi: `notif-{number}`
   - Helpdesk: `ticket-{number}`

2. **Method Getter & Setter:**
   - Setiap atribut memiliki method `get{AttributeName}()` dan `set{AttributeName}()` 
   - Contoh: `getName()`, `setName(string name)`
   - Getter untuk timestamps (`created_at`, `updated_at`) hanya read-only

3. **Framework Laravel:**
   - Method CRUD diimplementasikan melalui Eloquent ORM
   - `create()` → `Model::create()`
   - `read()` → `Model::find()`, `Model::all()`
   - `update()` → `$model->update()`
   - `delete()` → `$model->delete()`

---

*Dokumentasi Class Diagram - Sistem E-Learning Jagat Kawruh*
*Dibuat untuk keperluan Sidang Skripsi*
