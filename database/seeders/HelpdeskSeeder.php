<?php

namespace Database\Seeders;

use App\Models\Helpdesk;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HelpdeskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ticket dari siswa (user_id = 3 adalah siswa pertama)
        Helpdesk::create([
            'siswa_id' => 3,
            'kategori' => 'Akun',
            'judul' => 'Lupa Password',
            'pesan' => 'Saya lupa password akun saya. Mohon bantuannya untuk reset password.',
            'status' => 'solved',
            'balasan' => 'Password sudah direset. Silakan login dengan password: siswa123'
        ]);

        Helpdesk::create([
            'siswa_id' => 3,
            'kategori' => 'Kuis',
            'judul' => 'Error Submit Kuis',
            'pesan' => 'Saat submit kuis Algoritma, muncul error. Mohon dicek.',
            'status' => 'progress',
            'balasan' => 'Sedang kami cek. Terima kasih laporannya.'
        ]);

        Helpdesk::create([
            'siswa_id' => 4,
            'kategori' => 'Materi',
            'judul' => 'File Materi Tidak Bisa Didownload',
            'pesan' => 'File PDF materi "Database MySQL" tidak bisa didownload. Link rusak.',
            'status' => 'open',
            'balasan' => null
        ]);

        Helpdesk::create([
            'siswa_id' => 5,
            'kategori' => 'PBL',
            'judul' => 'Kelompok Belum Dibuat',
            'pesan' => 'Untuk project PBL "Aplikasi Perpustakaan", kelompok saya belum terbentuk. Mohon bantuannya.',
            'status' => 'open',
            'balasan' => null
        ]);

        Helpdesk::create([
            'siswa_id' => 6,
            'kategori' => 'Lainnya',
            'judul' => 'Request Fitur Export Nilai',
            'pesan' => 'Apakah bisa ditambahkan fitur export nilai ke PDF atau Excel?',
            'status' => 'open',
            'balasan' => null
        ]);
    }
}
