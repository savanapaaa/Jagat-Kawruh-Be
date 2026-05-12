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
use App\Http\Controllers\MateriSubmissionController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\PBLJobdeskController;
use App\Http\Controllers\PBLKontribusiController;
use App\Http\Controllers\PBLNilaiIndividuController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Guru\SiswaController as GuruSiswaController;
use App\Http\Controllers\PBLProgressController;

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
    // Siswa bisa akses data dirinya sendiri
    Route::get('/siswa/me', [SiswaController::class, 'showSelf']);
    
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
    Route::post('/kuis/{id}/submit', [KuisController::class, 'submit']); // Legacy endpoint
    
    // ===== KUIS ATTEMPT ROUTES (Sistem pengerjaan kuis) =====
    // Semua role bisa akses (validasi di controller)
    Route::get('/kuis/{kuisId}/attempts', [\App\Http\Controllers\KuisAttemptController::class, 'index']);
    Route::post('/kuis/{kuisId}/attempts/start', [\App\Http\Controllers\KuisAttemptController::class, 'start']);
    Route::get('/kuis/{kuisId}/attempts/{attemptId}', [\App\Http\Controllers\KuisAttemptController::class, 'show']);
    Route::get('/kuis/{kuisId}/attempts/{attemptId}/questions', [\App\Http\Controllers\KuisAttemptController::class, 'getQuestions']);
    Route::put('/kuis/{kuisId}/attempts/{attemptId}/answers', [\App\Http\Controllers\KuisAttemptController::class, 'saveAnswers']);
    Route::post('/kuis/{kuisId}/attempts/{attemptId}/submit', [\App\Http\Controllers\KuisAttemptController::class, 'submit']);
    
    // Create/Update/Delete hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::post('/kuis', [KuisController::class, 'store']);
        Route::put('/kuis/{id}', [KuisController::class, 'update']);
        Route::delete('/kuis/{id}', [KuisController::class, 'destroy']);
        Route::post('/kuis/{id}/import-soal', [KuisController::class, 'importSoal']);
        Route::post('/kuis/{id}/soal-image', [KuisController::class, 'uploadSoalImage']);
        Route::get('/kuis/{id}/nilai', [KuisController::class, 'getNilai']);
        Route::post('/kuis/{kuisId}/attempts/{attemptId}/approve-retake', [\App\Http\Controllers\KuisAttemptController::class, 'approveRetake']);
    });

    // ===== MATERI ROUTES =====
    // Siswa boleh READ materi yang dipublikasikan & download
    Route::get('/materi', [MateriController::class, 'index']);
    Route::get('/materi/{id}', [MateriController::class, 'show']);
    Route::get('/materi/{id}/download', [MateriController::class, 'download']);

    // Tugas materi (individual submission)
    Route::middleware(['role:siswa'])->group(function () {
        Route::post('/materi/{materiId}/submit', [MateriSubmissionController::class, 'submit']);
        Route::get('/materi/{materiId}/submission', [MateriSubmissionController::class, 'getMine']);
    });
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::get('/materi/{materiId}/submissions', [MateriSubmissionController::class, 'getByMateri']);
        Route::put('/materi/submissions/{submissionId}/nilai', [MateriSubmissionController::class, 'nilai']);
    });
    Route::get('/materi/submissions/{submissionId}/download', [MateriSubmissionController::class, 'download']);
    
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
    Route::get('/pbl/{id}/leaderboard', [PBLController::class, 'leaderboard']);
    Route::get('/pbl/{id}/sintaks', [PBLController::class, 'getSintaks']);
    Route::get('/pbl/{id}/kelompok', [PBLController::class, 'getKelompok']); // Siswa perlu akses ini
    Route::get('/pbl/{pblId}/kelompok/{kelompokId}/jobdesk', [PBLJobdeskController::class, 'show']);
    // Nilai individu per anggota kelompok (siswa boleh baca nilainya sendiri; aturan di controller)
    Route::get('/pbl/{pblId}/kelompok/{kelompokId}/nilai-individu', [PBLNilaiIndividuController::class, 'show']);

    // Siswa boleh submit PBL
    Route::post('/pbl/{id}/submit', [PBLController::class, 'submit']);

    // Download file submission PBL (guru/admin pemilik PBL, atau siswa anggota kelompok)
    Route::get('/pbl/submissions/{id}/download', [PBLController::class, 'downloadSubmission']);
    
    // ===== PBL PROGRESS ROUTES (per sintaks/tahapan) =====
    // Semua role bisa lihat progress
    Route::get('/pbl/{pblId}/progress', [PBLProgressController::class, 'index']);
    Route::get('/pbl/{pblId}/sintaks/{sintaksId}/progress', [PBLProgressController::class, 'show']);
    Route::get('/pbl/{pblId}/sintaks/{sintaksId}/kontribusi', [PBLKontribusiController::class, 'getMine']);
    // Siswa submit progress per sintaks
    Route::post('/pbl/{pblId}/sintaks/{sintaksId}/progress', [PBLProgressController::class, 'store']);
    Route::post('/pbl/{pblId}/sintaks/{sintaksId}/kontribusi', [PBLKontribusiController::class, 'storeMine']);
    Route::delete('/pbl/{pblId}/sintaks/{sintaksId}/progress', [PBLProgressController::class, 'destroy']);
    
    // CRUD PBL hanya untuk GURU & ADMIN
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::post('/pbl', [PBLController::class, 'store']);
        Route::put('/pbl/{id}', [PBLController::class, 'update']);
        Route::delete('/pbl/{id}', [PBLController::class, 'destroy']);

        // PBL Sintaks (step-by-step)
        Route::post('/pbl/{id}/sintaks', [PBLController::class, 'createSintaks']);
        Route::put('/pbl/{id}/sintaks/{sintaksId}', [PBLController::class, 'updateSintaks']);
        Route::delete('/pbl/{id}/sintaks/{sintaksId}', [PBLController::class, 'destroySintaks']);
        
        // Kelompok management (create/update/delete - guru/admin only)
        Route::post('/pbl/{id}/kelompok', [PBLController::class, 'createKelompok']);
        Route::put('/pbl/{id}/kelompok/{kelompokId}', [PBLController::class, 'updateKelompok']);
        Route::delete('/pbl/{id}/kelompok/{kelompokId}', [PBLController::class, 'deleteKelompok']);
        Route::put('/pbl/{pblId}/kelompok/{kelompokId}/jobdesk', [PBLJobdeskController::class, 'update']);
        Route::get('/pbl/{pblId}/kelompok/{kelompokId}/kontribusi', [PBLKontribusiController::class, 'indexByKelompok']);

        // Nilai individu per anggota kelompok (update khusus guru/admin)
        Route::put('/pbl/{pblId}/kelompok/{kelompokId}/nilai-individu', [PBLNilaiIndividuController::class, 'update']);
        
        // Get submissions & nilai
        Route::get('/pbl/{id}/submissions', [PBLController::class, 'getSubmissions']);
        Route::put('/pbl/submissions/{id}/nilai', [PBLController::class, 'nilaiSubmission']);
    });

    // ===== NILAI ROUTES =====
    // Siswa lihat nilai sendiri, guru bisa lihat semua & filter by kelas
    Route::get('/nilai', [NilaiController::class, 'index']);
    Route::middleware(['role:admin,guru'])->group(function () {
        Route::get('/nilai/export', [NilaiController::class, 'exportExcel']);
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
    // Semua role boleh READ daftar kelas
    Route::get('/kelas', [KelasController::class, 'index']);
    
    // Hanya ADMIN yang bisa manage (create/update/delete) kelas
    Route::middleware(['role:admin'])->group(function () {
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
