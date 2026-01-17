<?php

namespace App\Http\Controllers;

use App\Models\Materi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class MateriController extends Controller
{
    /**
     * Display a listing of materi
     * GET /api/materi
     * Query params: kelas, kelas_id, status
     * Siswa: hanya lihat yang dipublikasikan & sesuai kelasnya (via pivot table)
     * Guru/Admin: lihat semua
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $query = Materi::with('kelasRelation:id,nama,tingkat');

            // Guru hanya melihat materi yang dia buat (admin tetap lihat semua)
            if ($user && $user->role === 'guru') {
                $query->where('created_by', $user->id);
            }

            // Siswa hanya bisa lihat materi yang dipublikasikan & sesuai kelas_id
            if ($user->role === 'siswa') {
                // DB enum uses "Published"; keep backward-compat if legacy rows exist.
                $query->whereIn('status', ['Published', 'Dipublikasikan']);
                
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

            $materi = $query->orderBy('created_at', 'desc')->get();

            $data = $materi->map(function($m) {
                return [
                    'id' => $m->id,
                    'judul' => $m->judul,
                    'deskripsi' => $m->deskripsi,
                    // Legacy field (array of tingkat) - for backward compat
                    'kelas' => $m->kelas,
                    // New: array of kelas objects from pivot
                    'kelas_list' => $m->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $m->kelasRelation->pluck('id'),
                    'file_name' => $m->file_name,
                    'file_size' => $m->file_size,
                    'status' => $m->status,
                    'created_at' => $m->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created materi with file upload
     * POST /api/materi
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'judul' => 'required|string|max:255',
                'deskripsi' => 'nullable|string',
                'kelas' => 'sometimes', // Legacy: array of tingkat (X, XI, XII)
                'kelas_ids' => 'sometimes|array', // New: array of kelas IDs
                'kelas_ids.*' => 'integer|exists:kelas,id',
                // Optional (FE form doesn't always send it)
                'jurusan_id' => 'nullable|exists:jurusans,id',
                // Accept FE label "Dipublikasikan" but persist as DB enum value "Published"
                'status' => 'sometimes|in:Draft,Published,Archived,Dipublikasikan',
                'file' => 'nullable|file|mimes:pdf|max:10240' // Max 10MB
            ], [
                'judul.required' => 'Judul materi wajib diisi',
                'file.mimes' => 'File harus berformat PDF',
                'file.max' => 'File maksimal 10MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $kelas = $request->has('kelas') ? $this->normalizeKelas($request->kelas) : [];
            $status = $this->normalizeStatus($request->status ?? 'Draft');

            // Handle file upload if present
            $fileName = null;
            $filePath = null;
            $fileSize = null;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $storedName = time() . '_' . $originalName;
                $filePath = $file->storeAs('materi', $storedName, 'public');
                $fileSize = $file->getSize();

                // Store original name for download/display
                $fileName = $originalName;
            }

            $materi = Materi::create([
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'kelas' => $kelas,
                'jurusan_id' => $request->jurusan_id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'status' => $status,
                'created_by' => auth()->id()
            ]);

            // Sync kelas via pivot table
            if ($request->has('kelas_ids') && is_array($request->kelas_ids)) {
                $materi->kelasRelation()->sync($request->kelas_ids);
            }

            // Load kelas relation for response
            $materi->load('kelasRelation:id,nama,tingkat');

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil dibuat',
                'data' => [
                    'id' => $materi->id,
                    'judul' => $materi->judul,
                    'kelas' => $materi->kelas,
                    'kelas_list' => $materi->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $materi->kelasRelation->pluck('id'),
                    'jurusan_id' => $materi->jurusan_id,
                    'file_name' => $materi->file_name,
                    'file_size' => $materi->file_size,
                    'status' => $materi->status,
                    'created_at' => $materi->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified materi
     * GET /api/materi/{id}
     */
    public function show(string $id)
    {
        try {
            $materi = Materi::with('kelasRelation:id,nama,tingkat')->find($id);

            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $materi->id,
                    'judul' => $materi->judul,
                    'deskripsi' => $materi->deskripsi,
                    'kelas' => $materi->kelas,
                    'kelas_list' => $materi->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $materi->kelasRelation->pluck('id'),
                    'file_name' => $materi->file_name,
                    'file_size' => $materi->file_size,
                    'status' => $materi->status,
                    'created_at' => $materi->created_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified materi
     * PUT/PATCH /api/materi/{id}
     */
    public function update(Request $request, string $id)
    {
        try {
            $materi = Materi::find($id);

            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'judul' => 'sometimes|string|max:255',
                'deskripsi' => 'nullable|string',
                'kelas' => 'sometimes', // Legacy: array of tingkat
                'kelas_ids' => 'sometimes|array', // New: array of kelas IDs
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'jurusan_id' => 'sometimes|exists:jurusans,id',
                'status' => 'sometimes|in:Draft,Published,Archived,Dipublikasikan',
                'file' => 'nullable|file|mimes:pdf|max:10240'
            ], [
                'file.mimes' => 'File harus berformat PDF',
                'file.max' => 'File maksimal 10MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('judul')) $updateData['judul'] = $request->judul;
            if ($request->has('deskripsi')) $updateData['deskripsi'] = $request->deskripsi;
            if ($request->has('jurusan_id')) $updateData['jurusan_id'] = $request->jurusan_id;
            if ($request->has('status')) $updateData['status'] = $this->normalizeStatus($request->status);
            
            if ($request->has('kelas')) {
                $updateData['kelas'] = $this->normalizeKelas($request->kelas);
            }

            // Update file if new file uploaded
            if ($request->hasFile('file')) {
                // Delete old file
                if ($materi->file_path && Storage::disk('public')->exists($materi->file_path)) {
                    Storage::disk('public')->delete($materi->file_path);
                }

                // Upload new file
                $file = $request->file('file');
                $originalName = $file->getClientOriginalName();
                $storedName = time() . '_' . $originalName;
                $filePath = $file->storeAs('materi', $storedName, 'public');

                $updateData['file_name'] = $originalName;
                $updateData['file_path'] = $filePath;
                $updateData['file_size'] = $file->getSize();
            }

            $materi->update($updateData);

            // Sync kelas via pivot table jika ada kelas_ids
            if ($request->has('kelas_ids') && is_array($request->kelas_ids)) {
                $materi->kelasRelation()->sync($request->kelas_ids);
            }

            // Load kelas relation for response
            $materi->load('kelasRelation:id,nama,tingkat');

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil diupdate',
                'data' => [
                    'id' => $materi->id,
                    'judul' => $materi->judul,
                    'kelas' => $materi->kelas,
                    'kelas_list' => $materi->kelasRelation->map(fn($k) => [
                        'id' => $k->id,
                        'nama' => $k->nama,
                        'tingkat' => $k->tingkat
                    ]),
                    'kelas_ids' => $materi->kelasRelation->pluck('id'),
                    'file_name' => $materi->file_name,
                    'file_size' => $materi->file_size,
                    'status' => $materi->status,
                    'created_at' => $materi->created_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified materi
     * DELETE /api/materi/{id}
     */
    public function destroy(string $id)
    {
        try {
            $materi = Materi::find($id);

            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            // File akan otomatis dihapus oleh model boot deleting event
            $materi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Materi berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download materi file
     * GET /api/materi/{id}/download
     */
    public function download(string $id)
    {
        try {
            $materi = Materi::find($id);

            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            if (!$materi->file_path || !Storage::disk('public')->exists($materi->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan'
                ], 404);
            }

            $absolutePath = Storage::disk('public')->path($materi->file_path);
            $downloadName = $materi->file_name ?: basename($materi->file_path);
            return response()->download($absolutePath, $downloadName);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal download file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

    private function normalizeStatus(string $status): string
    {
        return $status === 'Dipublikasikan' ? 'Published' : $status;
    }
}
