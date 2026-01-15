<?php

namespace Database\Seeders;

use App\Models\Materi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MateriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Materi::create([
            'judul' => 'Pemrograman Dasar - Pengenalan Algoritma',
            'deskripsi' => 'Materi pengenalan algoritma dan flowchart untuk kelas X RPL',
            'file_name' => null, // Bisa di-upload manual
            'file_path' => null,
            'file_size' => null,
            'kelas' => ['X', 'XI'],
            'jurusan_id' => 'JUR-1', // RPL
            'status' => 'Published',
            'created_by' => 2, // Guru
        ]);

        Materi::create([
            'judul' => 'Database MySQL - ERD dan Normalisasi',
            'deskripsi' => 'Materi tentang Entity Relationship Diagram dan normalisasi database',
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'kelas' => ['XI'],
            'jurusan_id' => 'JUR-1', // RPL
            'status' => 'Published',
            'created_by' => 2,
        ]);

        Materi::create([
            'judul' => 'Teknik Jaringan - OSI Layer',
            'deskripsi' => 'Penjelasan lengkap tentang 7 layer OSI model',
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'kelas' => ['X', 'XI', 'XII'],
            'jurusan_id' => 'JUR-2', // TKJ
            'status' => 'Published',
            'created_by' => 2,
        ]);

        Materi::create([
            'judul' => 'Multimedia - Adobe Photoshop Basic',
            'deskripsi' => 'Tutorial dasar penggunaan Adobe Photoshop untuk desain grafis',
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'kelas' => ['X'],
            'jurusan_id' => 'JUR-3', // MM
            'status' => 'Draft',
            'created_by' => 2,
        ]);
    }
}
