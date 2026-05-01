<?php

namespace App\Http\Controllers;

use App\Models\Kuis;
use App\Models\KuisAttempt;
use App\Models\Soal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KuisAttemptController extends Controller
{
    /**
     * Normalize kuis ID - support both numeric ID (26) and full ID (kuis-26)
     * Database stores as "kuis-XX" format
     */
    private function normalizeKuisId(string $kuisId): string
    {
        // If already in kuis-XX format, return as-is
        if (preg_match('/^kuis-\d+$/i', $kuisId)) {
            return strtolower($kuisId); // normalize case
        }
        
        // If numeric only, prepend "kuis-"
        if (preg_match('/^\d+$/', $kuisId)) {
            return 'kuis-' . $kuisId;
        }
        
        // Return as-is for other formats
        return $kuisId;
    }

    /**
     * Start a new attempt for a kuis
     * POST /api/kuis/{kuisId}/attempts/start
     * 
     * Validasi:
     * - Kuis harus aktif
     * - Siswa harus punya akses (kelas relation)
     * - Belum ada attempt aktif (in_progress)
     */
    public function start(Request $request, string $kuisId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            // Validasi role siswa
            if ($user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang bisa mengerjakan kuis'
                ], 403);
            }

            // Cari kuis
            $kuis = Kuis::with('kelasRelation')->find($normalizedId);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan',
                    'debug' => [
                        'requested_id' => $kuisId,
                        'normalized_id' => $normalizedId,
                    ]
                ], 404);
            }

            // Validasi kuis aktif
            if ($kuis->status !== 'Aktif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak aktif',
                    'debug' => [
                        'kuis_id' => $kuis->id,
                        'status' => $kuis->status,
                    ]
                ], 400);
            }

            // Validasi akses kelas (siswa harus di kelas yang ada di pivot)
            $kelasIds = $kuis->kelasRelation->pluck('id')->toArray();
            
            // Debug: cek kelas_id siswa
            if (!$user->kelas_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa belum memiliki kelas',
                    'debug' => [
                        'siswa_id' => $user->id,
                        'kelas_id' => $user->kelas_id,
                    ]
                ], 400);
            }
            
            if (!in_array($user->kelas_id, $kelasIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke kuis ini',
                    'debug' => [
                        'siswa_kelas_id' => $user->kelas_id,
                        'kuis_kelas_ids' => $kelasIds,
                    ]
                ], 403);
            }

            // Aturan 1 attempt: jika sudah pernah attempt, wajib approval retake dari guru/admin
            $latestAttempt = KuisAttempt::where('kuis_id', $normalizedId)
                ->where('siswa_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $approvedRetakeAttempt = KuisAttempt::where('kuis_id', $normalizedId)
                ->where('siswa_id', $user->id)
                ->where('retake_allowed', true)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestAttempt) {
                // Sinkronisasi status jika attempt terakhir sudah lewat batas waktu
                if ($latestAttempt->status === 'in_progress' && $latestAttempt->isExpired()) {
                    $latestAttempt->markAsExpired();
                    $latestAttempt->refresh();
                }

                // Jika tidak ada retake yang disetujui di attempt mana pun, tolak dengan 409
                if (!$approvedRetakeAttempt) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attempt sudah ada, minta guru setujui untuk ulang',
                        'data' => [
                            'attempt_id' => $latestAttempt->id,
                            'status' => $latestAttempt->status,
                            'started_at' => $latestAttempt->started_at,
                            'ends_at' => $latestAttempt->ends_at,
                            'submitted_at' => $latestAttempt->submitted_at,
                            'latest_approved_attempt_id' => null,
                        ]
                    ], 409);
                }
            }

            // Hitung jumlah soal
            $totalSoal = Soal::where('kuis_id', $normalizedId)->count();

            if ($totalSoal === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis belum memiliki soal'
                ], 400);
            }

            // Buat attempt baru
            $now = now();
            $durationMinutes = $kuis->batas_waktu ?? 60; // default 60 menit
            $endsAt = $now->copy()->addMinutes($durationMinutes);

            $attempt = KuisAttempt::create([
                'kuis_id' => $normalizedId,
                'siswa_id' => $user->id,
                'started_at' => $now,
                'ends_at' => $endsAt,
                'status' => 'in_progress',
                'retake_allowed' => false,
                'total_soal' => $totalSoal,
                'answers' => [],
            ]);

            // Konsumsi approval retake dari attempt yang disetujui (jika ada)
            if ($approvedRetakeAttempt) {
                $approvedRetakeAttempt->update([
                    'retake_allowed' => false,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Attempt berhasil dimulai',
                'data' => [
                    'attempt_id' => $attempt->id,
                    'token' => $attempt->token,
                    'started_at' => $attempt->started_at,
                    'ends_at' => $attempt->ends_at,
                    'remaining_seconds' => $attempt->remaining_seconds,
                    'total_soal' => $attempt->total_soal,
                    'status' => $attempt->status,
                    'retake_source_attempt_id' => $approvedRetakeAttempt?->id,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulai kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve retake attempt (guru/admin)
     * POST /api/kuis/{kuisId}/attempts/{attemptId}/approve-retake
     */
    public function approveRetake(Request $request, string $kuisId, int $attemptId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            if (!$user || !in_array($user->role, ['admin', 'guru'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru/admin yang dapat menyetujui retake'
                ], 403);
            }

            $kuis = Kuis::find($normalizedId);
            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            // Guru hanya boleh approve retake untuk kuis yang dia buat
            if ($user->role === 'guru' && (int) $kuis->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses untuk approve retake kuis ini'
                ], 403);
            }

            $attempt = KuisAttempt::where('id', $attemptId)
                ->where('kuis_id', $normalizedId)
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt tidak ditemukan'
                ], 404);
            }

            $attempt->update([
                'retake_allowed' => true,
                'retake_approved_by' => $user->id,
                'retake_approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Retake disetujui, siswa bisa start attempt ulang',
                'data' => [
                    'attempt_id' => $attempt->id,
                    'kuis_id' => $attempt->kuis_id,
                    'siswa_id' => $attempt->siswa_id,
                    'retake_allowed' => (bool) $attempt->retake_allowed,
                    'retake_approved_by' => $attempt->retake_approved_by,
                    'retake_approved_at' => $attempt->retake_approved_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve retake',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attempt details (for resume after refresh)
     * GET /api/kuis/{kuisId}/attempts/{attemptId}
     */
    public function show(Request $request, string $kuisId, int $attemptId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            $attempt = KuisAttempt::where('id', $attemptId)
                ->where('kuis_id', $normalizedId)
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt tidak ditemukan'
                ], 404);
            }

            // Validasi ownership
            if ($attempt->siswa_id !== $user->id && $user->role !== 'admin' && $user->role !== 'guru') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke attempt ini'
                ], 403);
            }

            // Cek expired
            if ($attempt->status === 'in_progress' && $attempt->isExpired()) {
                $attempt->markAsExpired();
            }

            // Data response
            $data = [
                'attempt_id' => $attempt->id,
                'kuis_id' => $attempt->kuis_id,
                'token' => $attempt->token,
                'started_at' => $attempt->started_at,
                'ends_at' => $attempt->ends_at,
                'submitted_at' => $attempt->submitted_at,
                'remaining_seconds' => $attempt->remaining_seconds,
                'status' => $attempt->status,
                'total_soal' => $attempt->total_soal,
            ];

            // Jika siswa dan masih in_progress, sertakan progress jawaban
            if ($user->role === 'siswa' && $attempt->status === 'in_progress') {
                $data['answered_count'] = $attempt->answers ? count($attempt->answers) : 0;
                $data['answers'] = $attempt->answers ?? [];
            }

            // Jika sudah submitted/expired, sertakan hasil
            if ($attempt->status !== 'in_progress') {
                $data['score'] = $attempt->score;
                $data['benar'] = $attempt->benar;
                $data['salah'] = $attempt->salah;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data attempt',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get questions for an active attempt (GATING SOAL)
     * GET /api/kuis/{kuisId}/attempts/{attemptId}/questions
     * 
     * Soal HANYA bisa diakses jika:
     * - Attempt milik siswa yang login
     * - Status masih in_progress
     * - Belum expired
     */
    public function getQuestions(Request $request, string $kuisId, int $attemptId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            $attempt = KuisAttempt::where('id', $attemptId)
                ->where('kuis_id', $normalizedId)
                ->where('siswa_id', $user->id)
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt tidak ditemukan'
                ], 404);
            }

            // Cek status
            if ($attempt->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt sudah selesai atau expired'
                ], 400);
            }

            // Cek expired
            if ($attempt->isExpired()) {
                $attempt->markAsExpired();
                return response()->json([
                    'success' => false,
                    'message' => 'Waktu pengerjaan sudah habis'
                ], 400);
            }

            // Ambil soal (dengan jawaban untuk validasi di FE)
            $soal = Soal::where('kuis_id', $normalizedId)
                ->orderBy('urutan')
                ->get()
                ->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'pertanyaan' => $s->pertanyaan,
                        'image' => $s->image,
                        'pilihan' => $s->pilihan,
                        'urutan' => $s->urutan,
                        'jawaban' => $s->jawaban, // Kunci jawaban
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'attempt_id' => $attempt->id,
                    'remaining_seconds' => $attempt->remaining_seconds,
                    'total_soal' => count($soal),
                    'answered_count' => $attempt->answers ? count($attempt->answers) : 0,
                    'answers' => $attempt->answers ?? [],
                    'soal' => $soal,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil soal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Autosave answers
     * PUT /api/kuis/{kuisId}/attempts/{attemptId}/answers
     * 
     * Body: { answers: { "soal-1": "A", "soal-2": "B" } }
     */
    public function saveAnswers(Request $request, string $kuisId, int $attemptId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            $attempt = KuisAttempt::where('id', $attemptId)
                ->where('kuis_id', $normalizedId)
                ->where('siswa_id', $user->id)
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt tidak ditemukan'
                ], 404);
            }

            // Cek status
            if ($attempt->status !== 'in_progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt sudah selesai'
                ], 400);
            }

            // Cek expired
            if ($attempt->isExpired()) {
                $attempt->markAsExpired();
                return response()->json([
                    'success' => false,
                    'message' => 'Waktu pengerjaan sudah habis'
                ], 400);
            }

            // Merge answers
            $newAnswers = $request->input('answers', []);
            $currentAnswers = $attempt->answers ?? [];
            
            // Merge (new answers override existing)
            $mergedAnswers = array_merge($currentAnswers, $newAnswers);
            
            $attempt->answers = $mergedAnswers;
            $attempt->save();

            return response()->json([
                'success' => true,
                'message' => 'Jawaban tersimpan',
                'data' => [
                    'answered_count' => count($mergedAnswers),
                    'remaining_seconds' => $attempt->remaining_seconds,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jawaban',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit attempt (selesai mengerjakan)
     * POST /api/kuis/{kuisId}/attempts/{attemptId}/submit
     * 
     * Server akan:
     * - Cek waktu (jika lewat, status = expired tapi tetap grade)
     * - Hitung nilai dari jawaban
     * - Simpan hasil ke attempt
     */
    public function submit(Request $request, string $kuisId, int $attemptId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            $attempt = KuisAttempt::where('id', $attemptId)
                ->where('kuis_id', $normalizedId)
                ->where('siswa_id', $user->id)
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attempt tidak ditemukan'
                ], 404);
            }

            // Cek apakah sudah pernah submit
            if ($attempt->status === 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis sudah pernah disubmit'
                ], 400);
            }

            // Merge final answers dari request jika ada
            $finalAnswers = $request->input('answers', []);
            $currentAnswers = $attempt->answers ?? [];
            $mergedAnswers = array_merge($currentAnswers, $finalAnswers);
            $attempt->answers = $mergedAnswers;

            // Ambil semua soal dan kunci jawaban
            $soal = Soal::where('kuis_id', $normalizedId)->get();
            $totalSoal = $soal->count();
            $benar = 0;
            $salah = 0;

            foreach ($soal as $s) {
                $jawabanSiswa = $mergedAnswers[$s->id] ?? null;
                if ($jawabanSiswa === $s->jawaban) {
                    $benar++;
                } else {
                    $salah++;
                }
            }

            // Hitung score (0-100) dengan 2 desimal (contoh: 1/30 = 3.33)
            $score = $totalSoal > 0 ? round(($benar / $totalSoal) * 100, 2) : 0;

            // Tentukan status (expired jika waktu sudah habis)
            $status = $attempt->isExpired() ? 'expired' : 'submitted';

            // Update attempt
            $attempt->update([
                'status' => $status,
                'submitted_at' => now(),
                'score' => $score,
                'benar' => $benar,
                'salah' => $salah,
                'total_soal' => $totalSoal,
            ]);

            return response()->json([
                'success' => true,
                'message' => $status === 'expired' ? 'Kuis disubmit (waktu sudah habis)' : 'Kuis berhasil disubmit',
                'data' => [
                    'attempt_id' => $attempt->id,
                    'status' => $status,
                    'score' => $score,
                    'benar' => $benar,
                    'salah' => $salah,
                    'total_soal' => $totalSoal,
                    'submitted_at' => $attempt->submitted_at,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal submit kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attempt history for a siswa on a kuis
     * GET /api/kuis/{kuisId}/attempts
     */
    public function index(Request $request, string $kuisId)
    {
        try {
            $user = auth()->user();
            $normalizedId = $this->normalizeKuisId($kuisId);

            $query = KuisAttempt::where('kuis_id', $normalizedId)
                ->with('siswa:id,name,nis');

            // Siswa hanya lihat attempt miliknya
            if ($user->role === 'siswa') {
                $query->where('siswa_id', $user->id);
            }

            $attempts = $query->orderBy('created_at', 'desc')->get();

            $data = $attempts->map(function ($a) {
                $item = [
                    'attempt_id' => $a->id,
                    'kuis_id' => $a->kuis_id,
                    'siswa_id' => $a->siswa_id,
                    'siswa_nama' => $a->siswa?->name,
                    'started_at' => $a->started_at,
                    'ends_at' => $a->ends_at,
                    'submitted_at' => $a->submitted_at,
                    'status' => $a->status,
                    'score' => $a->score,
                    'benar' => $a->benar,
                    'salah' => $a->salah,
                    'total_soal' => $a->total_soal,
                    'siswa' => $a->siswa ? [
                        'id' => $a->siswa->id,
                        'nama' => $a->siswa->name,
                        'nis' => $a->siswa->nis,
                    ] : null,
                ];

                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data attempt',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
