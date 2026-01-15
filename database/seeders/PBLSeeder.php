<?php

namespace Database\Seeders;

use App\Models\PBL;
use App\Models\Kelompok;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PBLSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // PBL 1 - RPL
        $pbl1 = PBL::create([
            'judul' => 'Aplikasi Perpustakaan Digital',
            'masalah' => 'Perpustakaan sekolah masih menggunakan sistem manual dalam pengelolaan peminjaman buku',
            'tujuan_pembelajaran' => 'Siswa mampu membuat aplikasi web CRUD sederhana dengan PHP dan MySQL',
            'panduan' => '1. Analisis kebutuhan sistem\n2. Buat ERD database\n3. Implementasi backend API\n4. Buat UI menggunakan Bootstrap\n5. Testing dan deploy',
            'referensi' => 'https://laravel.com/docs, https://getbootstrap.com',
            'kelas' => 'XI',
            'jurusan_id' => 'JUR-1', // RPL
            'status' => 'Aktif',
            'deadline' => now()->addDays(30),
            'created_by' => 2,
        ]);

        // Kelompok untuk PBL 1
        Kelompok::create([
            'pbl_id' => $pbl1->id,
            'nama_kelompok' => 'Kelompok A1',
            'anggota' => ['siswa-3', 'siswa-4', 'siswa-5']
        ]);

        Kelompok::create([
            'pbl_id' => $pbl1->id,
            'nama_kelompok' => 'Kelompok A2',
            'anggota' => ['siswa-6', 'siswa-7']
        ]);

        // PBL 2 - TKJ
        $pbl2 = PBL::create([
            'judul' => 'Konfigurasi Router MikroTik',
            'masalah' => 'Lab jaringan sekolah membutuhkan konfigurasi router yang optimal untuk pembelajaran',
            'tujuan_pembelajaran' => 'Siswa mampu melakukan konfigurasi dasar router MikroTik dan monitoring jaringan',
            'panduan' => '1. Setup router fisik/GNS3\n2. Konfigurasi IP address\n3. Setup DHCP server\n4. Konfigurasi firewall\n5. Monitoring traffic',
            'referensi' => 'https://help.mikrotik.com, https://wiki.mikrotik.com',
            'kelas' => 'XII',
            'jurusan_id' => 'JUR-2', // TKJ
            'status' => 'Aktif',
            'deadline' => now()->addDays(21),
            'created_by' => 2,
        ]);

        // Kelompok untuk PBL 2
        Kelompok::create([
            'pbl_id' => $pbl2->id,
            'nama_kelompok' => 'Network Warriors',
            'anggota' => ['siswa-1', 'siswa-2']
        ]);

        // PBL 3 - Multimedia
        PBL::create([
            'judul' => 'Video Promosi Sekolah',
            'masalah' => 'Sekolah membutuhkan video promosi untuk PPDB tahun depan',
            'tujuan_pembelajaran' => 'Siswa mampu membuat video promosi profesional menggunakan Adobe Premiere Pro',
            'panduan' => '1. Perencanaan konten (storyboard)\n2. Shooting video\n3. Editing dengan Premiere Pro\n4. Color grading\n5. Export dan publikasi',
            'referensi' => 'https://helpx.adobe.com/premiere-pro, YouTube: Film Riot',
            'kelas' => 'XII',
            'jurusan_id' => 'JUR-3', // MM
            'status' => 'Draft',
            'deadline' => now()->addDays(45),
            'created_by' => 2,
        ]);
    }
}
