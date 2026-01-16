<?php

namespace App\Http\Controllers;

use App\Models\PBL;
use App\Models\Kelompok;
use App\Models\PBLSubmission;
use App\Models\User;
use App\Models\PBLSintaks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PBLController extends Controller
{
    private function siswaCanAccessProject($user, PBL $project): bool
    {
        if (!$user || $user->role !== 'siswa') {
            return true;
        }

        if ($project->status !== 'Aktif') {
            return false;
        }

        if (!empty($user->kelas) && $project->kelas !== $user->kelas) {
            return false;
        }

        if (!empty($user->jurusan_id) && $project->jurusan_id !== $user->jurusan_id) {
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
     * Display a listing of PBL projects
     * GET /api/pbl
     * Query params: kelas, jurusan_id, status
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = PBL::with('jurusan:id,nama', 'creator:id,name,email')
                ->withCount('sintaks as jumlah_sintaks');

            // Guru: hanya lihat PBL yang dia buat (admin tetap lihat semua)
            if ($user && $user->role === 'guru') {
                $query->where('created_by', $user->id);
            }

            // Siswa: hanya lihat PBL yang aktif & sesuai kelas/jurusan mereka.
            if ($user && $user->role === 'siswa') {
                $query->where('status', 'Aktif');
                if (!empty($user->kelas)) {
                    $query->where('kelas', $user->kelas);
                }
                if (!empty($user->jurusan_id)) {
                    $query->where('jurusan_id', $user->jurusan_id);
                }
            }

            // Filter by kelas
            if ($request->has('kelas')) {
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
                    'kelas' => $p->kelas,
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
     */
    public function store(Request $request)
    {
        try {
            // Normalize kelas: bisa terima kelas_id (integer) atau tingkat (X/XI/XII)
            $kelas = $request->input('kelas');
            $kelasId = $request->input('kelas_id');
            $jurusanId = $request->input('jurusan_id');
            
            // Jika frontend kirim kelas sebagai ID integer (bukan X/XI/XII), treat sebagai kelas_id
            if (is_numeric($kelas) && !in_array($kelas, ['X', 'XI', 'XII'])) {
                $kelasId = $kelas;
                $kelas = null;
            }
            
            // Jika ada kelas_id (ID dari tabel kelas), ambil data kelas
            if ($kelasId) {
                $kelasData = \App\Models\Kelas::find($kelasId);
                if ($kelasData) {
                    $kelas = $kelasData->tingkat; // X, XI, atau XII
                    // Jika jurusan_id belum diset, ambil dari kelas
                    if (!$jurusanId) {
                        $jurusanId = $kelasData->jurusan_id;
                    }
                }
            }
            
            // Jika kelas masih berupa nama penuh (misal "X RPL 1"), extract tingkatnya
            if ($kelas && !in_array($kelas, ['X', 'XI', 'XII'])) {
                if (preg_match('/^(X|XI|XII)/i', $kelas, $matches)) {
                    $kelas = strtoupper($matches[1]);
                }
            }
            
            // Normalize jurusan_id: bisa terima nama (RPL, TKJ) atau ID (JUR-1)
            if ($jurusanId && !str_starts_with((string)$jurusanId, 'JUR-')) {
                // Cari berdasarkan nama jurusan
                $jurusan = \App\Models\Jurusan::where('nama', $jurusanId)->first();
                if ($jurusan) {
                    $jurusanId = $jurusan->id;
                }
            }
            
            // Merge normalized values
            $request->merge([
                'kelas' => $kelas,
                'jurusan_id' => $jurusanId,
            ]);
            
            $validator = Validator::make($request->all(), [
                'judul' => 'required|string|max:255',
                'masalah' => 'nullable|string',
                'tujuan_pembelajaran' => 'nullable|string',
                'panduan' => 'nullable|string',
                'referensi' => 'nullable|string',
                'kelas' => 'required|in:X,XI,XII',
                'jurusan_id' => 'required|exists:jurusans,id',
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

            $project->load('jurusan:id,nama', 'sintaks');

            return response()->json([
                'success' => true,
                'message' => 'Project PBL berhasil dibuat',
                'data' => $project
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
            $project = PBL::with('jurusan:id,nama', 'creator:id,name,email', 'sintaks')
                ->withCount('sintaks as jumlah_sintaks')
                ->find($id);

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

            return response()->json([
                'success' => true,
                'data' => $project
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

            $validator = Validator::make($request->all(), [
                'judul' => 'sometimes|string|max:255',
                'masalah' => 'sometimes|string',
                'tujuan_pembelajaran' => 'sometimes|string',
                'panduan' => 'sometimes|string',
                'referensi' => 'nullable|string',
                'kelas' => 'sometimes|in:X,XI,XII',
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

            $project->load('jurusan:id,nama', 'sintaks');

            return response()->json([
                'success' => true,
                'message' => 'Project PBL berhasil diupdate',
                'data' => $project
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
            if (!$this->siswaCanAccessProject($user, $project)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak'
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
                $anggotaDetails = $k->anggotaDetails();
                
                return [
                    'id' => $k->id,
                    'pbl_id' => $k->pbl_id,
                    'nama_kelompok' => $k->nama_kelompok,
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
            });

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
            $project = PBL::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'kelompok_id' => 'required|exists:kelompoks,id',
                'file' => 'required|file|mimes:zip,rar,7z|max:51200', // Max 50MB
                'catatan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
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
