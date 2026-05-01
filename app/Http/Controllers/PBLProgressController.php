<?php

namespace App\Http\Controllers;

use App\Models\PBL;
use App\Models\PBLSintaks;
use App\Models\PBLProgress;
use App\Models\Kelompok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PBLProgressController extends Controller
{
    /**
     * GET /api/pbl/{pblId}/progress
     * Get all progress by project
     * - Guru: lihat semua kelompok
     * - Siswa: lihat kelompoknya saja
     */
    public function index(Request $request, $pblId)
    {
        $user = auth()->user();
        $kelompokId = $request->query('kelompok_id');

        // Normalize pbl_id
        $pblId = $this->normalizePblId($pblId);

        // Check PBL exists
        $pbl = PBL::find($pblId);
        if (!$pbl) {
            return response()->json([
                'success' => false,
                'message' => 'PBL tidak ditemukan'
            ], 404);
        }

        // Jika siswa, auto-filter ke kelompoknya
        if ($user->role === 'siswa') {
            $myKelompok = $this->findKelompokByUser($pblId, $user);
            
            if (!$myKelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda belum terdaftar di kelompok manapun untuk project ini'
                ], 403);
            }
            
            $kelompokId = $myKelompok->id;
        }

        // Get sintaks list with progress
        $sintaksList = PBLSintaks::where('pbl_id', $pblId)
            ->orderBy('urutan')
            ->get();

        $result = $sintaksList->map(function ($sintaks) use ($kelompokId) {
            $progressQuery = PBLProgress::where('sintaks_id', $sintaks->id);
            
            if ($kelompokId) {
                $progressQuery->where('kelompok_id', $kelompokId);
            }
            
            $progress = $progressQuery->first();

            return [
                'sintaks_id' => $sintaks->id,
                'judul' => $sintaks->judul,
                'instruksi' => $sintaks->instruksi,
                'urutan' => $sintaks->urutan,
                'catatan' => $progress?->catatan,
                'file_path' => $progress?->file_path,
                'file_name' => $progress?->file_name,
                'file_url' => $progress?->file_url,
                'completed' => $progress !== null,
                'submitted_at' => $progress?->submitted_at
            ];
        });

        // Calculate completion percentage
        $totalSintaks = $sintaksList->count();
        $completedSintaks = $result->filter(fn($item) => $item['completed'])->count();
        $completionPercentage = $totalSintaks > 0 ? round(($completedSintaks / $totalSintaks) * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'pbl_id' => $pblId,
                'kelompok_id' => $kelompokId,
                'total_sintaks' => $totalSintaks,
                'completed_sintaks' => $completedSintaks,
                'completion_percentage' => $completionPercentage,
                'progress' => $result
            ]
        ]);
    }

    /**
     * GET /api/pbl/{pblId}/sintaks/{sintaksId}/progress
     * Get progress per sintaks
     */
    public function show(Request $request, $pblId, $sintaksId)
    {
        $user = auth()->user();
        $kelompokId = $request->query('kelompok_id');

        // Normalize IDs
        $pblId = $this->normalizePblId($pblId);
        $sintaksId = $this->normalizeSintaksId($sintaksId);

        // Jika siswa, auto-filter ke kelompoknya
        if ($user->role === 'siswa') {
            $myKelompok = $this->findKelompokByUser($pblId, $user);
            $kelompokId = $myKelompok?->id;
        }

        $progressQuery = PBLProgress::where('pbl_id', $pblId)
            ->where('sintaks_id', $sintaksId);

        if ($kelompokId) {
            $progressQuery->where('kelompok_id', $kelompokId);
        }

        $progress = $progressQuery->with(['sintaks', 'kelompok'])->first();

        return response()->json([
            'success' => true,
            'data' => $progress
        ]);
    }

    /**
     * POST /api/pbl/{pblId}/sintaks/{sintaksId}/progress
     * Submit atau update progress per sintaks (siswa only)
     */
    public function store(Request $request, $pblId, $sintaksId)
    {
        $user = auth()->user();

        // Normalize IDs
        $pblId = $this->normalizePblId($pblId);
        $sintaksId = $this->normalizeSintaksId($sintaksId);

        // Check PBL exists
        $pbl = PBL::find($pblId);
        if (!$pbl) {
            return response()->json([
                'success' => false,
                'message' => 'PBL tidak ditemukan'
            ], 404);
        }

        // Check sintaks exists
        $sintaks = PBLSintaks::find($sintaksId);
        if (!$sintaks || $sintaks->pbl_id !== $pblId) {
            return response()->json([
                'success' => false,
                'message' => 'Sintaks tidak ditemukan'
            ], 404);
        }

        // Validasi siswa harus punya kelompok di project ini
        $kelompok = $this->findKelompokByUser($pblId, $user);

        if (!$kelompok) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar di kelompok manapun untuk project ini'
            ], 403);
        }

        $validated = $request->validate([
            'catatan' => 'required|string',
            'file' => 'nullable|file|mimes:pdf,doc,docx,zip,rar,ppt,pptx,xls,xlsx,jpg,jpeg,png|max:10240'
        ]);

        // Get existing progress if any
        $existingProgress = PBLProgress::where('sintaks_id', $sintaksId)
            ->where('kelompok_id', $kelompok->id)
            ->first();

        // Upload file jika ada
        $filePath = $existingProgress?->file_path;
        $fileName = $existingProgress?->file_name;
        
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('pbl/progress', $fileName, 'public');
        }

        // Upsert: update jika sudah ada, create jika belum
        $progress = PBLProgress::updateOrCreate(
            [
                'sintaks_id' => $sintaksId,
                'kelompok_id' => $kelompok->id
            ],
            [
                'pbl_id' => $pblId,
                'catatan' => $validated['catatan'],
                'file_path' => $filePath,
                'file_name' => $fileName,
                'submitted_at' => now()
            ]
        );

        $progress->load(['sintaks', 'kelompok']);

        return response()->json([
            'success' => true,
            'message' => 'Progress berhasil disimpan',
            'data' => $progress
        ], $existingProgress ? 200 : 201);
    }

    /**
     * DELETE /api/pbl/{pblId}/sintaks/{sintaksId}/progress
     * Delete progress (optional - for siswa to reset their progress)
     */
    public function destroy(Request $request, $pblId, $sintaksId)
    {
        $user = auth()->user();

        // Normalize IDs
        $pblId = $this->normalizePblId($pblId);
        $sintaksId = $this->normalizeSintaksId($sintaksId);

        // Get kelompok
        $kelompok = $this->findKelompokByUser($pblId, $user);

        if (!$kelompok) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar di kelompok manapun untuk project ini'
            ], 403);
        }

        $progress = PBLProgress::where('sintaks_id', $sintaksId)
            ->where('kelompok_id', $kelompok->id)
            ->first();

        if (!$progress) {
            return response()->json([
                'success' => false,
                'message' => 'Progress tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($progress->file_path && Storage::disk('public')->exists($progress->file_path)) {
            Storage::disk('public')->delete($progress->file_path);
        }

        $progress->delete();

        return response()->json([
            'success' => true,
            'message' => 'Progress berhasil dihapus'
        ]);
    }

    /**
     * Normalize PBL ID (handle both "pbl-1" and "1" format)
     */
    private function normalizePblId($id)
    {
        if (is_numeric($id)) {
            return 'pbl-' . $id;
        }
        return $id;
    }

    /**
     * Normalize Sintaks ID (handle both "sintaks-1" and "1" format)
     */
    private function normalizeSintaksId($id)
    {
        // Check if sintaks uses custom ID format
        if (is_numeric($id)) {
            // Try to find with numeric ID first
            $sintaks = PBLSintaks::find($id);
            if ($sintaks) {
                return $id;
            }
        }
        return $id;
    }

    /**
     * Find kelompok by user membership
     * Supports multiple ID formats: integer, string, "siswa-X"
     */
    private function findKelompokByUser($pblId, $user)
    {
        return Kelompok::where('pbl_id', $pblId)
            ->where(function ($query) use ($user) {
                $query->whereJsonContains('anggota', $user->id)
                    ->orWhereJsonContains('anggota', (string) $user->id)
                    ->orWhereJsonContains('anggota', 'siswa-' . $user->id);
            })
            ->first();
    }
}
