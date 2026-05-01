<?php

namespace App\Http\Controllers;

use App\Models\Materi;
use App\Models\MateriSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MateriSubmissionController extends Controller
{
    private function siswaCanAccessMateri($user, Materi $materi): bool
    {
        if (!$user || $user->role !== 'siswa') {
            return true;
        }

        if (!in_array($materi->status, ['Published', 'Dipublikasikan'])) {
            return false;
        }

        if ($user->kelas_id) {
            $kelasIds = $materi->kelasRelation->pluck('id')->toArray();
            if (!empty($kelasIds) && !in_array($user->kelas_id, $kelasIds)) {
                return false;
            }
        }

        return true;
    }

    private function guruCanManageMateri($user, Materi $materi): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        // Guru hanya boleh lihat/menilai submission pada materi yang dia buat.
        return $user->role === 'guru' && (int) $materi->created_by === (int) $user->id;
    }

    public function submit(Request $request, string $materiId)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang dapat submit tugas materi'
                ], 403);
            }

            $materi = Materi::with('kelasRelation:id')->find($materiId);
            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            if (!$this->siswaCanAccessMateri($user, $materi)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses untuk submit materi ini'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:pdf,zip,rar,docx,pptx,xlsx,doc,ppt,xls|max:20480',
                'catatan' => 'nullable|string'
            ], [
                'file.required' => 'File tugas wajib diunggah',
                'file.mimes' => 'Format file harus: pdf, zip, rar, docx, pptx, xlsx, doc, ppt, xls',
                'file.max' => 'Ukuran file maksimal 20MB'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $existing = MateriSubmission::where('materi_id', $materi->id)
                ->where('siswa_id', $user->id)
                ->first();

            if ($existing && $existing->file_path && Storage::disk('public')->exists($existing->file_path)) {
                Storage::disk('public')->delete($existing->file_path);
            }

            $file = $request->file('file');
            $storedName = time() . '_' . $user->id . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('materi_submissions/' . $materi->id, $storedName, 'public');

            if ($existing) {
                $existing->update([
                    'catatan' => $request->catatan,
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'submitted_at' => now(),
                    'nilai' => null,
                    'feedback' => null,
                    'graded_at' => null,
                ]);
                $submission = $existing->fresh(['siswa:id,name,email,kelas,kelas_id', 'siswa.kelasRelation:id,nama']);
                $message = 'Submission berhasil diperbarui';
            } else {
                $submission = MateriSubmission::create([
                    'materi_id' => $materi->id,
                    'siswa_id' => $user->id,
                    'catatan' => $request->catatan,
                    'file_path' => $filePath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'submitted_at' => now(),
                ])->load(['siswa:id,name,email,kelas,kelas_id', 'siswa.kelasRelation:id,nama']);
                $message = 'Submission berhasil dikumpulkan';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->formatSubmission($submission)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal submit tugas materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByMateri(string $materiId)
    {
        try {
            $user = auth()->user();

            $materi = Materi::find($materiId);
            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            if (!$this->guruCanManageMateri($user, $materi)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            $submissions = MateriSubmission::with(['siswa:id,name,email,kelas,kelas_id', 'siswa.kelasRelation:id,nama'])
                ->where('materi_id', $materi->id)
                ->orderBy('submitted_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $submissions->map(fn(MateriSubmission $submission) => $this->formatSubmission($submission))
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data submission materi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMine(string $materiId)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang dapat mengakses endpoint ini'
                ], 403);
            }

            $materi = Materi::with('kelasRelation:id')->find($materiId);
            if (!$materi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Materi tidak ditemukan'
                ], 404);
            }

            if (!$this->siswaCanAccessMateri($user, $materi)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke materi ini'
                ], 403);
            }

            $submission = MateriSubmission::with(['siswa:id,name,email,kelas,kelas_id', 'siswa.kelasRelation:id,nama'])
                ->where('materi_id', $materi->id)
                ->where('siswa_id', $user->id)
                ->first();

            if (!$submission) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatSubmission($submission)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil submission saya',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function nilai(Request $request, string $submissionId)
    {
        try {
            $submission = MateriSubmission::with('materi')->find($submissionId);
            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            if (!$this->guruCanManageMateri($user, $submission->materi)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'nilai' => 'required|numeric|min:0|max:100',
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
                'feedback' => $request->feedback,
                'graded_at' => now(),
            ]);

            $submission->load(['siswa:id,name,email,kelas,kelas_id', 'siswa.kelasRelation:id,nama']);

            return response()->json([
                'success' => true,
                'message' => 'Nilai submission berhasil disimpan',
                'data' => $this->formatSubmission($submission)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memberi nilai submission',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function download(string $submissionId)
    {
        try {
            $submission = MateriSubmission::with(['materi', 'siswa'])->find($submissionId);
            if (!$submission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Submission tidak ditemukan'
                ], 404);
            }

            $user = auth()->user();
            $isOwnerSiswa = $user && $user->role === 'siswa' && (int) $submission->siswa_id === (int) $user->id;
            $canManage = $this->guruCanManageMateri($user, $submission->materi);

            if (!$isOwnerSiswa && !$canManage) {
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

    private function formatSubmission(MateriSubmission $submission): array
    {
        $kelasNama = $submission->siswa?->kelasRelation?->nama ?? $submission->siswa?->kelas;

        return [
            'id' => $submission->id,
            'materi_id' => $submission->materi_id,
            'siswa_id' => $submission->siswa_id,
            'nama_siswa' => $submission->siswa?->name,
            'email_siswa' => $submission->siswa?->email,
            'kelas_nama' => $kelasNama,
            'catatan' => $submission->catatan,
            'file_name' => $submission->file_name,
            'file_size' => $submission->file_size,
            'file_path' => $submission->file_path,
            'file_url' => $submission->file_path ? Storage::url($submission->file_path) : null,
            'submitted_at' => $submission->submitted_at,
            'nilai' => $submission->nilai,
            'feedback' => $submission->feedback,
            'graded_at' => $submission->graded_at,
            'created_at' => $submission->created_at,
            'updated_at' => $submission->updated_at,
        ];
    }
}
