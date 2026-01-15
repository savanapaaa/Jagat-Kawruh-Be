<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JurusanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jurusanList = [
            [
                'nama' => 'RPL',
                'deskripsi' => 'Rekayasa Perangkat Lunak - Jurusan yang mempelajari pengembangan software dan aplikasi'
            ],
            [
                'nama' => 'TKJ',
                'deskripsi' => 'Teknik Komputer dan Jaringan - Jurusan yang mempelajari jaringan komputer dan infrastruktur IT'
            ],
            [
                'nama' => 'MM',
                'deskripsi' => 'Multimedia - Jurusan yang mempelajari desain grafis, video editing, dan konten digital'
            ],
            [
                'nama' => 'AKL',
                'deskripsi' => 'Akuntansi dan Keuangan Lembaga - Jurusan yang mempelajari akuntansi dan manajemen keuangan'
            ],
        ];

        foreach ($jurusanList as $jurusan) {
            Jurusan::create($jurusan);
        }
    }
}
