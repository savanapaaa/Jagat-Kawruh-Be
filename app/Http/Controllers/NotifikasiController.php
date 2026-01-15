<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotifikasiController extends Controller
{
    /**
     * Get All Notifikasi
     * GET /api/notifikasi
     * Query params: tipe (kuis/materi/pbl/pengumuman)
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            
            $query = Notifikasi::where('user_id', $user->id)
                ->orWhereNull('user_id') // Broadcast notifikasi
                ->orderBy('created_at', 'desc');

            // Filter by tipe
            if ($request->has('tipe')) {
                $query->where('tipe', $request->tipe);
            }

            $notifikasi = $query->get()->map(function($n) {
                return [
                    'id' => $n->id,
                    'judul' => $n->judul,
                    'pesan' => $n->pesan,
                    'tipe' => $n->tipe,
                    'read' => $n->read,
                    'created_at' => $n->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $notifikasi
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Notifikasi (Admin/Guru)
     * POST /api/notifikasi
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'judul' => 'required|string|max:255',
                'pesan' => 'required|string',
                'tipe' => 'required|in:kuis,materi,pbl,pengumuman',
                'user_id' => 'nullable|exists:users,id' // Null = broadcast to all
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $notifikasi = Notifikasi::create([
                'user_id' => $request->user_id,
                'judul' => $request->judul,
                'pesan' => $request->pesan,
                'tipe' => $request->tipe
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil dibuat',
                'data' => $notifikasi
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark as Read
     * PUT /api/notifikasi/{id}/read
     */
    public function markAsRead(string $id)
    {
        try {
            $notifikasi = Notifikasi::find($id);

            if (!$notifikasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifikasi tidak ditemukan'
                ], 404);
            }

            // Verify ownership
            $user = auth()->user();
            if ($notifikasi->user_id && $notifikasi->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            $notifikasi->update(['read' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi ditandai sudah dibaca',
                'data' => $notifikasi
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Notifikasi
     * DELETE /api/notifikasi/{id}
     */
    public function destroy(string $id)
    {
        try {
            $notifikasi = Notifikasi::find($id);

            if (!$notifikasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notifikasi tidak ditemukan'
                ], 404);
            }

            // Verify ownership (or admin/guru can delete any)
            $user = auth()->user();
            if ($notifikasi->user_id && $notifikasi->user_id !== $user->id && !in_array($user->role, ['admin', 'guru'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            $notifikasi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifikasi berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
