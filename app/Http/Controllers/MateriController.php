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
                    'pesan_pembelajaran' => $m->pesan_pembelajaran,
                    'link_video' => $m->link_video,
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
                    'tugas_enabled' => (bool) $m->tugas_enabled,
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
                'pesan_pembelajaran' => 'nullable|string',
                'link_video' => 'nullable|url|max:2048',
                'deskripsi' => 'nullable|string',
                'kelas' => 'sometimes', // Legacy: array of tingkat (X, XI, XII) - opsional
                'kelas_ids' => 'required|array|min:1', // New: array of kelas IDs - WAJIB
                'kelas_ids.*' => 'integer|exists:kelas,id',
                // Optional (FE form doesn't always send it)
                'jurusan_id' => 'nullable|exists:jurusans,id',
                // Accept FE label "Dipublikasikan" but persist as DB enum value "Published"
                'status' => 'sometimes|in:Draft,Published,Archived,Dipublikasikan',
                'tugas_enabled' => 'sometimes|boolean',
                'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,jpeg,png,webp,mp4|max:51200' // Max 50MB
            ], [
                'judul.required' => 'Judul materi wajib diisi',
                'kelas_ids.required' => 'Kelas wajib dipilih (kelas_ids)',
                'kelas_ids.min' => 'Minimal pilih 1 kelas',
                'file.mimes' => 'Format file tidak didukung',
                'file.max' => 'File maksimal 50MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Require at least one: file upload OR link video
            if (!$request->hasFile('file') && !$request->filled('link_video')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => [
                        'file' => ['Wajib upload file atau isi link video.'],
                        'link_video' => ['Wajib upload file atau isi link video.'],
                    ],
                ], 422);
            }

            // Buat array kelas legacy dari kelas_ids untuk backward compat
            $kelas = [];
            if ($request->has('kelas_ids') && is_array($request->kelas_ids)) {
                $kelasData = \App\Models\Kelas::whereIn('id', $request->kelas_ids)->pluck('tingkat')->unique()->values()->toArray();
                $kelas = $kelasData;
            } elseif ($request->has('kelas')) {
                $kelas = $this->normalizeKelas($request->kelas);
            }
            
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
                'pesan_pembelajaran' => $request->input('pesan_pembelajaran'),
                'link_video' => $request->input('link_video'),
                'deskripsi' => $request->deskripsi,
                'kelas' => $kelas,
                'jurusan_id' => $request->jurusan_id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'status' => $status,
                'tugas_enabled' => $request->boolean('tugas_enabled', false),
                'created_by' => auth()->id()
            ]);

            // Sync kelas via pivot table (WAJIB ada kelas_ids)
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
                    'pesan_pembelajaran' => $materi->pesan_pembelajaran,
                    'link_video' => $materi->link_video,
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
                    'tugas_enabled' => (bool) $materi->tugas_enabled,
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
            $user = auth()->user();
            $materi = Materi::with('kelasRelation:id,nama,tingkat')->find($id);

            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            // Siswa hanya bisa lihat materi yang dipublikasikan & sesuai kelas_id
            if ($user && $user->role === 'siswa') {
                if (!in_array($materi->status, ['Published', 'Dipublikasikan'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Materi tidak ditemukan'
                    ], 404);
                }

                if ($user->kelas_id && !$materi->kelasRelation->contains('id', $user->kelas_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Materi tidak ditemukan'
                    ], 404);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $materi->id,
                    'judul' => $materi->judul,
                    'pesan_pembelajaran' => $materi->pesan_pembelajaran,
                    'link_video' => $materi->link_video,
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
                    'tugas_enabled' => (bool) $materi->tugas_enabled,
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
                'pesan_pembelajaran' => 'nullable|string',
                'link_video' => 'nullable|url|max:2048',
                'deskripsi' => 'nullable|string',
                'kelas' => 'sometimes', // Legacy: array of tingkat - opsional
                'kelas_ids' => 'sometimes|array|min:1', // New: array of kelas IDs
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'jurusan_id' => 'sometimes|exists:jurusans,id',
                'status' => 'sometimes|in:Draft,Published,Archived,Dipublikasikan',
                'tugas_enabled' => 'sometimes|boolean',
                'file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,jpeg,png,webp,mp4|max:51200'
            ], [
                'file.mimes' => 'Format file tidak didukung',
                'file.max' => 'File maksimal 50MB'
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
            // Use exists() so empty string can intentionally clear the field
            if ($request->exists('pesan_pembelajaran')) $updateData['pesan_pembelajaran'] = $request->input('pesan_pembelajaran');
            // Use exists() so empty string can intentionally clear the field
            if ($request->exists('link_video')) {
                $linkVideo = $request->input('link_video');
                if (is_string($linkVideo) && trim($linkVideo) === '') {
                    $linkVideo = null;
                }
                $updateData['link_video'] = $linkVideo;
            }
            if ($request->has('deskripsi')) $updateData['deskripsi'] = $request->deskripsi;
            if ($request->has('jurusan_id')) $updateData['jurusan_id'] = $request->jurusan_id;
            if ($request->has('status')) $updateData['status'] = $this->normalizeStatus($request->status);
            if ($request->has('tugas_enabled')) $updateData['tugas_enabled'] = $request->boolean('tugas_enabled');
            
            // Update kelas legacy dari kelas_ids jika ada
            if ($request->has('kelas_ids') && is_array($request->kelas_ids)) {
                $kelasData = \App\Models\Kelas::whereIn('id', $request->kelas_ids)->pluck('tingkat')->unique()->values()->toArray();
                $updateData['kelas'] = $kelasData;
            } elseif ($request->has('kelas')) {
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

            // Optional safety: don't allow materi end up with no file & no link
            $willHaveFile = $request->hasFile('file') || !empty($materi->file_path);
            $newLinkVideoValue = array_key_exists('link_video', $updateData) ? $updateData['link_video'] : $materi->link_video;
            $willHaveLink = !empty($newLinkVideoValue);
            if (!$willHaveFile && !$willHaveLink) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => [
                        'file' => ['Wajib upload file atau isi link video.'],
                        'link_video' => ['Wajib upload file atau isi link video.'],
                    ],
                ], 422);
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
                    'pesan_pembelajaran' => $materi->pesan_pembelajaran,
                    'link_video' => $materi->link_video,
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
                    'tugas_enabled' => (bool) $materi->tugas_enabled,
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
            $user = auth()->user();
            $materi = Materi::with('kelasRelation:id')->find($id);

            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            // Siswa hanya bisa download materi yang dipublikasikan & sesuai kelas_id
            if ($user && $user->role === 'siswa') {
                if (!in_array($materi->status, ['Published', 'Dipublikasikan'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Materi tidak ditemukan'
                    ], 404);
                }

                if ($user->kelas_id && !$materi->kelasRelation->contains('id', $user->kelas_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Materi tidak ditemukan'
                    ], 404);
                }
            }

            if (!$materi->file_path || !Storage::disk('public')->exists($materi->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan'
                ], 404);
            }

            $absolutePath = Storage::disk('public')->path($materi->file_path);
            $downloadName = $materi->file_name ?: basename($materi->file_path);
            $detectedMime = @mime_content_type($absolutePath);
            $mimeType = is_string($detectedMime) && $detectedMime !== '' ? $detectedMime : 'application/octet-stream';
            return response()->download($absolutePath, $downloadName, [
                'Content-Type' => $mimeType,
            ]);
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
