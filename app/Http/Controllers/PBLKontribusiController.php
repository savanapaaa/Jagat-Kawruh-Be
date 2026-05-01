<?php

namespace App\Http\Controllers;

use App\Models\Kelompok;
use App\Models\PBL;
use App\Models\PBLKontribusi;
use App\Models\PBLSintaks;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PBLKontribusiController extends Controller
{
    public function getMine(Request $request, string $pblId, string $sintaksId)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang dapat mengakses kontribusi pribadi'
                ], 403);
            }

            $project = PBL::find($pblId);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $sintaks = PBLSintaks::find($sintaksId);
            if (!$sintaks || $sintaks->pbl_id !== $pblId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sintaks tidak ditemukan'
                ], 404);
            }

            $kelompok = $this->findKelompokByUser($pblId, $user);
            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum terdaftar di kelompok manapun untuk project ini'
                ], 403);
            }

            $kontribusi = PBLKontribusi::where('pbl_id', $pblId)
                ->where('kelompok_id', $kelompok->id)
                ->where('sintaks_id', $sintaksId)
                ->where('siswa_id', $user->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $kontribusi ? $this->formatKontribusi($kontribusi) : null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil kontribusi pribadi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeMine(Request $request, string $pblId, string $sintaksId)
    {
        try {
            $user = auth()->user();
            if (!$user || $user->role !== 'siswa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya siswa yang dapat mengirim kontribusi'
                ], 403);
            }

            $project = PBL::find($pblId);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $sintaks = PBLSintaks::find($sintaksId);
            if (!$sintaks || $sintaks->pbl_id !== $pblId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sintaks tidak ditemukan'
                ], 404);
            }

            $kelompok = $this->findKelompokByUser($pblId, $user);
            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum terdaftar di kelompok manapun untuk project ini'
                ], 403);
            }

            $existing = PBLKontribusi::where('pbl_id', $pblId)
                ->where('kelompok_id', $kelompok->id)
                ->where('sintaks_id', $sintaksId)
                ->where('siswa_id', $user->id)
                ->first();

            $validator = Validator::make($request->all(), [
                'catatan' => 'required|string',
                'file' => 'nullable|file|mimes:pdf,doc,docx,zip,rar,ppt,pptx,xls,xlsx,jpg,jpeg,png|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $filePath = $existing?->file_path;
            $fileName = $existing?->file_name;

            if ($request->hasFile('file')) {
                if ($filePath && Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }

                $file = $request->file('file');
                $safeFileName = time() . '_kontribusi_' . $user->id . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('pbl/kontribusi', $safeFileName, 'public');
                $fileName = $file->getClientOriginalName();
            }

            $kontribusi = PBLKontribusi::updateOrCreate(
                [
                    'pbl_id' => $pblId,
                    'kelompok_id' => $kelompok->id,
                    'sintaks_id' => $sintaksId,
                    'siswa_id' => $user->id,
                ],
                [
                    'catatan' => (string) $request->input('catatan'),
                    'file_path' => $filePath ?: '',
                    'file_name' => $fileName ?: '',
                    'submitted_at' => now(),
                ]
            );

            $kontribusi->load('siswa:id,name,nis');

            return response()->json([
                'success' => true,
                'message' => 'Kontribusi individu berhasil disimpan',
                'data' => $this->formatKontribusi($kontribusi),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan kontribusi individu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function indexByKelompok(Request $request, string $pblId, string $kelompokId)
    {
        try {
            $user = auth()->user();
            if (!$user || !in_array($user->role, ['admin', 'guru'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru/admin yang dapat melihat kontribusi kelompok'
                ], 403);
            }

            $project = PBL::find($pblId);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            if ($user->role === 'guru' && (int) $project->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke project ini'
                ], 403);
            }

            $kelompok = Kelompok::where('id', $kelompokId)
                ->where('pbl_id', $pblId)
                ->first();

            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelompok tidak ditemukan'
                ], 404);
            }

            $query = PBLKontribusi::with('siswa:id,name,nis')
                ->where('pbl_id', $pblId)
                ->where('kelompok_id', $kelompokId)
                ->orderBy('submitted_at', 'desc');

            if ($request->filled('sintaks_id')) {
                $query->where('sintaks_id', (string) $request->query('sintaks_id'));
            }

            $rows = $query->get();

            return response()->json([
                'success' => true,
                'data' => $rows->map(fn ($row) => $this->formatKontribusi($row)),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil kontribusi kelompok',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function findKelompokByUser(string $pblId, User $user): ?Kelompok
    {
        return Kelompok::where('pbl_id', $pblId)
            ->where(function ($query) use ($user) {
                $query->whereJsonContains('anggota', $user->id)
                    ->orWhereJsonContains('anggota', (string) $user->id)
                    ->orWhereJsonContains('anggota', 'siswa-' . $user->id);
            })
            ->first();
    }

    private function formatKontribusi($kontribusi): array
    {
        return [
            'id' => $kontribusi->id,
            'pbl_id' => $kontribusi->pbl_id,
            'kelompok_id' => $kontribusi->kelompok_id,
            'sintaks_id' => $kontribusi->sintaks_id,
            'siswa_id' => 'siswa-' . $kontribusi->siswa_id,
            'siswa' => $kontribusi->siswa ? [
                'id' => 'siswa-' . $kontribusi->siswa->id,
                'nama' => $kontribusi->siswa->name,
                'nis' => $kontribusi->siswa->nis,
            ] : null,
            'catatan' => $kontribusi->catatan,
            'file_path' => $kontribusi->file_path,
            'file_name' => $kontribusi->file_name,
            'file_url' => $kontribusi->file_url,
            'submitted_at' => $kontribusi->submitted_at,
            'created_at' => $kontribusi->created_at,
            'updated_at' => $kontribusi->updated_at,
        ];
    }
}
