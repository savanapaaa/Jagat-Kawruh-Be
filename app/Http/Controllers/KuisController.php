<?php

namespace App\Http\Controllers;

use App\Models\Kuis;
use App\Models\Soal;
use App\Models\KuisAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class KuisController extends Controller
{
    /**
     * Normalize kuis ID - support both numeric ID (26) and full ID (kuis-26)
     * Database stores as "kuis-XX" format
     */
    private function normalizeKuisId(string $kuisId): string
    {
        if (preg_match('/^kuis-\d+$/i', $kuisId)) {
            return strtolower($kuisId);
        }
        if (preg_match('/^\d+$/', $kuisId)) {
            return 'kuis-' . $kuisId;
        }
        return $kuisId;
    }

    /**
     * Display a listing of kuis
     * GET /api/kuis
     * Query params: kelas, kelas_id, status
     * Siswa: hanya lihat yang aktif & sesuai kelasnya (via pivot table)
     * Guru/Admin: lihat semua
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = Kuis::query()->withCount('soal as jumlah_soal')
                ->with(['soal:id,kuis_id,urutan', 'kelasRelation:id,nama,tingkat'])
                ->select('kuis.*')->selectSub(function ($q) {
                    // Count dari KuisAttempt (bukan hasil_kuis)
                    $q->from('kuis_attempts')
                        ->selectRaw('COUNT(DISTINCT siswa_id)')
                        ->whereIn('status', ['submitted', 'expired'])
                        ->whereColumn('kuis_attempts.kuis_id', 'kuis.id');
                }, 'total_peserta');

            // Guru hanya melihat kuis yang dia buat (admin tetap lihat semua)
            if ($user && $user->role === 'guru') {
                $query->where('created_by', $user->id);
            }

            // Siswa hanya bisa lihat kuis yang aktif & sesuai kelas_id
            if ($user->role === 'siswa') {
                $query->where('status', 'Aktif');
                
                // Filter by kelas siswa menggunakan pivot table
                if ($user->kelas_id) {
                    $query->whereHas('kelasRelation', function($q) use ($user) {
                        $q->where('kelas.id', $user->kelas_id);
                    });
                }
            }

            // Filter by kelas_id (dari query param, untuk guru/admin)
            if ($request->has('kelas_id') && in_array($user->role, ['guru', 'admin'])) {
                $query->whereHas('kelasRelation', function($q) use ($request) {
                    $q->where('kelas.id', $request->kelas_id);
                });
            }

            // Filter by kelas tingkat (dari query param, untuk guru/admin) - backward compat
            if ($request->has('kelas') && in_array($user->role, ['guru', 'admin'])) {
                $kelasParam = $request->kelas;
                $query->whereHas('kelasRelation', function($q) use ($kelasParam) {
                    $q->where('kelas.tingkat', $kelasParam);
                });
            }

            // Filter by status (untuk guru/admin)
            if ($request->has('status') && in_array($user->role, ['guru', 'admin'])) {
                $query->where('status', $request->status);
            }

            $kuis = $query->orderBy('created_at', 'desc')->get();

            $data = $kuis->map(function($k) {
                $jumlahSoal = $this->getDisplaySoalCount($k, (int) ($k->jumlah_soal ?? 0));
                return [
                    'id' => $k->id,
                    'judul' => $k->judul,
                    // Legacy field (array of tingkat) - for backward compat
                    'kelas' => $k->kelas,
                    // New: array of kelas objects from pivot
                    'kelas_list' => $k->kelasRelation->map(fn($kls) => [
                        'id' => $kls->id,
                        'nama' => $kls->nama,
                        'tingkat' => $kls->tingkat
                    ]),
                    'kelas_ids' => $k->kelasRelation->pluck('id'),
                    'batas_waktu' => $k->batas_waktu,
                    'draft_soal_count' => (int) ($k->draft_soal_count ?? 0),
                    'total_peserta' => (int) ($k->total_peserta ?? 0),
                    // Count fields (aliases for FE compatibility)
                    'jumlah_soal' => $jumlahSoal,
                    'soal_count' => $jumlahSoal,
                    'total_soal' => $jumlahSoal,
                    // Lightweight soal list so FE can use soal.length on list page
                    'soal' => $k->soal ? $k->soal->map(fn ($s) => ['id' => $s->id, 'urutan' => $s->urutan]) : [],
                    'status' => $k->status,
                    'created_at' => $k->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created kuis
     * POST /api/kuis
     */
    public function store(Request $request)
    {
        try {
            $payload = $request->all();
            $payload['kelas'] = $this->normalizeKelas($payload['kelas'] ?? null);

            if (isset($payload['soal']) && is_string($payload['soal'])) {
                $decodedSoal = json_decode($payload['soal'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload['soal'] = $decodedSoal;
                }
            }

            // If FE sends a single soal object, wrap it into an array
            if (isset($payload['soal']) && is_array($payload['soal'])) {
                $isList = array_is_list($payload['soal']);
                if (!$isList && (isset($payload['soal']['pertanyaan']) || isset($payload['soal']['question']) || isset($payload['soal']['text']))) {
                    $payload['soal'] = [$payload['soal']];
                }
            }

            if (isset($payload['soal']) && is_array($payload['soal'])) {
                foreach ($payload['soal'] as $i => $soalData) {
                    if (!is_array($soalData)) {
                        continue;
                    }

                    // Accept FE alias keys
                    if (!isset($soalData['image'])) {
                        $imageCandidate = $soalData['url_gambar'] ?? $soalData['image_url'] ?? $soalData['gambar'] ?? null;
                        if ($imageCandidate !== null) {
                            $payload['soal'][$i]['image'] = $imageCandidate;
                            $soalData['image'] = $imageCandidate;
                        }
                    }
                    if (!isset($soalData['pertanyaan']) && isset($soalData['question'])) {
                        $payload['soal'][$i]['pertanyaan'] = $soalData['question'];
                        $soalData['pertanyaan'] = $soalData['question'];
                    }
                    if (!isset($soalData['pertanyaan']) && isset($soalData['text'])) {
                        $payload['soal'][$i]['pertanyaan'] = $soalData['text'];
                        $soalData['pertanyaan'] = $soalData['text'];
                    }
                    if (!isset($soalData['pertanyaan']) && isset($soalData['pertanyaan_soal'])) {
                        $payload['soal'][$i]['pertanyaan'] = $soalData['pertanyaan_soal'];
                        $soalData['pertanyaan'] = $soalData['pertanyaan_soal'];
                    }
                    if (!isset($soalData['pilihan']) && isset($soalData['options'])) {
                        $payload['soal'][$i]['pilihan'] = $soalData['options'];
                        $soalData['pilihan'] = $soalData['options'];
                    }
                    if (!isset($soalData['jawaban'])) {
                        $answerCandidate = $soalData['answer'] ?? $soalData['jawaban_benar'] ?? $soalData['jawabanBenar'] ?? $soalData['correct_answer'] ?? $soalData['correctAnswer'] ?? null;
                        if ($answerCandidate !== null) {
                            $payload['soal'][$i]['jawaban'] = $answerCandidate;
                            $soalData['jawaban'] = $answerCandidate;
                        }
                    }

                    // Normalize pilihan (string JSON / list / associative)
                    if (isset($soalData['pilihan'])) {
                        $payload['soal'][$i]['pilihan'] = $this->normalizePilihan($soalData['pilihan']);
                    }

                    // Normalize jawaban
                    if (array_key_exists('jawaban', $soalData)) {
                        $normalizedPilihan = isset($payload['soal'][$i]['pilihan']) && is_array($payload['soal'][$i]['pilihan'])
                            ? $payload['soal'][$i]['pilihan']
                            : [];
                        $payload['soal'][$i]['jawaban'] = $this->resolveJawaban($soalData['jawaban'], $normalizedPilihan);
                    }
                }
            }

            $validator = Validator::make($payload, [
                'judul' => 'required|string|max:255',
                'kelas' => 'sometimes|array', // Legacy: array of tingkat (X, XI, XII) - opsional
                'kelas.*' => 'in:X,XI,XII',
                'kelas_ids' => 'required|array|min:1', // New: array of kelas IDs - WAJIB
                'kelas_ids.*' => 'integer|exists:kelas,id',
                // FE kadang belum mengirim batas_waktu saat create
                'batas_waktu' => 'nullable|integer|min:1',
                'status' => 'sometimes|in:Draft,Aktif,Selesai',
                // FE boleh create kuis dulu tanpa soal; nanti bisa update via PUT /api/kuis/{id}
                'soal' => 'nullable|array',
            ], [
                'judul.required' => 'Judul kuis wajib diisi',
                'kelas_ids.required' => 'Kelas wajib dipilih (kelas_ids)',
                'kelas_ids.min' => 'Minimal pilih 1 kelas',
                'kelas.*.in' => 'Kelas harus salah satu dari: X, XI, XII',
                'batas_waktu.integer' => 'Batas waktu harus berupa angka',
            ]);

            if ($validator->fails()) {
                $response = [
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ];

                if (app()->environment('local')) {
                    $response['debug'] = [
                        'keys' => array_keys($payload),
                        'kelas' => $payload['kelas'] ?? null,
                        'has_soal' => array_key_exists('soal', $payload),
                        'soal_type' => isset($payload['soal']) ? gettype($payload['soal']) : null,
                    ];
                }

                return response()->json($response, 422);
            }

            $kelas = $payload['kelas'];
            $soalList = isset($payload['soal']) && is_array($payload['soal']) ? $payload['soal'] : [];

            // FE kadang mengirim placeholder soal kosong (mis. 2 item tapi belum diisi).
            // Abaikan item yang benar-benar kosong supaya tidak memicu validasi.
            $soalList = array_values(array_filter($soalList, function ($soal) {
                if (!is_array($soal)) {
                    return false;
                }

                $pertanyaan = isset($soal['pertanyaan']) ? trim((string) $soal['pertanyaan']) : '';
                $jawaban = isset($soal['jawaban']) ? trim((string) $soal['jawaban']) : '';
                $pilihan = isset($soal['pilihan']) && is_array($soal['pilihan']) ? $soal['pilihan'] : [];

                $hasPilihan = false;
                foreach ($pilihan as $v) {
                    if (trim((string) $v) !== '') {
                        $hasPilihan = true;
                        break;
                    }
                }

                return $pertanyaan !== '' || $jawaban !== '' || $hasPilihan;
            }));

            DB::beginTransaction();

            // Buat array kelas legacy dari kelas_ids untuk backward compat
            $kelasLegacy = [];
            if (isset($payload['kelas_ids']) && is_array($payload['kelas_ids'])) {
                $kelasData = \App\Models\Kelas::whereIn('id', $payload['kelas_ids'])->pluck('tingkat')->unique()->values()->toArray();
                $kelasLegacy = $kelasData;
            } elseif (isset($payload['kelas'])) {
                $kelasLegacy = $payload['kelas'];
            }

            $status = $payload['status'] ?? 'Draft';
            $requestedSoalCount = isset($payload['soal']) && is_array($payload['soal']) ? count($payload['soal']) : 0;

            $kuis = Kuis::create([
                'judul' => $payload['judul'],
                'kelas' => $kelasLegacy,
                'batas_waktu' => $payload['batas_waktu'] ?? 60,
                'draft_soal_count' => $this->isDraftStatus($status) ? $requestedSoalCount : 0,
                'status' => $status,
                'created_by' => auth()->id()
            ]);

            // Sync kelas via pivot table (WAJIB ada kelas_ids)
            if (isset($payload['kelas_ids']) && is_array($payload['kelas_ids'])) {
                $kuis->kelasRelation()->sync($payload['kelas_ids']);
            }

            // Create soal (optional)
            if (count($soalList) > 0) {
                $soalListToPersist = $soalList;

                // Draft: boleh simpan meski soal masih parsial.
                // Hanya soal yang sudah lengkap yang dipersist ke tabel soal.
                if ($this->isDraftStatus($status)) {
                    $soalListToPersist = array_values(array_filter($soalList, function ($soal) {
                        return $this->isCompleteSoal($soal);
                    }));
                }

                if (!$this->isDraftStatus($status)) {
                    $soalValidator = Validator::make(['soal' => $soalList], [
                    'soal' => 'array|min:1',
                    'soal.*.pertanyaan' => 'required|string',
                    'soal.*.image' => 'nullable|string',
                    'soal.*.pilihan' => 'required|array',
                    'soal.*.pilihan.A' => 'required|string',
                    'soal.*.pilihan.B' => 'required|string',
                    'soal.*.pilihan.C' => 'required|string',
                    'soal.*.pilihan.D' => 'required|string',
                    'soal.*.pilihan.E' => 'nullable|string',
                    'soal.*.jawaban' => 'required|in:A,B,C,D,E',
                ], [
                    'soal.*.pertanyaan.required' => 'Pertanyaan wajib diisi',
                    'soal.*.pilihan.required' => 'Pilihan jawaban wajib diisi',
                    'soal.*.jawaban.required' => 'Jawaban yang benar wajib dipilih',
                ]);

                    if ($soalValidator->fails()) {
                        DB::rollBack();
                        $response = [
                            'success' => false,
                            'message' => 'Validasi gagal',
                            'errors' => $soalValidator->errors(),
                        ];

                        if (app()->environment('local')) {
                            $response['debug'] = [
                                'first_soal' => $soalList[0] ?? null,
                                'first_soal_keys' => isset($soalList[0]) && is_array($soalList[0]) ? array_keys($soalList[0]) : null,
                            ];

                            Log::info('Kuis update soal validation failed', [
                                'kuis_id' => $kuis->id,
                                'errors' => $soalValidator->errors()->toArray(),
                                'first_soal' => $soalList[0] ?? null,
                            ]);
                        }

                        return response()->json($response, 422);
                    }
                }

                foreach ($soalListToPersist as $index => $soalData) {
                    Soal::create([
                        'kuis_id' => $kuis->id,
                        'pertanyaan' => $soalData['pertanyaan'],
                        'image' => $soalData['image'] ?? null,
                        'pilihan' => $soalData['pilihan'],
                        'jawaban' => $soalData['jawaban'],
                        'urutan' => $index + 1,
                    ]);
                }
            }

            DB::commit();

            $kuis->load('soal', 'kelasRelation:id,nama,tingkat');
            $kuis->loadCount('soal as jumlah_soal');
            $displaySoalCount = $this->getDisplaySoalCount($kuis, (int) ($kuis->jumlah_soal ?? 0));

            return response()->json([
                'success' => true,
                'message' => 'Kuis berhasil dibuat',
                'data' => [
                    'id' => $kuis->id,
                    'judul' => $kuis->judul,
                    'kelas' => $kuis->kelas,
                    'kelas_list' => $kuis->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $kuis->kelasRelation->pluck('id'),
                    'batas_waktu' => $kuis->batas_waktu,
                    'draft_soal_count' => (int) ($kuis->draft_soal_count ?? 0),
                    'jumlah_soal' => $displaySoalCount,
                    'soal_count' => $displaySoalCount,
                    'total_soal' => $displaySoalCount,
                    'status' => $kuis->status,
                    'created_at' => $kuis->created_at,
                    'soal' => $kuis->soal,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified kuis with questions
     * GET /api/kuis/{id}
     * 
     * GATING SOAL:
     * - Siswa: TIDAK dapat soal, hanya metadata (harus start attempt dulu)
     * - Guru/Admin: dapat soal lengkap dengan jawaban
     */
    public function show(string $id)
    {
        try {
            $user = auth()->user();
            
            $kuis = Kuis::with(['soal', 'kelasRelation:id,nama,tingkat'])
                ->withCount('soal as jumlah_soal')
                ->find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            // Count peserta dari KuisAttempt (bukan HasilKuis)
            $totalPeserta = KuisAttempt::where('kuis_id', $kuis->id)
                ->whereIn('status', ['submitted', 'expired'])
                ->distinct('siswa_id')
                ->count('siswa_id');

            // Base data (metadata) - untuk semua role
            $displaySoalCount = $this->getDisplaySoalCount($kuis, (int) ($kuis->jumlah_soal ?? 0));

            $data = [
                'id' => $kuis->id,
                'judul' => $kuis->judul,
                'kelas' => $kuis->kelas,
                'kelas_list' => $kuis->kelasRelation->map(fn($k) => [
                    'id' => $k->id,
                    'nama' => $k->nama,
                    'tingkat' => $k->tingkat
                ]),
                'kelas_ids' => $kuis->kelasRelation->pluck('id'),
                'batas_waktu' => $kuis->batas_waktu,
                'status' => $kuis->status,
                'total_peserta' => (int) $totalPeserta,
                'draft_soal_count' => (int) ($kuis->draft_soal_count ?? 0),
                'jumlah_soal' => $displaySoalCount,
                'soal_count' => $displaySoalCount,
                'total_soal' => $displaySoalCount,
                'created_at' => $kuis->created_at,
            ];

            // GATING SOAL: Siswa TIDAK dapat soal di endpoint ini
            // Mereka harus start attempt dan akses via /attempts/{id}/questions
            if ($user->role === 'siswa') {
                // Cek apakah siswa sudah punya attempt
                $existingAttempt = \App\Models\KuisAttempt::where('kuis_id', $id)
                    ->where('siswa_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($existingAttempt) {
                    $data['attempt'] = [
                        'id' => $existingAttempt->id,
                        'status' => $existingAttempt->status,
                        'started_at' => $existingAttempt->started_at,
                        'score' => $existingAttempt->score,
                    ];
                } else {
                    $data['attempt'] = null;
                }
                
                // TIDAK sertakan soal untuk siswa
                $data['soal'] = null;
                $data['message'] = 'Mulai kuis untuk melihat soal';
            } else {
                // Guru/Admin: dapat soal lengkap dengan jawaban
                $soalMapped = $kuis->soal->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'pertanyaan' => $s->pertanyaan,
                        'image' => $s->image,
                        'pilihan' => $s->pilihan,
                        'jawaban' => $s->jawaban, // Kunci jawaban
                        'urutan' => $s->urutan,
                    ];
                });

                if ($this->isDraftStatus((string) $kuis->status) && $displaySoalCount > $soalMapped->count()) {
                    for ($i = $soalMapped->count() + 1; $i <= $displaySoalCount; $i++) {
                        $soalMapped->push([
                            'id' => null,
                            'pertanyaan' => '',
                            'image' => null,
                            'pilihan' => [
                                'A' => '',
                                'B' => '',
                                'C' => '',
                                'D' => '',
                                'E' => '',
                            ],
                            'jawaban' => null,
                            'urutan' => $i,
                            'is_placeholder' => true,
                        ]);
                    }
                }

                $data['soal'] = $soalMapped->values();
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified kuis
     * PUT /api/kuis/{id}
     */
    public function update(Request $request, string $id)
    {
        try {
            $kuis = Kuis::find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $payload = $request->all();

            if (array_key_exists('kelas', $payload)) {
                $payload['kelas'] = $this->normalizeKelas($payload['kelas']);
            }

            if (isset($payload['soal']) && is_string($payload['soal'])) {
                $decodedSoal = json_decode($payload['soal'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload['soal'] = $decodedSoal;
                }
            }

            // If FE sends a single soal object, wrap it into an array
            if (isset($payload['soal']) && is_array($payload['soal'])) {
                $isList = array_is_list($payload['soal']);
                if (!$isList && (isset($payload['soal']['pertanyaan']) || isset($payload['soal']['question']) || isset($payload['soal']['text']))) {
                    $payload['soal'] = [$payload['soal']];
                }
            }

            if (isset($payload['soal']) && is_array($payload['soal'])) {
                foreach ($payload['soal'] as $i => $soalData) {
                    if (!is_array($soalData)) {
                        continue;
                    }

                    // Accept FE alias keys
                    if (!isset($soalData['image']) && isset($soalData['url_gambar'])) {
                        $payload['soal'][$i]['image'] = $soalData['url_gambar'];
                        $soalData['image'] = $soalData['url_gambar'];
                    }
                    if (!isset($soalData['pertanyaan']) && isset($soalData['question'])) {
                        $payload['soal'][$i]['pertanyaan'] = $soalData['question'];
                        $soalData['pertanyaan'] = $soalData['question'];
                    }
                    if (!isset($soalData['pertanyaan']) && isset($soalData['text'])) {
                        $payload['soal'][$i]['pertanyaan'] = $soalData['text'];
                        $soalData['pertanyaan'] = $soalData['text'];
                    }
                    if (!isset($soalData['pilihan']) && isset($soalData['options'])) {
                        $payload['soal'][$i]['pilihan'] = $soalData['options'];
                        $soalData['pilihan'] = $soalData['options'];
                    }
                    if (!isset($soalData['jawaban']) && isset($soalData['answer'])) {
                        $payload['soal'][$i]['jawaban'] = $soalData['answer'];
                        $soalData['jawaban'] = $soalData['answer'];
                    }
                    if (isset($soalData['pilihan'])) {
                        $payload['soal'][$i]['pilihan'] = $this->normalizePilihan($soalData['pilihan']);
                    }
                    if (array_key_exists('jawaban', $soalData)) {
                        $normalizedPilihan = isset($payload['soal'][$i]['pilihan']) && is_array($payload['soal'][$i]['pilihan'])
                            ? $payload['soal'][$i]['pilihan']
                            : [];
                        $payload['soal'][$i]['jawaban'] = $this->resolveJawaban($soalData['jawaban'], $normalizedPilihan);
                    }
                }
            }

            // Filter empty placeholder questions
            $soalList = [];
            if (isset($payload['soal']) && is_array($payload['soal'])) {
                $soalList = array_values(array_filter($payload['soal'], function ($soal) {
                    if (!is_array($soal)) {
                        return false;
                    }

                    $pertanyaan = isset($soal['pertanyaan']) ? trim((string) $soal['pertanyaan']) : '';
                    $jawaban = isset($soal['jawaban']) ? trim((string) $soal['jawaban']) : '';
                    $pilihan = isset($soal['pilihan']) && is_array($soal['pilihan']) ? $soal['pilihan'] : [];

                    $hasPilihan = false;
                    foreach ($pilihan as $v) {
                        if (trim((string) $v) !== '') {
                            $hasPilihan = true;
                            break;
                        }
                    }

                    return $pertanyaan !== '' || $jawaban !== '' || $hasPilihan;
                }));
            }

            $validator = Validator::make($payload, [
                'judul' => 'sometimes|string|max:255',
                'kelas' => 'sometimes', // Legacy: array of tingkat - opsional
                'kelas_ids' => 'sometimes|array|min:1', // New: array of kelas IDs
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'batas_waktu' => 'sometimes|integer|min:1',
                'status' => 'sometimes|in:Draft,Aktif,Selesai',
                // soal divalidasi terpisah jika ada yang terisi
                'soal' => 'sometimes'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = [];
            if ($request->has('judul')) $updateData['judul'] = $request->judul;
            if ($request->has('batas_waktu')) $updateData['batas_waktu'] = $request->batas_waktu;
            if ($request->has('status')) $updateData['status'] = $request->status;
            
            // Update kelas legacy dari kelas_ids jika ada
            if (isset($payload['kelas_ids']) && is_array($payload['kelas_ids'])) {
                $kelasData = \App\Models\Kelas::whereIn('id', $payload['kelas_ids'])->pluck('tingkat')->unique()->values()->toArray();
                $updateData['kelas'] = $kelasData;
            } elseif ($request->has('kelas')) {
                $updateData['kelas'] = $payload['kelas'];
            }

            $kuis->update($updateData);

            // Sync kelas via pivot table jika ada kelas_ids
            if (isset($payload['kelas_ids']) && is_array($payload['kelas_ids'])) {
                $kuis->kelasRelation()->sync($payload['kelas_ids']);
            }

            // Update soal if provided and at least one question is actually filled.
            // If FE only sends empty placeholders, do nothing (do not wipe existing questions).
            if (array_key_exists('soal', $payload)) {
                $effectiveStatus = $request->has('status') ? (string) $request->status : (string) $kuis->status;
                $requestedSoalCount = is_array($payload['soal']) ? count($payload['soal']) : 0;

                if ($this->isDraftStatus($effectiveStatus)) {
                    $kuis->draft_soal_count = $requestedSoalCount;
                } elseif ($request->has('status') && !$this->isDraftStatus($effectiveStatus)) {
                    $kuis->draft_soal_count = 0;
                }

                $soalListToPersist = $soalList;

                if ($this->isDraftStatus($effectiveStatus)) {
                    // Draft: soal boleh parsial, simpan hanya yang sudah lengkap.
                    $soalListToPersist = array_values(array_filter($soalList, function ($soal) {
                        return $this->isCompleteSoal($soal);
                    }));
                }

                if (!$this->isDraftStatus($effectiveStatus)) {
                    $soalValidator = Validator::make(['soal' => $soalList], [
                    'soal' => 'array|min:1',
                    'soal.*.pertanyaan' => 'required|string',
                    'soal.*.image' => 'nullable|string',
                    'soal.*.pilihan' => 'required|array',
                    'soal.*.pilihan.A' => 'required|string',
                    'soal.*.pilihan.B' => 'required|string',
                    'soal.*.pilihan.C' => 'required|string',
                    'soal.*.pilihan.D' => 'required|string',
                    'soal.*.pilihan.E' => 'nullable|string',
                    'soal.*.jawaban' => 'required|in:A,B,C,D,E',
                ], [
                    'soal.*.pertanyaan.required' => 'Pertanyaan wajib diisi',
                    'soal.*.pilihan.required' => 'Pilihan jawaban wajib diisi',
                    'soal.*.jawaban.required' => 'Jawaban yang benar wajib dipilih',
                ]);

                    if ($soalValidator->fails()) {
                        DB::rollBack();
                        $response = [
                            'success' => false,
                            'message' => 'Validasi gagal',
                            'errors' => $soalValidator->errors(),
                        ];

                        if (app()->environment('local')) {
                            $response['debug'] = [
                                'first_soal' => $soalList[0] ?? null,
                                'first_soal_keys' => isset($soalList[0]) && is_array($soalList[0]) ? array_keys($soalList[0]) : null,
                            ];
                        }

                        return response()->json($response, 422);
                    }
                }

                // Draft dengan semua soal parsial: jangan hapus soal lama.
                if (count($soalListToPersist) > 0) {
                    // Replace existing questions
                    Soal::where('kuis_id', $kuis->id)->delete();

                    foreach ($soalListToPersist as $index => $soalData) {
                        Soal::create([
                            'kuis_id' => $kuis->id,
                            'pertanyaan' => $soalData['pertanyaan'],
                            'image' => $soalData['image'] ?? null,
                            'pilihan' => $soalData['pilihan'],
                            'jawaban' => $soalData['jawaban'],
                            'urutan' => $index + 1,
                        ]);
                    }
                }
            }

            // Jika FE tidak kirim array soal saat pindah dari Draft ke non-Draft, reset hitung draft.
            if (!array_key_exists('soal', $payload) && $request->has('status') && !$this->isDraftStatus((string) $request->status)) {
                $kuis->draft_soal_count = 0;
            }

            $kuis->save();

            DB::commit();

            $kuis->load('soal', 'kelasRelation:id,nama,tingkat');
            $kuis->loadCount('soal as jumlah_soal');
            $displaySoalCount = $this->getDisplaySoalCount($kuis, (int) ($kuis->jumlah_soal ?? 0));

            return response()->json([
                'success' => true,
                'message' => 'Kuis berhasil diupdate',
                'data' => [
                    'id' => $kuis->id,
                    'judul' => $kuis->judul,
                    'kelas' => $kuis->kelas,
                    'kelas_list' => $kuis->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $kuis->kelasRelation->pluck('id'),
                    'batas_waktu' => $kuis->batas_waktu,
                    'draft_soal_count' => (int) ($kuis->draft_soal_count ?? 0),
                    'jumlah_soal' => $displaySoalCount,
                    'soal_count' => $displaySoalCount,
                    'total_soal' => $displaySoalCount,
                    'status' => $kuis->status,
                    'soal' => $kuis->soal,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import soal dari CSV/XLSX (Guru/Admin)
     * POST /api/kuis/{id}/import-soal
     * multipart/form-data: file
     *
     * Template kolom (header):
     * pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, opsi_e, jawaban_benar (A-E)
     */
    public function importSoal(Request $request, string $id)
    {
        try {
            $normalizedId = $this->normalizeKuisId($id);
            $kuis = Kuis::with('kelasRelation:id,nama,tingkat')
                ->withCount('soal as jumlah_soal')
                ->find($normalizedId);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            ], [
                'file.required' => 'File import wajib diunggah',
                'file.mimes' => 'Format file harus CSV atau XLSX',
                'file.max' => 'Ukuran file maksimal 10MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension() ?: '');
            $absolutePath = $file->getRealPath();

            if (!$absolutePath || !is_file($absolutePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File import tidak valid',
                ], 422);
            }

            $rawRows = [];
            if (in_array($ext, ['csv', 'txt'], true)) {
                $rawRows = $this->readCsvRows($absolutePath);
            } elseif ($ext === 'xlsx') {
                $rawRows = $this->readXlsxRows($absolutePath);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file tidak didukung',
                ], 422);
            }

            if (count($rawRows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'File kosong atau tidak memiliki data',
                ], 422);
            }

            $headerRow = $rawRows[0] ?? [];
            $headerMap = $this->buildImportHeaderMap($headerRow);

            $requiredHeaders = ['pertanyaan', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'jawaban_benar'];
            $missing = [];
            foreach ($requiredHeaders as $h) {
                if (!array_key_exists($h, $headerMap)) {
                    $missing[] = $h;
                }
            }

            if (!empty($missing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Header kolom tidak sesuai template',
                    'data' => [
                        'missing_headers' => $missing,
                        'expected' => $requiredHeaders,
                    ],
                ], 422);
            }

            $prepared = [];
            $rowErrors = [];

            for ($i = 1; $i < count($rawRows); $i++) {
                $row = $rawRows[$i];

                $rowAssoc = [];
                foreach ($requiredHeaders as $key) {
                    $idx = $headerMap[$key];
                    $value = $row[$idx] ?? '';
                    if (is_string($value)) {
                        $value = trim($value);
                    }
                    $rowAssoc[$key] = $value;
                }

                $isEmptyRow = true;
                foreach ($rowAssoc as $v) {
                    if (trim((string) $v) !== '') {
                        $isEmptyRow = false;
                        break;
                    }
                }
                if ($isEmptyRow) {
                    continue;
                }

                $errors = [];
                $pertanyaan = trim((string) ($rowAssoc['pertanyaan'] ?? ''));
                $opsiA = trim((string) ($rowAssoc['opsi_a'] ?? ''));
                $opsiB = trim((string) ($rowAssoc['opsi_b'] ?? ''));
                $opsiC = trim((string) ($rowAssoc['opsi_c'] ?? ''));
                $opsiD = trim((string) ($rowAssoc['opsi_d'] ?? ''));
                $opsiE = trim((string) ($rowAssoc['opsi_e'] ?? ''));

                $jawaban = strtoupper(trim((string) ($rowAssoc['jawaban_benar'] ?? '')));
                if ($jawaban !== '') {
                    $jawaban = strtoupper(substr($jawaban, 0, 1));
                }

                if ($pertanyaan === '') {
                    $errors['pertanyaan'] = 'Pertanyaan wajib diisi';
                }
                if ($opsiA === '') {
                    $errors['opsi_a'] = 'Opsi A wajib diisi';
                }
                if ($opsiB === '') {
                    $errors['opsi_b'] = 'Opsi B wajib diisi';
                }
                if ($opsiC === '') {
                    $errors['opsi_c'] = 'Opsi C wajib diisi';
                }
                if ($opsiD === '') {
                    $errors['opsi_d'] = 'Opsi D wajib diisi';
                }

                if (!in_array($jawaban, ['A', 'B', 'C', 'D', 'E'], true)) {
                    $errors['jawaban_benar'] = 'Jawaban benar harus A/B/C/D/E';
                }
                if ($jawaban === 'E' && $opsiE === '') {
                    $errors['opsi_e'] = 'Opsi E wajib diisi jika jawaban benar = E';
                }

                if (!empty($errors)) {
                    // +1 karena rawRows sudah termasuk header di index 0
                    $rowErrors[] = [
                        'row' => $i + 1,
                        'errors' => $errors,
                    ];
                    continue;
                }

                $prepared[] = [
                    'pertanyaan' => $pertanyaan,
                    'pilihan' => [
                        'A' => $opsiA,
                        'B' => $opsiB,
                        'C' => $opsiC,
                        'D' => $opsiD,
                        'E' => $opsiE,
                    ],
                    'jawaban' => $jawaban,
                ];
            }

            if (!empty($rowErrors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi data import gagal',
                    'data' => [
                        'row_errors' => $rowErrors,
                    ],
                ], 422);
            }

            if (count($prepared) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada baris data yang valid untuk diimport',
                ], 422);
            }

            DB::beginTransaction();

            // Replace existing soal (konsisten dengan KuisController::update)
            Soal::where('kuis_id', $kuis->id)->delete();

            foreach ($prepared as $index => $soalData) {
                Soal::create([
                    'kuis_id' => $kuis->id,
                    'pertanyaan' => $soalData['pertanyaan'],
                    'image' => null,
                    'pilihan' => $soalData['pilihan'],
                    'jawaban' => $soalData['jawaban'],
                    'urutan' => $index + 1,
                ]);
            }

            // Update draft count bila kuis masih Draft
            if ($this->isDraftStatus((string) $kuis->status)) {
                $kuis->draft_soal_count = count($prepared);
                $kuis->save();
            }

            DB::commit();

            $kuis->refresh();
            $kuis->load('soal', 'kelasRelation:id,nama,tingkat');
            $kuis->loadCount('soal as jumlah_soal');
            $displaySoalCount = $this->getDisplaySoalCount($kuis, (int) ($kuis->jumlah_soal ?? 0));

            $soalMapped = $kuis->soal->sortBy('urutan')->values()->map(function ($s) {
                return [
                    'id' => $s->id,
                    'pertanyaan' => $s->pertanyaan,
                    'image' => $s->image,
                    'pilihan' => $s->pilihan,
                    'jawaban' => $s->jawaban,
                    'urutan' => $s->urutan,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Import soal berhasil',
                'data' => [
                    'id' => $kuis->id,
                    'judul' => $kuis->judul,
                    'kelas' => $kuis->kelas,
                    'kelas_list' => $kuis->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $kuis->kelasRelation->pluck('id'),
                    'batas_waktu' => $kuis->batas_waktu,
                    'status' => $kuis->status,
                    'draft_soal_count' => (int) ($kuis->draft_soal_count ?? 0),
                    'jumlah_soal' => $displaySoalCount,
                    'soal_count' => $displaySoalCount,
                    'total_soal' => $displaySoalCount,
                    'imported_count' => count($prepared),
                    'soal' => $soalMapped,
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal import soal',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function buildImportHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $col) {
            $normalized = $this->normalizeImportHeader($col);
            if ($normalized === '') {
                continue;
            }

            // Keep first occurrence
            if (!array_key_exists($normalized, $map)) {
                $map[$normalized] = $idx;
            }
        }

        return $map;
    }

    private function normalizeImportHeader($value): string
    {
        $s = trim((string) $value);

        // Remove UTF-8 BOM
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;

        $s = strtolower($s);
        $s = str_replace([' ', '-'], '_', $s);
        $s = preg_replace('/[^a-z0-9_]/', '', $s) ?? $s;
        return $s;
    }

    private function readCsvRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membaca file CSV');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $delimiterCandidates = [',', ';', "\t"];
        $bestDelimiter = ',';
        $bestCount = 0;

        foreach ($delimiterCandidates as $d) {
            $count = count(str_getcsv($firstLine, $d));
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestDelimiter = $d;
            }
        }

        rewind($handle);

        $rows = [];
        while (($data = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Remove the specified kuis
     * DELETE /api/kuis/{id}
     */
    public function destroy(string $id)
    {
        try {
            $kuis = Kuis::find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $kuis->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kuis berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kuis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload image for soal (Guru/Admin)
     * POST /api/kuis/{id}/soal-image
     * multipart/form-data: image
     */
    public function uploadSoalImage(Request $request, string $id)
    {
        try {
            $kuis = Kuis::find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'image' => 'required|file|image|mimes:jpeg,jpg,png,webp|max:5120',
            ], [
                'image.required' => 'File gambar wajib diisi',
                'image.image' => 'File harus berupa gambar',
                'image.mimes' => 'Format gambar harus jpeg/jpg/png/webp',
                'image.max' => 'Ukuran gambar maksimal 5MB',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('image');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = (string) Str::uuid() . '.' . $ext;

            $relativeDir = 'soal-images/' . $kuis->id;
            $absoluteDir = storage_path('app/public/' . $relativeDir);

            if (!is_dir($absoluteDir)) {
                mkdir($absoluteDir, 0775, true);
            }

            $file->move($absoluteDir, $filename);

            $relativePath = $relativeDir . '/' . $filename;
            $publicUrl = url('/storage/' . $relativePath);

            return response()->json([
                'success' => true,
                'message' => 'Upload gambar berhasil',
                'data' => [
                    'path' => $relativePath,
                    'url' => $publicUrl,
                ]
            ], 200);
        } catch (\Exception $e) {
            if (app()->environment('local')) {
                Log::error('Upload soal image failed', [
                    'kuis_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal upload gambar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit kuis answer (Siswa)
     * POST /api/kuis/{id}/submit
     * 
     * @deprecated Gunakan KuisAttemptController::submit() via POST /kuis/{id}/attempts/{attemptId}/submit
     * Endpoint ini tetap ada untuk backward compatibility tapi akan menyimpan ke KuisAttempt
     */
    public function submit(Request $request, string $id)
    {
        try {
            $normalizedId = $this->normalizeKuisId($id);
            $kuis = Kuis::with('soal')->find($normalizedId);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'siswa_id' => 'required|exists:users,id',
                'jawaban' => 'required|array',
                'waktu_mulai' => 'nullable|date',
                'waktu_selesai' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Hitung nilai
            $benar = 0;
            $salah = 0;
            $detail = [];

            foreach ($kuis->soal as $soal) {
                $jawabanSiswa = $request->jawaban[$soal->id] ?? null;
                $isBenar = $jawabanSiswa === $soal->jawaban;

                if ($isBenar) {
                    $benar++;
                } else {
                    $salah++;
                }

                $detail[] = [
                    'soal_id' => $soal->id,
                    'jawaban_siswa' => $jawabanSiswa,
                    'jawaban_benar' => $soal->jawaban,
                    'benar' => $isBenar
                ];
            }

            $totalSoal = $kuis->soal->count();
            $nilai = $totalSoal > 0 ? ($benar / $totalSoal) * 100 : 0;
            $nilai = round($nilai, 2);

            // Save ke KuisAttempt (bukan HasilKuis)
            $attempt = KuisAttempt::create([
                'kuis_id' => $normalizedId,
                'siswa_id' => $request->siswa_id,
                'started_at' => $request->waktu_mulai ?? now(),
                'ends_at' => now()->addHour(), // Default 1 jam
                'submitted_at' => $request->waktu_selesai ?? now(),
                'status' => 'submitted',
                'score' => $nilai,
                'benar' => $benar,
                'salah' => $salah,
                'total_soal' => $totalSoal,
                'answers' => $request->jawaban,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kuis berhasil disubmit (legacy endpoint, please use /attempts/{id}/submit)',
                'data' => [
                    'attempt_id' => $attempt->id,
                    'nilai' => $nilai,
                    'benar' => $benar,
                    'salah' => $salah,
                    'detail' => $detail
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
     * Get nilai kuis siswa
     * GET /api/kuis/{id}/nilai
     * Query params: kelas, kelas_id, siswa_id
     * 
     * NOTE: Sekarang menggunakan KuisAttempt (bukan HasilKuis yang deprecated)
     */
    public function getNilai(Request $request, string $id)
    {
        try {
            $normalizedId = $this->normalizeKuisId($id);
            $kuis = Kuis::find($normalizedId);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            // Query dari KuisAttempt (hanya yang sudah submitted/expired)
            $query = \App\Models\KuisAttempt::where('kuis_id', $normalizedId)
                ->whereIn('status', ['submitted', 'expired'])
                ->with(['siswa' => function($q) {
                    $q->select('id', 'name', 'nis', 'kelas', 'kelas_id', 'jurusan_id');
                }]);

            // Filter by siswa_id
            if ($request->has('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            // Filter by kelas_id (preferred)
            if ($request->has('kelas_id')) {
                $query->whereHas('siswa', function($q) use ($request) {
                    $q->where('kelas_id', $request->kelas_id);
                });
            } elseif ($request->has('kelas')) {
                // Legacy: filter by kelas string
                $query->whereHas('siswa', function($q) use ($request) {
                    $q->where('kelas', $request->kelas);
                });
            }

            $attempts = $query->orderBy('score', 'desc')->get();

            $data = $attempts->map(function($a) {
                return [
                    'attempt_id' => $a->id,
                    'siswa_id' => $a->siswa_id,
                    'siswa_nama' => $a->siswa->name ?? null,
                    'siswa_nis' => $a->siswa->nis ?? null,
                    'siswa_kelas' => $a->siswa->kelas ?? null,
                    'siswa_kelas_id' => $a->siswa->kelas_id ?? null,
                    'nilai' => $a->score,
                    'benar' => $a->benar,
                    'salah' => $a->salah,
                    'total_soal' => $a->total_soal,
                    'status' => $a->status,
                    'started_at' => $a->started_at,
                    'submitted_at' => $a->submitted_at,
                    'created_at' => $a->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize kelas input (bisa string JSON atau array).
     */
    private function normalizeKelas($kelas): array
    {
        if (is_array($kelas)) {
            $normalized = $kelas;
        } elseif (is_string($kelas)) {
            $trimmed = trim($kelas);
            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $normalized = $decoded;
            } elseif (str_contains($trimmed, ',')) {
                $normalized = array_map('trim', explode(',', $trimmed));
            } else {
                $normalized = [$trimmed];
            }
        } else {
            $normalized = [$kelas];
        }

        $normalized = array_values(array_filter($normalized, fn ($v) => is_string($v) ? trim($v) !== '' : $v !== null));
        return $normalized;
    }

    private function normalizePilihan($pilihan): array
    {
        if (is_string($pilihan)) {
            $decoded = json_decode(trim($pilihan), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $pilihan = $decoded;
            }
        }

        if (!is_array($pilihan)) {
            return [];
        }

        // If FE sends list like ["a","b","c","d","e"], map to A..E
        if (array_is_list($pilihan)) {
            $letters = ['A', 'B', 'C', 'D', 'E'];
            $mapped = [];

            // If FE sends list of objects like [{key:"A", value:"..."}, ...]
            $allObjectsWithKey = true;
            foreach ($pilihan as $item) {
                if (!is_array($item)) {
                    $allObjectsWithKey = false;
                    break;
                }
                $k = $item['key'] ?? $item['label'] ?? $item['option'] ?? null;
                if (!is_string($k) || trim($k) === '') {
                    $allObjectsWithKey = false;
                    break;
                }
            }

            if ($allObjectsWithKey) {
                foreach ($pilihan as $item) {
                    $k = strtoupper(trim((string) ($item['key'] ?? $item['label'] ?? $item['option'])));
                    if (!in_array($k, $letters, true)) {
                        continue;
                    }
                    $v = $item['text'] ?? $item['value'] ?? $item['answer'] ?? '';
                    $mapped[$k] = is_string($v) ? $v : (string) $v;
                }
                return $mapped;
            }

            foreach ($letters as $idx => $letter) {
                if (array_key_exists($idx, $pilihan) && $pilihan[$idx] !== null) {
                    $value = $pilihan[$idx];
                    if (is_array($value)) {
                        $value = $value['text'] ?? $value['value'] ?? $value['label'] ?? '';
                    }
                    $mapped[$letter] = is_string($value) ? $value : (string) $value;
                }
            }
            return $mapped;
        }

        // Normalize keys to uppercase A..E
        $mapped = [];
        foreach ($pilihan as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $upperKey = strtoupper(trim($key));
            if (in_array($upperKey, ['A', 'B', 'C', 'D', 'E'], true)) {
                if (is_array($value)) {
                    $value = $value['text'] ?? $value['value'] ?? $value['label'] ?? '';
                }
                $mapped[$upperKey] = is_string($value) ? $value : (string) $value;
            }
        }
        return $mapped;
    }

    private function normalizeJawaban($jawaban): ?string
    {
        if ($jawaban === null) {
            return null;
        }

        // Numeric index (0-4 or 1-5)
        if (is_int($jawaban) || (is_string($jawaban) && preg_match('/^\d+$/', trim($jawaban)))) {
            $n = (int) $jawaban;
            $map0 = ['A', 'B', 'C', 'D', 'E'];
            if ($n >= 0 && $n <= 4) {
                return $map0[$n];
            }
            if ($n >= 1 && $n <= 5) {
                return $map0[$n - 1];
            }
        }

        if (!is_string($jawaban)) {
            return null;
        }

        $s = strtoupper(trim($jawaban));

        // Direct letter
        if (in_array($s, ['A', 'B', 'C', 'D', 'E'], true)) {
            return $s;
        }

        // If contains a letter A-E somewhere (e.g. "Pilihan B", "B)")
        if (preg_match('/[A-E]/', $s, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    private function resolveJawaban($jawaban, array $pilihan): ?string
    {
        $letter = $this->normalizeJawaban($jawaban);
        if ($letter !== null) {
            return $letter;
        }

        // If FE sends the option text as the answer, map it back to its letter
        if (is_string($jawaban)) {
            $needle = trim($jawaban);
            if ($needle !== '') {
                foreach ($pilihan as $k => $v) {
                    if (!is_string($k)) {
                        continue;
                    }
                    $kk = strtoupper(trim($k));
                    if (!in_array($kk, ['A', 'B', 'C', 'D', 'E'], true)) {
                        continue;
                    }
                    if (trim((string) $v) === $needle) {
                        return $kk;
                    }
                }
            }
        }

        return null;
    }

    private function isDraftStatus(?string $status): bool
    {
        return strtoupper((string) $status) === 'DRAFT';
    }

    private function isCompleteSoal($soal): bool
    {
        if (!is_array($soal)) {
            return false;
        }

        $pertanyaan = isset($soal['pertanyaan']) ? trim((string) $soal['pertanyaan']) : '';
        $jawaban = isset($soal['jawaban']) ? strtoupper(trim((string) $soal['jawaban'])) : '';
        $pilihan = isset($soal['pilihan']) && is_array($soal['pilihan']) ? $soal['pilihan'] : [];

        if ($pertanyaan === '' || !in_array($jawaban, ['A', 'B', 'C', 'D', 'E'], true)) {
            return false;
        }

        foreach (['A', 'B', 'C', 'D'] as $k) {
            if (!array_key_exists($k, $pilihan) || trim((string) $pilihan[$k]) === '') {
                return false;
            }
        }

        if (!array_key_exists($jawaban, $pilihan) || trim((string) $pilihan[$jawaban]) === '') {
            return false;
        }

        return true;
    }

    private function getDisplaySoalCount($kuis, int $savedSoalCount): int
    {
        $draftCount = (int) ($kuis->draft_soal_count ?? 0);
        return max($savedSoalCount, $draftCount);
    }
}
