<?php

namespace App\Http\Controllers;

use App\Models\Kuis;
use App\Models\Soal;
use App\Models\HasilKuis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KuisController extends Controller
{
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
                    $q->from('hasil_kuis')
                        ->selectRaw('COUNT(DISTINCT siswa_id)')
                        ->whereColumn('hasil_kuis.kuis_id', 'kuis.id');
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
                $jumlahSoal = (int) ($k->jumlah_soal ?? 0);
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
                'kelas' => 'sometimes|array', // Legacy: array of tingkat (X, XI, XII)
                'kelas.*' => 'in:X,XI,XII',
                'kelas_ids' => 'sometimes|array', // New: array of kelas IDs
                'kelas_ids.*' => 'integer|exists:kelas,id',
                // FE kadang belum mengirim batas_waktu saat create
                'batas_waktu' => 'nullable|integer|min:1',
                'status' => 'sometimes|in:Draft,Aktif,Selesai',
                // FE boleh create kuis dulu tanpa soal; nanti bisa update via PUT /api/kuis/{id}
                'soal' => 'nullable|array',
            ], [
                'judul.required' => 'Judul kuis wajib diisi',
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

            $kuis = Kuis::create([
                'judul' => $payload['judul'],
                'kelas' => $kelas,
                'batas_waktu' => $payload['batas_waktu'] ?? 60,
                'status' => $payload['status'] ?? 'Draft',
                'created_by' => auth()->id()
            ]);

            // Create soal (optional)
            if (count($soalList) > 0) {
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

                foreach ($soalList as $index => $soalData) {
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

            // Sync kelas via pivot table
            if (isset($payload['kelas_ids']) && is_array($payload['kelas_ids'])) {
                $kuis->kelasRelation()->sync($payload['kelas_ids']);
            }

            $kuis->load('soal', 'kelasRelation:id,nama,tingkat');
            $kuis->loadCount('soal as jumlah_soal');

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
                    'jumlah_soal' => $kuis->jumlah_soal ?? 0,
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
     */
    public function show(string $id)
    {
        try {
            $kuis = Kuis::with(['soal', 'kelasRelation:id,nama,tingkat'])
                ->withCount('soal as jumlah_soal')
                ->find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $totalPeserta = HasilKuis::query()
                ->where('kuis_id', $kuis->id)
                ->distinct('siswa_id')
                ->count('siswa_id');

            return response()->json([
                'success' => true,
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
                    'total_peserta' => (int) $totalPeserta,
                    'jumlah_soal' => (int) ($kuis->jumlah_soal ?? 0),
                    'soal' => $kuis->soal->map(function ($s) {
                        return [
                            'id' => $s->id,
                            'pertanyaan' => $s->pertanyaan,
                            'image' => $s->image,
                            'pilihan' => $s->pilihan,
                            'jawaban' => $s->jawaban,
                            'urutan' => $s->urutan,
                        ];
                    }),
                    'created_at' => $kuis->created_at,
                ]
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
                'kelas' => 'sometimes', // Legacy: array of tingkat
                'kelas_ids' => 'sometimes|array', // New: array of kelas IDs
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
            if ($request->has('kelas')) $updateData['kelas'] = $payload['kelas'];
            if ($request->has('batas_waktu')) $updateData['batas_waktu'] = $request->batas_waktu;
            if ($request->has('status')) $updateData['status'] = $request->status;

            $kuis->update($updateData);

            // Update soal if provided and at least one question is actually filled.
            // If FE only sends empty placeholders, do nothing (do not wipe existing questions).
            if (array_key_exists('soal', $payload) && count($soalList) > 0) {
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

                // Replace existing questions
                Soal::where('kuis_id', $kuis->id)->delete();

                foreach ($soalList as $index => $soalData) {
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

            // Sync kelas via pivot table jika ada kelas_ids
            if (isset($payload['kelas_ids']) && is_array($payload['kelas_ids'])) {
                $kuis->kelasRelation()->sync($payload['kelas_ids']);
            }

            $kuis->load('soal', 'kelasRelation:id,nama,tingkat');

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
     */
    public function submit(Request $request, string $id)
    {
        try {
            $kuis = Kuis::with('soal')->find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'siswa_id' => 'required|exists:users,id',
                'jawaban' => 'required|array',
                'waktu_mulai' => 'required|date',
                'waktu_selesai' => 'required|date'
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

            // Save hasil
            HasilKuis::create([
                'kuis_id' => $kuis->id,
                'siswa_id' => $request->siswa_id,
                'jawaban' => $request->jawaban,
                'nilai' => round($nilai),
                'benar' => $benar,
                'salah' => $salah,
                'waktu_mulai' => $request->waktu_mulai,
                'waktu_selesai' => $request->waktu_selesai
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'nilai' => round($nilai),
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
     * Query params: kelas, siswa_id
     */
    public function getNilai(Request $request, string $id)
    {
        try {
            $kuis = Kuis::find($id);

            if (!$kuis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kuis tidak ditemukan'
                ], 404);
            }

            $query = HasilKuis::where('kuis_id', $id)
                ->with(['siswa' => function($q) {
                    $q->select('id', 'name', 'nis', 'kelas', 'jurusan_id');
                }]);

            // Filter by siswa_id
            if ($request->has('siswa_id')) {
                $query->where('siswa_id', $request->siswa_id);
            }

            // Filter by kelas (via siswa relationship)
            if ($request->has('kelas')) {
                $query->whereHas('siswa', function($q) use ($request) {
                    $q->where('kelas', $request->kelas);
                });
            }

            $hasil = $query->orderBy('nilai', 'desc')->get();

            $data = $hasil->map(function($h) {
                return [
                    'siswa_id' => $h->siswa_id,
                    'siswa_nama' => $h->siswa->name ?? null,
                    'siswa_nis' => $h->siswa->nis ?? null,
                    'siswa_kelas' => $h->siswa->kelas ?? null,
                    'nilai' => $h->nilai,
                    'benar' => $h->benar,
                    'salah' => $h->salah,
                    'waktu_mulai' => $h->waktu_mulai,
                    'waktu_selesai' => $h->waktu_selesai,
                    'created_at' => $h->created_at
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
}
