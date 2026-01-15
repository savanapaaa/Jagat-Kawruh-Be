<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin Utama',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create guru user
        User::create([
            'name' => 'Guru Contoh',
            'email' => 'guru@example.com',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'nip' => '1234567890',
            'phone' => '08123456789',
            'is_active' => true,
        ]);

        // Create siswa user
        User::create([
            'name' => 'Siswa Contoh',
            'email' => 'siswa@example.com',
            'password' => Hash::make('password'),
            'role' => 'siswa',
            'nisn' => '0001234567',
            'phone' => '08198765432',
            'is_active' => true,
        ]);

        // Seed jurusan
        $this->call(JurusanSeeder::class);

        // Seed kelas
        $this->call(KelasSeeder::class);
        
        // Seed siswa
        $this->call(SiswaSeeder::class);
        
        // Seed kuis
        $this->call(KuisSeeder::class);
        
        // Seed materi
        $this->call(MateriSeeder::class);
        
        // Seed PBL
        $this->call(PBLSeeder::class);
        
        // Seed notifikasi
        $this->call(NotifikasiSeeder::class);
        
        // Seed helpdesk
        $this->call(HelpdeskSeeder::class);
    }
}
