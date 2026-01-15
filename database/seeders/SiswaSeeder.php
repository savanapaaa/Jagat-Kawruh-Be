<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SiswaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $siswaList = [
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'ahmad@student.sch.id',
                'nis' => '12345',
                'kelas' => 'XII',
                'jurusan_id' => 'JUR-1', // RPL
            ],
            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti@student.sch.id',
                'nis' => '12346',
                'kelas' => 'XII',
                'jurusan_id' => 'JUR-1', // RPL
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@student.sch.id',
                'nis' => '12347',
                'kelas' => 'XI',
                'jurusan_id' => 'JUR-2', // TKJ
            ],
            [
                'name' => 'Dewi Lestari',
                'email' => 'dewi@student.sch.id',
                'nis' => '12348',
                'kelas' => 'XI',
                'jurusan_id' => 'JUR-3', // MM
            ],
            [
                'name' => 'Rizki Pratama',
                'email' => 'rizki@student.sch.id',
                'nis' => '12349',
                'kelas' => 'X',
                'jurusan_id' => 'JUR-1', // RPL
            ],
            [
                'name' => 'Maya Safitri',
                'email' => 'maya@student.sch.id',
                'nis' => '12350',
                'kelas' => 'X',
                'jurusan_id' => 'JUR-4', // AKL
            ],
        ];

        foreach ($siswaList as $siswa) {
            User::create([
                'name' => $siswa['name'],
                'email' => $siswa['email'],
                'password' => Hash::make('password'),
                'role' => 'siswa',
                'nis' => $siswa['nis'],
                'kelas' => $siswa['kelas'],
                'jurusan_id' => $siswa['jurusan_id'],
                'is_active' => true
            ]);
        }
    }
}
