<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JurusanController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\KuisController;
use App\Http\Controllers\MateriController;
use App\Http\Controllers\PBLController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Guru\SiswaController as GuruSiswaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/login', [AuthController::class, 'login']); // Frontend pakai /auth/login
Route::post('/auth/register', [AuthController::class, 'register']); // Frontend pakai /auth/register

// Auth routes sesuai spec baru (untuk get current user)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // Legacy auth routes (backward compatibility)
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // ===== PROFILE ROUTES =====
    // Semua role boleh akses profil mereka sendiri
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // ===== JURUSAN ROUTES =====
    // Hanya GURU & ADMIN yang bisa akses
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::get('/jurusan', [JurusanController::class, 'index']);
        Route::post('/jurusan', [JurusanController::class, 'store']);
        Route::get('/jurusan/{id}', [JurusanController::class, 'show']);
        Route::put('/jurusan/{id}', [JurusanController::class, 'update']);
        Route::delete('/jurusan/{id}', [JurusanController::class, 'destroy']);
    });

    // ===== SISWA ROUTES (Data Master) =====
    // Hanya GURU & ADMIN yang bisa akses data master siswa
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::get('/siswa', [SiswaController::class, 'index']);
        Route::post('/siswa', [SiswaController::class, 'store']);
        Route::post('/siswa/import', [SiswaController::class, 'import']);
        Route::get('/siswa/{id}', [SiswaController::class, 'show']);
        Route::put('/siswa/{id}', [SiswaController::class, 'update']);
        Route::delete('/siswa/{id}', [SiswaController::class, 'destroy']);
    });

    // ===== KUIS ROUTES =====
    // Siswa boleh READ kuis yang aktif & submit jawaban
    Route::get('/kuis', [KuisController::class, 'index']);
    Route::get('/kuis/{id}', [KuisController::class, 'show']);
    Route::post('/kuis/{id}/submit', [KuisController::class, 'submit']);
    
    // Create/Update/Delete hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::post('/kuis', [KuisController::class, 'store']);
        Route::put('/kuis/{id}', [KuisController::class, 'update']);
        Route::delete('/kuis/{id}', [KuisController::class, 'destroy']);
        Route::post('/kuis/{id}/soal-image', [KuisController::class, 'uploadSoalImage']);
        Route::get('/kuis/{id}/nilai', [KuisController::class, 'getNilai']);
    });

    // ===== MATERI ROUTES =====
    // Siswa boleh READ materi yang dipublikasikan & download
    Route::get('/materi', [MateriController::class, 'index']);
    Route::get('/materi/{id}', [MateriController::class, 'show']);
    Route::get('/materi/{id}/download', [MateriController::class, 'download']);
    
    // Create/Update/Delete hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::post('/materi', [MateriController::class, 'store']);
        Route::put('/materi/{id}', [MateriController::class, 'update']);
        Route::delete('/materi/{id}', [MateriController::class, 'destroy']);
    });

    // ===== PBL ROUTES =====
    // Semua role boleh READ PBL (siswa dibatasi di controller: hanya Aktif & sesuai kelas/jurusan)
    Route::get('/pbl', [PBLController::class, 'index']);
    Route::get('/pbl/{id}', [PBLController::class, 'show']);
    Route::get('/pbl/{id}/sintaks', [PBLController::class, 'getSintaks']);

    // Siswa boleh submit PBL
    Route::post('/pbl/{id}/submit', [PBLController::class, 'submit']);
    
    // CRUD PBL hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::post('/pbl', [PBLController::class, 'store']);
        Route::put('/pbl/{id}', [PBLController::class, 'update']);
        Route::delete('/pbl/{id}', [PBLController::class, 'destroy']);

        // PBL Sintaks (step-by-step)
        Route::post('/pbl/{id}/sintaks', [PBLController::class, 'createSintaks']);
        Route::put('/pbl/{id}/sintaks/{sintaksId}', [PBLController::class, 'updateSintaks']);
        Route::delete('/pbl/{id}/sintaks/{sintaksId}', [PBLController::class, 'destroySintaks']);
        
        // Kelompok management
        Route::get('/pbl/{id}/kelompok', [PBLController::class, 'getKelompok']);
        Route::post('/pbl/{id}/kelompok', [PBLController::class, 'createKelompok']);
        
        // Get submissions & nilai
        Route::get('/pbl/{id}/submissions', [PBLController::class, 'getSubmissions']);
        Route::put('/pbl/submissions/{id}/nilai', [PBLController::class, 'nilaiSubmission']);
    });

    // ===== NILAI ROUTES =====
    // Siswa lihat nilai sendiri, guru bisa lihat semua & filter by kelas
    Route::get('/nilai', [NilaiController::class, 'index']);
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::get('/nilai/kelas/{kelas}', [NilaiController::class, 'getByKelas']);
    });

    // ===== NOTIFIKASI ROUTES =====
    // Semua role boleh akses notifikasi mereka
    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::put('/notifikasi/{id}/read', [NotifikasiController::class, 'markAsRead']);
    Route::delete('/notifikasi/{id}', [NotifikasiController::class, 'destroy']);
    // Create hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::post('/notifikasi', [NotifikasiController::class, 'store']);
    });

    // ===== HELPDESK ROUTES =====
    // Semua role boleh akses helpdesk (siswa create ticket, guru respond)
    Route::get('/helpdesk', [HelpdeskController::class, 'index']);
    Route::get('/helpdesk/{id}', [HelpdeskController::class, 'show']);
    Route::post('/helpdesk', [HelpdeskController::class, 'store']);
    Route::delete('/helpdesk/{id}', [HelpdeskController::class, 'destroy']);
    // Update status hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::put('/helpdesk/{id}/status', [HelpdeskController::class, 'updateStatus']);
    });

    // ===== SISWA ROUTES (Data Master) =====
    // Hanya GURU & ADMIN yang bisa akses data master siswa
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::get('/siswa', [SiswaController::class, 'index']);
        Route::post('/siswa', [SiswaController::class, 'store']);
        Route::post('/siswa/import', [SiswaController::class, 'import']);
        Route::get('/siswa/{id}', [SiswaController::class, 'show']);
        Route::put('/siswa/{id}', [SiswaController::class, 'update']);
        Route::delete('/siswa/{id}', [SiswaController::class, 'destroy']);
    });

    // ===== ADMIN ROUTES =====
    // Hanya ADMIN yang bisa akses user management
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
        Route::patch('/users/{id}/toggle-status', [AdminUserController::class, 'toggleStatus']);
    });

    // ===== GURU ROUTES (Data Master) =====
    // Hanya ADMIN yang bisa manage data master guru
    // NOTE: pakai whereNumber('id') supaya tidak bentrok dengan /guru/siswa
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/guru', [GuruController::class, 'index']);
        Route::post('/guru', [GuruController::class, 'store']);
        Route::put('/guru/{id}', [GuruController::class, 'update'])->whereNumber('id');
        Route::delete('/guru/{id}', [GuruController::class, 'destroy'])->whereNumber('id');
    });

    // ===== KELAS ROUTES (Data Master) =====
    // Hanya ADMIN yang bisa manage data master kelas
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/kelas', [KelasController::class, 'index']);
        Route::post('/kelas', [KelasController::class, 'store']);
        Route::put('/kelas/{id}', [KelasController::class, 'update'])->whereNumber('id');
        Route::delete('/kelas/{id}', [KelasController::class, 'destroy'])->whereNumber('id');
    });

    // ===== GURU NAMESPACE ROUTES =====
    // Admin dan Guru bisa akses siswa management via /guru prefix
    Route::middleware(['role:admin,guru'])->prefix('guru')->group(function () {
        Route::get('/siswa', [GuruSiswaController::class, 'index']);
        Route::post('/siswa', [GuruSiswaController::class, 'store']);
        Route::get('/siswa/{id}', [GuruSiswaController::class, 'show']);
        Route::put('/siswa/{id}', [GuruSiswaController::class, 'update']);
        Route::delete('/siswa/{id}', [GuruSiswaController::class, 'destroy']);
        Route::patch('/siswa/{id}/toggle-status', [GuruSiswaController::class, 'toggleStatus']);
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint tidak ditemukan'
    ], 404);
});
