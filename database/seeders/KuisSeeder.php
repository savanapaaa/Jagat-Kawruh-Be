<?php

namespace Database\Seeders;

use App\Models\Kuis;
use App\Models\Soal;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KuisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Kuis 1: Algoritma
        $kuis1 = Kuis::create([
            'judul' => 'Kuis Algoritma Dasar',
            'kelas' => ['X', 'XI'],
            'batas_waktu' => 30,
            'status' => 'Aktif',
            'created_by' => 2 // Guru
        ]);

        Soal::create([
            'kuis_id' => $kuis1->id,
            'pertanyaan' => 'Apa itu algoritma?',
            'pilihan' => [
                'A' => 'Langkah-langkah sistematis untuk menyelesaikan masalah',
                'B' => 'Bahasa pemrograman',
                'C' => 'Kode program',
                'D' => 'Syntax',
                'E' => 'Compiler'
            ],
            'jawaban' => 'A',
            'urutan' => 1
        ]);

        Soal::create([
            'kuis_id' => $kuis1->id,
            'pertanyaan' => 'Struktur data yang menggunakan prinsip LIFO adalah?',
            'pilihan' => [
                'A' => 'Queue',
                'B' => 'Stack',
                'C' => 'Array',
                'D' => 'Linked List',
                'E' => 'Tree'
            ],
            'jawaban' => 'B',
            'urutan' => 2
        ]);

        Soal::create([
            'kuis_id' => $kuis1->id,
            'pertanyaan' => 'Big O notation O(n) menunjukkan kompleksitas waktu?',
            'pilihan' => [
                'A' => 'Konstan',
                'B' => 'Linear',
                'C' => 'Logaritmik',
                'D' => 'Kuadratik',
                'E' => 'Eksponensial'
            ],
            'jawaban' => 'B',
            'urutan' => 3
        ]);

        // Kuis 2: Database
        $kuis2 = Kuis::create([
            'judul' => 'Kuis Database MySQL',
            'kelas' => ['XI', 'XII'],
            'batas_waktu' => 45,
            'status' => 'Aktif',
            'created_by' => 2
        ]);

        Soal::create([
            'kuis_id' => $kuis2->id,
            'pertanyaan' => 'Perintah SQL untuk mengambil data dari tabel adalah?',
            'pilihan' => [
                'A' => 'INSERT',
                'B' => 'UPDATE',
                'C' => 'SELECT',
                'D' => 'DELETE',
                'E' => 'CREATE'
            ],
            'jawaban' => 'C',
            'urutan' => 1
        ]);

        Soal::create([
            'kuis_id' => $kuis2->id,
            'pertanyaan' => 'Primary key berfungsi untuk?',
            'pilihan' => [
                'A' => 'Menghubungkan tabel',
                'B' => 'Mengidentifikasi unik setiap record',
                'C' => 'Menyimpan data',
                'D' => 'Mengurutkan data',
                'E' => 'Menghapus data'
            ],
            'jawaban' => 'B',
            'urutan' => 2
        ]);

        // Kuis 3: Draft (belum aktif)
        Kuis::create([
            'judul' => 'Kuis OOP (Draft)',
            'kelas' => ['XII'],
            'batas_waktu' => 60,
            'status' => 'Draft',
            'created_by' => 2
        ]);
    }
}
