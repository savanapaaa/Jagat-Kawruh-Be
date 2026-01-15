<?php

namespace Database\Seeders;

use App\Models\Jurusan;
use App\Models\Kelas;
use Illuminate\Database\Seeder;

class KelasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $map = Jurusan::query()->pluck('id', 'nama');

        $data = [
            // X RPL 1, X RPL 2
            ['nama' => 'X RPL 1', 'tingkat' => 'X', 'jurusan_id' => $map['RPL'] ?? null],
            ['nama' => 'X RPL 2', 'tingkat' => 'X', 'jurusan_id' => $map['RPL'] ?? null],

            // XI TKJ 1, XI TKJ 2
            ['nama' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan_id' => $map['TKJ'] ?? null],
            ['nama' => 'XI TKJ 2', 'tingkat' => 'XI', 'jurusan_id' => $map['TKJ'] ?? null],

            // XII MM 1
            ['nama' => 'XII MM 1', 'tingkat' => 'XII', 'jurusan_id' => $map['MM'] ?? null],
        ];

        foreach ($data as $row) {
            if (empty($row['jurusan_id'])) {
                continue;
            }

            Kelas::firstOrCreate(
                ['nama' => $row['nama']],
                ['tingkat' => $row['tingkat'], 'jurusan_id' => $row['jurusan_id']]
            );
        }
    }
}
