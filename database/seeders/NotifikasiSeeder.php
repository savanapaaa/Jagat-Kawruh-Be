<?php

namespace Database\Seeders;

use App\Models\Notifikasi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotifikasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Broadcast notifikasi (untuk semua user)
        Notifikasi::create([
            'user_id' => null,
            'judul' => 'Selamat Datang di Jagat Kawruh',
            'pesan' => 'Selamat datang di sistem pembelajaran Jagat Kawruh. Silakan explore fitur-fitur yang tersedia!',
            'tipe' => 'pengumuman',
            'read' => false
        ]);

        Notifikasi::create([
            'user_id' => null,
            'judul' => 'Libur Semester',
            'pesan' => 'Libur semester akan dimulai tanggal 20 Juni 2026. Selamat berlibur!',
            'tipe' => 'pengumuman',
            'read' => false
        ]);

        // Notifikasi untuk siswa tertentu (user_id = 3 adalah siswa pertama)
        Notifikasi::create([
            'user_id' => 3,
            'judul' => 'Kuis Baru Tersedia',
            'pesan' => 'Ada kuis baru: "Algoritma Dasar". Deadline pengerjaan 3 hari lagi.',
            'tipe' => 'kuis',
            'read' => false
        ]);

        Notifikasi::create([
            'user_id' => 3,
            'judul' => 'Materi Baru',
            'pesan' => 'Materi "Pemrograman Dasar - Algoritma" sudah tersedia. Silakan download!',
            'tipe' => 'materi',
            'read' => false
        ]);

        Notifikasi::create([
            'user_id' => 3,
            'judul' => 'Deadline PBL',
            'pesan' => 'Deadline project "Aplikasi Perpustakaan Digital" tinggal 5 hari lagi!',
            'tipe' => 'pbl',
            'read' => true
        ]);
    }
}
