<?php

namespace App\Http\Controllers;

use App\Models\PBL;
use App\Models\Kelompok;
use App\Models\PBLSubmission;
use App\Models\PBLProgress;
use App\Models\User;
use App\Models\PBLSintaks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PBLController extends Controller
{
    private function guruCanManageProject($user, PBL $project): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'guru' && (int) $project->created_by === (int) $user->id;
    }

    private function siswaIsMemberOfKelompok($user, ?Kelompok $kelompok): bool
    {
        if (!$user || $user->role !== 'siswa' || !$kelompok) {
            return false;
        }

        if (!is_array($kelompok->anggota)) {
            return false;
        }

        foreach ($kelompok->anggota as $anggota) {
            $anggotaId = str_replace('siswa-', '', (string) $anggota);
            if ($anggotaId == $user->id || (string) $anggota == (string) $user->id) {
                return true;
            }
        }

        return false;
    }

    private function siswaCanAccessProject($user, PBL $project): bool
    {
        if (!$user || $user->role !== 'siswa') {
            return true;
        }

        if ($project->status !== 'Aktif') {
            return false;
        }

        // Cek akses via pivot table kelasRelation (many-to-many)
        if ($user->kelas_id) {
            $kelasIds = $project->kelasRelation->pluck('id')->toArray();
            if (!empty($kelasIds) && !in_array($user->kelas_id, $kelasIds)) {
                return false;
            }
        }

        // Legacy check: kelas string (X/XI/XII)
        if (empty($project->kelasRelation->count()) && !empty($user->kelas) && $project->kelas !== $user->kelas) {
            return false;
        }

        if (!empty($user->jurusan_id) && $project->jurusan_id && $project->jurusan_id !== $user->jurusan_id) {
            return false;
        }

        return true;
    }

    private function defaultSintaksTemplate(): array
    {
        return [
            [
                'judul' => 'Orientasi Masalah',
                'instruksi' => 'Baca studi kasus/masalah. Identifikasi konteks, batasan, dan tujuan awal.',
                'urutan' => 1,
            ],
            [
                'judul' => 'Merumuskan Masalah & Hipotesis',
                'instruksi' => 'Rumuskan pertanyaan masalah. Buat dugaan solusi/hipotesis dan rencana kerja.',
                'urutan' => 2,
            ],
            [
                'judul' => 'Pengumpulan Data',
                'instruksi' => 'Kumpulkan informasi/referensi, lakukan observasi/eksperimen seperlunya. Catat sumber.',
                'urutan' => 3,
            ],
            [
                'judul' => 'Analisis & Pengembangan Solusi',
                'instruksi' => 'Analisis data, susun solusi, buat artefak (laporan/prototipe) dan uji hasilnya.',
                'urutan' => 4,
            ],
            [
                'judul' => 'Presentasi & Refleksi',
                'instruksi' => 'Presentasikan hasil. Refleksikan proses, kendala, dan perbaikan untuk iterasi berikutnya.',
                'urutan' => 5,
            ],
        ];
    }

    /**
     * Access rule khusus leaderboard untuk siswa.
     * Tidak mensyaratkan status project aktif, cukup terhubung via kelas/jurusan
     * atau anggota kelompok pada project tersebut.
     */
    private function siswaCanAccessLeaderboard($user, PBL $project): bool
    {
        if (!$user || $user->role !== 'siswa') {
            return true;
        }

        // Opsi aman: siswa anggota kelompok di project ini
        $inKelompok = Kelompok::where('pbl_id', $project->id)
            ->where(function ($q) use ($user) {
                $q->whereJsonContains('anggota', $user->id)
                    ->orWhereJsonContains('anggota', (string) $user->id)
                    ->orWhereJsonContains('anggota', 'siswa-' . $user->id);
            })
            ->exists();

        if ($inKelompok) {
            return true;
        }

        // Opsi aman: project memang ditujukan ke kelas siswa
        $kelasAllowed = true;
        if ($user->kelas_id) {
            $kelasIds = $project->kelasRelation->pluck('id')->toArray();
            if (!empty($kelasIds)) {
                $kelasAllowed = in_array($user->kelas_id, $kelasIds);
            } elseif (!empty($project->kelas) && !empty($user->kelas)) {
                // Fallback legacy saat pivot belum diisi
                if (is_array($project->kelas)) {
                    $kelasAllowed = in_array($user->kelas, $project->kelas);
                } else {
                    $kelasAllowed = ((string) $project->kelas === (string) $user->kelas);
                }
            }
        }

        if (!$kelasAllowed) {
            return false;
        }

        if (!empty($user->jurusan_id) && $project->jurusan_id && $project->jurusan_id !== $user->jurusan_id) {
            return false;
        }

        return true;
    }

    /**
     * Display a listing of PBL projects
     * GET /api/pbl
     * Query params: kelas, kelas_id, jurusan_id, status
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = PBL::with('jurusan:id,nama', 'creator:id,name,email', 'kelasRelation:id,nama,tingkat')
                ->withCount('sintaks as jumlah_sintaks');

            // Guru: hanya lihat PBL yang dia buat (admin tetap lihat semua)
            if ($user && $user->role === 'guru') {
                $query->where('created_by', $user->id);
            }

            // Siswa: hanya lihat PBL yang aktif & sesuai kelas mereka (via pivot)
            if ($user && $user->role === 'siswa') {
                $query->where('status', 'Aktif');
                
                // Filter by kelas_id via pivot table
                if ($user->kelas_id) {
                    $query->where(function($q) use ($user) {
                        $q->whereHas('kelasRelation', function($subQ) use ($user) {
                            $subQ->where('kelas.id', $user->kelas_id);
                        })
                        // Fallback: jika belum ada kelas di pivot, cek legacy kelas field
                        ->orWhere(function($subQ) use ($user) {
                            $subQ->doesntHave('kelasRelation');
                            if ($user->kelas) {
                                $subQ->where('kelas', $user->kelas);
                            }
                        });
                    });
                }
                
                if (!empty($user->jurusan_id)) {
                    $query->where('jurusan_id', $user->jurusan_id);
                }
            }

            // Filter by kelas_id (via pivot)
            if ($request->has('kelas_id')) {
                $query->whereHas('kelasRelation', function($q) use ($request) {
                    $q->where('kelas.id', $request->kelas_id);
                });
            } elseif ($request->has('kelas')) {
                // Legacy: filter by kelas string
                $query->where('kelas', $request->kelas);
            }

            // Filter by jurusan
            if ($request->has('jurusan_id')) {
                $query->where('jurusan_id', $request->jurusan_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $projects = $query->orderBy('created_at', 'desc')->get();

            $data = $projects->map(function($p) {
                $deadline = $p->deadline;
                return [
                    'id' => $p->id,
                    'judul' => $p->judul,
                    'masalah' => $p->masalah,
                    'tujuan_pembelajaran' => $p->tujuan_pembelajaran,
                    'panduan' => $p->panduan,
                    'referensi' => $p->referensi,
                    'kelas' => $p->kelas, // Legacy field
                    'kelas_list' => $p->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $p->kelasRelation->pluck('id'),
                    'jurusan_id' => $p->jurusan_id,
                    'jurusan' => $p->jurusan ? [
                        'id' => $p->jurusan->id,
                        'nama' => $p->jurusan->nama
                    ] : null,
                    'status' => $p->status,
                    'deadline' => $deadline
                        ? (method_exists($deadline, 'format') ? $deadline->format('Y-m-d') : (string) $deadline)
                        : null,
                    'jumlah_sintaks' => (int) ($p->jumlah_sintaks ?? 0),
                    'created_by' => $p->creator ? $p->creator->email : null,
                    'created_at' => $p->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data PBL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created PBL project
     * POST /api/pbl
     * 
     * Supports kelas_ids (array of kelas IDs) for many-to-many relationship
     */
    public function store(Request $request)
    {
        try {
            // Support kelas_ids (array) untuk many-to-many
            $kelasIds = $request->input('kelas_ids', []);
            
            // Normalize kelas: bisa terima kelas_id (integer) atau tingkat (X/XI/XII)
            $kelas = $request->input('kelas');
            $kelasId = $request->input('kelas_id');
            $jurusanId = $request->input('jurusan_id');
            
            // Jika frontend kirim kelas sebagai ID integer (bukan X/XI/XII), treat sebagai kelas_id
            if (is_numeric($kelas) && !in_array($kelas, ['X', 'XI', 'XII'])) {
                $kelasId = $kelas;
                $kelas = null;
            }
            
            // Jika ada kelas_id tunggal, tambahkan ke kelas_ids
            if ($kelasId && !in_array($kelasId, $kelasIds)) {
                $kelasIds[] = $kelasId;
            }
            
            // Jika ada kelas_ids, ambil data tingkat untuk legacy field
            if (!empty($kelasIds)) {
                $kelasData = \App\Models\Kelas::whereIn('id', $kelasIds)->get();
                if ($kelasData->isNotEmpty()) {
                    // Ambil tingkat unik untuk legacy field
                    $tingkatList = $kelasData->pluck('tingkat')->unique()->values()->toArray();
                    $kelas = implode(',', $tingkatList);
                    
                    // Jika jurusan_id belum diset, ambil dari kelas pertama
                    if (!$jurusanId && $kelasData->first()->jurusan_id) {
                        $jurusanId = $kelasData->first()->jurusan_id;
                    }
                }
            } elseif ($kelasId) {
                $kelasData = \App\Models\Kelas::find($kelasId);
                if ($kelasData) {
                    $kelas = $kelasData->tingkat;
                    if (!$jurusanId) {
                        $jurusanId = $kelasData->jurusan_id;
                    }
                }
            }
            
            // Jika kelas masih berupa nama penuh (misal "X RPL 1"), extract tingkatnya
            if ($kelas && !in_array($kelas, ['X', 'XI', 'XII']) && !str_contains($kelas, ',')) {
                if (preg_match('/^(X|XI|XII)/i', $kelas, $matches)) {
                    $kelas = strtoupper($matches[1]);
                }
            }
            
            // Normalize jurusan_id: bisa terima nama (RPL, TKJ) atau ID (JUR-1)
            if ($jurusanId && !str_starts_with((string)$jurusanId, 'JUR-') && !is_numeric($jurusanId)) {
                // Cari berdasarkan nama jurusan
                $jurusan = \App\Models\Jurusan::where('nama', $jurusanId)->first();
                if ($jurusan) {
                    $jurusanId = $jurusan->id;
                }
            }
            
            // Merge normalized values
            $request->merge([
                'kelas' => $kelas ?: 'X', // Default X jika tidak ada
                'jurusan_id' => $jurusanId,
            ]);
            
            $validator = Validator::make($request->all(), [
                'judul' => 'required|string|max:255',
                'masalah' => 'nullable|string',
                'tujuan_pembelajaran' => 'nullable|string',
                'panduan' => 'nullable|string',
                'referensi' => 'nullable|string',
                'kelas' => 'required|string', // Allow comma-separated for multiple tingkat
                'kelas_ids' => 'nullable|array',
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'jurusan_id' => 'nullable|exists:jurusans,id',
                'status' => 'sometimes|in:Draft,Aktif,Selesai',
                'deadline' => 'nullable|date',
                'sintaks' => 'nullable|array',
                'sintaks.*.judul' => 'required_with:sintaks|string|max:255',
                'sintaks.*.instruksi' => 'nullable|string',
                'sintaks.*.urutan' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                \Log::error('PBL Store Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $request->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $project = PBL::create([
                'judul' => $request->judul,
                'masalah' => $request->masalah,
                'tujuan_pembelajaran' => $request->tujuan_pembelajaran,
                'panduan' => $request->panduan,
                'referensi' => $request->referensi,
                'kelas' => $request->kelas,
                'jurusan_id' => $request->jurusan_id,
                'status' => $request->status ?? 'Draft',
                'deadline' => $request->deadline,
                'created_by' => auth()->id()
            ]);

            // Sync kelas_ids ke pivot table
            if (!empty($kelasIds)) {
                $project->kelasRelation()->sync($kelasIds);
            }

            $steps = null;
            if ($request->filled('sintaks') && is_array($request->sintaks) && count($request->sintaks) > 0) {
                $steps = $request->sintaks;
            } else {
                // If FE doesn't send sintaks, create a sensible default template.
                $steps = $this->defaultSintaksTemplate();
            }

            if (is_array($steps) && count($steps) > 0) {
                foreach (array_values($steps) as $idx => $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $urutan = isset($step['urutan']) ? (int) $step['urutan'] : ($idx + 1);
                    PBLSintaks::create([
                        'id' => (string) Str::uuid(),
                        'pbl_id' => $project->id,
                        'judul' => (string) ($step['judul'] ?? ''),
                        'instruksi' => isset($step['instruksi']) ? (string) $step['instruksi'] : null,
                        'urutan' => $urutan,
                    ]);
                }
            }

            DB::commit();

            $project->load('jurusan:id,nama', 'sintaks', 'kelasRelation:id,nama,tingkat');
            
            // Format response dengan kelas_list
            $responseData = $project->toArray();
            $responseData['kelas_list'] = $project->kelasRelation->map(fn($k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'tingkat' => $k->tingkat
            ]);
            $responseData['kelas_ids'] = $project->kelasRelation->pluck('id');

            return response()->json([
                'success' => true,
                'message' => 'Project PBL berhasil dibuat',
                'data' => $responseData
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat project PBL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified PBL project
     * GET /api/pbl/{id}
     */
    public function show(string $id)
    {
        try {
            $project = PBL::with('jurusan:id,nama', 'creator:id,name,email', 'sintaks', 'kelasRelation:id,nama,tingkat')
                ->withCount('sintaks as jumlah_sintaks')
                ->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            if (!$this->siswaCanAccessLeaderboard($user, $project)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses dibatasi untuk leaderboard project ini'
                ], 403);
            }

            // Format response dengan kelas_list
            $responseData = $project->toArray();
            $responseData['kelas_list'] = $project->kelasRelation->map(fn($k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'tingkat' => $k->tingkat
            ]);
            $responseData['kelas_ids'] = $project->kelasRelation->pluck('id');

            return response()->json([
                'success' => true,
                'data' => $responseData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data project PBL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified PBL project
     * PUT /api/pbl/{id}
     * 
     * Supports kelas_ids (array of kelas IDs) for many-to-many relationship
     */
    public function update(Request $request, string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            // Handle kelas_ids untuk many-to-many
            $kelasIds = $request->input('kelas_ids');
            $kelas = $request->input('kelas');
            
            // Jika ada kelas_ids, update legacy kelas field
            if (is_array($kelasIds) && !empty($kelasIds)) {
                $kelasData = \App\Models\Kelas::whereIn('id', $kelasIds)->get();
                if ($kelasData->isNotEmpty()) {
                    $tingkatList = $kelasData->pluck('tingkat')->unique()->values()->toArray();
                    $kelas = implode(',', $tingkatList);
                    $request->merge(['kelas' => $kelas]);
                }
            }

            $validator = Validator::make($request->all(), [
                'judul' => 'sometimes|string|max:255',
                'masalah' => 'sometimes|string',
                'tujuan_pembelajaran' => 'sometimes|string',
                'panduan' => 'sometimes|string',
                'referensi' => 'nullable|string',
                'kelas' => 'sometimes|string', // Allow comma-separated
                'kelas_ids' => 'nullable|array',
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'jurusan_id' => 'sometimes|exists:jurusans,id',
                'status' => 'sometimes|in:Draft,Aktif,Selesai',
                'deadline' => 'nullable|date',
                'sintaks' => 'sometimes|array',
                'sintaks.*.id' => 'nullable|string',
                'sintaks.*.judul' => 'required_with:sintaks|string|max:255',
                'sintaks.*.instruksi' => 'nullable|string',
                'sintaks.*.urutan' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $project->update($request->only([
                'judul', 'masalah', 'tujuan_pembelajaran', 'panduan', 
                'referensi', 'kelas', 'jurusan_id', 'status', 'deadline'
            ]));

            // Sync kelas_ids ke pivot table jika dikirim
            if ($request->has('kelas_ids')) {
                $project->kelasRelation()->sync($kelasIds ?? []);
            }

            // If FE sends sintaks array, replace all steps with the provided list.
            if ($request->has('sintaks') && is_array($request->sintaks)) {
                PBLSintaks::where('pbl_id', $project->id)->delete();

                foreach (array_values($request->sintaks) as $idx => $step) {
                    if (!is_array($step)) {
                        continue;
                    }
                    $urutan = isset($step['urutan']) ? (int) $step['urutan'] : ($idx + 1);
                    PBLSintaks::create([
                        'id' => (string) Str::uuid(),
                        'pbl_id' => $project->id,
                        'judul' => (string) ($step['judul'] ?? ''),
                        'instruksi' => isset($step['instruksi']) ? (string) $step['instruksi'] : null,
                        'urutan' => $urutan,
                    ]);
                }
            }

            DB::commit();

            $project->load('jurusan:id,nama', 'sintaks', 'kelasRelation:id,nama,tingkat');
            
            // Format response dengan kelas_list
            $responseData = $project->toArray();
            $responseData['kelas_list'] = $project->kelasRelation->map(fn($k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'tingkat' => $k->tingkat
            ]);
            $responseData['kelas_ids'] = $project->kelasRelation->pluck('id');

            return response()->json([
                'success' => true,
                'message' => 'Project PBL berhasil diupdate',
                'data' => $responseData
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate project PBL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sintaks steps for a PBL
     * GET /api/pbl/{id}/sintaks
     */
    public function getSintaks(string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            if (!$this->siswaCanAccessLeaderboard($user, $project)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses dibatasi untuk leaderboard project ini'
                ], 403);
            }

            $steps = PBLSintaks::where('pbl_id', $id)->orderBy('urutan')->get();

            return response()->json([
                'success' => true,
                'data' => $steps
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil sintaks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create/append a sintaks step
     * POST /api/pbl/{id}/sintaks
     */
    public function createSintaks(Request $request, string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'judul' => 'required|string|max:255',
                'instruksi' => 'nullable|string',
                'urutan' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $nextOrder = PBLSintaks::where('pbl_id', $id)->max('urutan');
            $urutan = $request->has('urutan') ? (int) $request->urutan : ((int) ($nextOrder ?? 0) + 1);

            $step = PBLSintaks::create([
                'id' => (string) Str::uuid(),
                'pbl_id' => $id,
                'judul' => $request->judul,
                'instruksi' => $request->instruksi,
                'urutan' => $urutan,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sintaks berhasil dibuat',
                'data' => $step
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat sintaks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a sintaks step
     * PUT /api/pbl/{id}/sintaks/{sintaksId}
     */
    public function updateSintaks(Request $request, string $id, string $sintaksId)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $step = PBLSintaks::where('pbl_id', $id)->where('id', $sintaksId)->first();
            if (!$step) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sintaks tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'judul' => 'sometimes|string|max:255',
                'instruksi' => 'nullable|string',
                'urutan' => 'sometimes|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $step->update($request->only(['judul', 'instruksi', 'urutan']));

            return response()->json([
                'success' => true,
                'message' => 'Sintaks berhasil diupdate',
                'data' => $step
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate sintaks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a sintaks step
     * DELETE /api/pbl/{id}/sintaks/{sintaksId}
     */
    public function destroySintaks(string $id, string $sintaksId)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $step = PBLSintaks::where('pbl_id', $id)->where('id', $sintaksId)->first();
            if (!$step) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sintaks tidak ditemukan'
                ], 404);
            }

            $step->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sintaks berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus sintaks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified PBL project
     * DELETE /api/pbl/{id}
     */
    public function destroy(string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $project->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project PBL berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus project PBL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get kelompok by project
     * GET /api/pbl/{id}/kelompok
     */
    public function getKelompok(string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $kelompok = Kelompok::where('pbl_id', $id)->get();

            $data = $kelompok->map(function($k) {
                if (!$k instanceof Kelompok) {
                    return null;
                }

                $anggotaDetails = $k->anggotaDetails();
                
                return [
                    'id' => $k->id,
                    'pbl_id' => $k->pbl_id,
                    'nama_kelompok' => $k->nama_kelompok,
                    'studi_kasus' => $k->studi_kasus,
                    'anggota' => $k->anggota,
                    'anggota_details' => $anggotaDetails->map(function($siswa) {
                        return [
                            'siswa_id' => 'siswa-' . $siswa->id,
                            'nama' => $siswa->name,
                            'nis' => $siswa->nis
                        ];
                    }),
                    'created_at' => $k->created_at
                ];
            })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data kelompok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create kelompok for project
     * POST /api/pbl/{id}/kelompok
     */
    public function createKelompok(Request $request, string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            // Normalize: frontend bisa kirim 'anggota', 'anggota_ids', atau 'anggota_kelompok'
            $anggota = $request->input('anggota') 
                ?? $request->input('anggota_ids') 
                ?? $request->input('anggota_kelompok');
            
            // Jika anggota adalah string (nama), convert ke array
            if (is_string($anggota)) {
                $anggota = [$anggota];
            }
            
            $request->merge(['anggota' => $anggota]);

            $validator = Validator::make($request->all(), [
                'nama_kelompok' => 'required|string|max:255',
                'studi_kasus' => 'nullable|string',
                'anggota' => 'required|array|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $kelompok = Kelompok::create([
                'pbl_id' => $id,
                'nama_kelompok' => $request->nama_kelompok,
                'studi_kasus' => $request->studi_kasus,
                'anggota' => $request->anggota
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Kelompok berhasil dibuat',
                'data' => $kelompok
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kelompok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update kelompok
     * PUT /api/pbl/{id}/kelompok/{kelompokId}
     */
    public function updateKelompok(Request $request, string $id, string $kelompokId)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $kelompok = Kelompok::where('pbl_id', $id)->where('id', $kelompokId)->first();

            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelompok tidak ditemukan'
                ], 404);
            }

            // Normalize: frontend bisa kirim 'anggota', 'anggota_ids', atau 'anggota_kelompok'
            $anggota = $request->input('anggota') 
                ?? $request->input('anggota_ids') 
                ?? $request->input('anggota_kelompok');
            
            // Jika anggota adalah string (nama), convert ke array
            if (is_string($anggota)) {
                $anggota = [$anggota];
            }
            
            if ($anggota) {
                $request->merge(['anggota' => $anggota]);
            }

            $validator = Validator::make($request->all(), [
                'nama_kelompok' => 'sometimes|required|string|max:255',
                'studi_kasus' => 'nullable|string',
                'anggota' => 'sometimes|required|array|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('nama_kelompok')) {
                $kelompok->nama_kelompok = $request->nama_kelompok;
            }
            if ($request->has('studi_kasus')) {
                $kelompok->studi_kasus = $request->studi_kasus;
            }
            if ($request->has('anggota')) {
                $kelompok->anggota = $request->anggota;
            }
            $kelompok->save();

            return response()->json([
                'success' => true,
                'message' => 'Kelompok berhasil diupdate',
                'data' => $kelompok
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate kelompok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete kelompok
     * DELETE /api/pbl/{id}/kelompok/{kelompokId}
     */
    public function deleteKelompok(string $id, string $kelompokId)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $kelompok = Kelompok::where('pbl_id', $id)->where('id', $kelompokId)->first();

            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelompok tidak ditemukan'
                ], 404);
            }

            $kelompok->delete();

            return response()->json([
                'success' => true,
                'message' => 'Kelompok berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus kelompok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit project (Siswa)
     * POST /api/pbl/{id}/submit
     */
    public function submit(Request $request, string $id)
    {
        try {
            // Get authenticated user
            $user = auth()->user();

            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            // Check authorization: siswa harus punya akses ke project
            if (!$this->siswaCanAccessProject($user, $project)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum terdaftar di kelompok manapun untuk project ini'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'kelompok_id' => 'required|exists:kelompoks,id',
                'file' => 'required|file|mimes:pdf,doc,docx,zip,rar,7z,ppt,pptx,xls,xlsx,jpg,jpeg,png|max:51200', // Max 50MB
                'catatan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify kelompok exists in this project
            $kelompok = Kelompok::where('id', $request->kelompok_id)
                ->where('pbl_id', $id)
                ->first();

            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelompok tidak ditemukan dalam project ini'
                ], 404);
            }

            // Check if siswa is member of this kelompok
            $isMember = false;
            if (is_array($kelompok->anggota)) {
                foreach ($kelompok->anggota as $anggota) {
                    // Support multiple formats: 'siswa-123', '123', or direct ID
                    $anggotaId = str_replace('siswa-', '', $anggota);
                    if ($anggotaId == $user->id || $anggota == $user->id) {
                        $isMember = true;
                        break;
                    }
                }
            }

            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan bagian dari kelompok ini'
                ], 403);
            }

            // Upload file
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('pbl_submissions', $fileName, 'public');

            $submission = PBLSubmission::create([
                'pbl_id' => $id,
                'kelompok_id' => $request->kelompok_id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'catatan' => $request->catatan
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Project berhasil dikumpulkan',
                'data' => $submission
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal submit project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get submissions by project
     * GET /api/pbl/{id}/submissions
     */
    public function getSubmissions(string $id)
    {
        try {
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $submissions = PBLSubmission::where('pbl_id', $id)
                ->with('kelompok')
                ->orderBy('submitted_at', 'desc')
                ->get();

            $data = $submissions->map(function($s) {
                $anggotaDetails = $s->kelompok ? $s->kelompok->anggotaDetails() : collect([]);
                
                return [
                    'id' => $s->id,
                    'pbl_id' => $s->pbl_id,
                    'kelompok_id' => $s->kelompok_id,
                    'kelompok' => $s->kelompok ? [
                        'nama_kelompok' => $s->kelompok->nama_kelompok,
                        'anggota' => $anggotaDetails->pluck('name')->toArray()
                    ] : null,
                    'file_name' => $s->file_name,
                    'file_size' => $s->file_size,
                    'catatan' => $s->catatan,
                    'nilai' => $s->nilai,
                    'feedback' => $s->feedback,
                    'submitted_at' => $s->submitted_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download file submission PBL
     * GET /api/pbl/submissions/{id}/download
     */
    public function downloadSubmission(string $id)
    {
        try {
            $submission = PBLSubmission::with(['pbl', 'kelompok'])->find($id);
            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            $project = $submission->pbl;
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $isMemberSiswa = $this->siswaIsMemberOfKelompok($user, $submission->kelompok);
            $canManage = $this->guruCanManageProject($user, $project);

            if (!$isMemberSiswa && !$canManage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            if (!$submission->file_path || !Storage::disk('public')->exists($submission->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File submission tidak ditemukan'
                ], 404);
            }

            $absolutePath = Storage::disk('public')->path($submission->file_path);
            return response()->download($absolutePath, $submission->file_name ?: basename($submission->file_path));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal download file submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get leaderboard by project (safe read-only)
     * GET /api/pbl/{id}/leaderboard
     */
    public function leaderboard(string $id)
    {
        try {
            $project = PBL::with('kelasRelation:id')->find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            if (!$this->siswaCanAccessProject($user, $project)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak'
                ], 403);
            }

            $kelompokList = Kelompok::where('pbl_id', $id)
                ->with(['submissions' => function($q) use ($id) {
                    $q->where('pbl_id', $id)
                        ->select('id', 'pbl_id', 'kelompok_id', 'nilai', 'submitted_at')
                        ->orderBy('submitted_at', 'desc');
                }])
                ->get();

            $sintaksList = PBLSintaks::where('pbl_id', $id)
                ->orderBy('urutan')
                ->get(['id', 'urutan']);
            $totalSintaks = $sintaksList->count();

            $progressMapByKelompok = PBLProgress::where('pbl_id', $id)
                ->get(['kelompok_id', 'sintaks_id', 'submitted_at'])
                ->groupBy('kelompok_id')
                ->map(function ($rows) {
                    return $rows->keyBy('sintaks_id');
                });

            $data = $kelompokList->map(function($kelompok) use ($sintaksList, $totalSintaks, $progressMapByKelompok) {
                $latestSubmission = $kelompok->submissions->first();
                $progressBySintaks = $progressMapByKelompok->get($kelompok->id, collect());

                $progress = $sintaksList->map(function ($sintaks) use ($progressBySintaks) {
                    $progressRow = $progressBySintaks->get($sintaks->id);

                    return [
                        'urutan' => (int) $sintaks->urutan,
                        'completed' => $progressRow !== null,
                        'submitted_at' => $progressRow?->submitted_at,
                    ];
                })->values();

                $completedSintaks = $progress->where('completed', true)->count();
                $completionPercentage = $totalSintaks > 0
                    ? (int) round(($completedSintaks / $totalSintaks) * 100)
                    : 0;
                $lastActivityAt = $progress
                    ->filter(fn ($item) => $item['completed'] && !empty($item['submitted_at']))
                    ->max('submitted_at');

                return [
                    'submission_id' => $latestSubmission?->id,
                    'kelompok_id' => $kelompok->id,
                    'kelompok' => [
                        'id' => $kelompok->id,
                        'nama_kelompok' => $kelompok->nama_kelompok,
                    ],
                    'completion_percentage' => $completionPercentage,
                    'last_activity_at' => $lastActivityAt,
                    'progress' => $progress,
                    // Backward compatibility for FE lama
                    'progress_percentage' => $completionPercentage,
                    'completed_sintaks' => $completedSintaks,
                    'total_sintaks' => $totalSintaks,
                    'submitted_at' => $lastActivityAt,
                    // Backward compatibility for FE lama yang masih baca field nilai
                    'nilai' => $completionPercentage,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil leaderboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nilai submission (Guru)
     * PUT /api/pbl/submissions/{id}/nilai
     */
    public function nilaiSubmission(Request $request, string $id)
    {
        try {
            $submission = PBLSubmission::find($id);

            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nilai' => 'required|integer|min:0|max:100',
                'feedback' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $submission->update([
                'nilai' => $request->nilai,
                'feedback' => $request->feedback
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nilai berhasil diberikan',
                'data' => $submission
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memberikan nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
