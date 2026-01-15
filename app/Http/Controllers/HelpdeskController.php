<?php

namespace App\Http\Controllers;

use App\Models\Helpdesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HelpdeskController extends Controller
{
    /**
     * Get All Tickets
     * GET /api/helpdesk
     * Query params: status (open/progress/solved)
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();

            $query = Helpdesk::with('siswa:id,name,kelas,nis')
                ->orderBy('created_at', 'desc');

            // Siswa hanya bisa lihat ticket sendiri
            if ($user->role === 'siswa') {
                $query->where('siswa_id', $user->id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $tickets = $query->get()->map(function($t) {
                return [
                    'id' => $t->id,
                    'siswa_id' => 'siswa-' . $t->siswa_id,
                    'siswa' => $t->siswa ? [
                        'nama' => $t->siswa->name,
                        'kelas' => $t->siswa->kelas,
                        'nis' => $t->siswa->nis
                    ] : null,
                    'kategori' => $t->kategori,
                    'judul' => $t->judul,
                    'pesan' => $t->pesan,
                    'status' => $t->status,
                    'balasan' => $t->balasan,
                    'created_at' => $t->created_at,
                    'updated_at' => $t->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $tickets
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data helpdesk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Ticket by ID
     * GET /api/helpdesk/{id}
     */
    public function show(string $id)
    {
        try {
            $ticket = Helpdesk::with('siswa:id,name,kelas,nis')->find($id);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan'
                ], 404);
            }

            // Verify ownership if siswa
            $user = auth()->user();
            if ($user->role === 'siswa' && $ticket->siswa_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $ticket->id,
                    'siswa_id' => 'siswa-' . $ticket->siswa_id,
                    'siswa' => $ticket->siswa ? [
                        'nama' => $ticket->siswa->name,
                        'kelas' => $ticket->siswa->kelas,
                        'nis' => $ticket->siswa->nis
                    ] : null,
                    'kategori' => $ticket->kategori,
                    'judul' => $ticket->judul,
                    'pesan' => $ticket->pesan,
                    'status' => $ticket->status,
                    'balasan' => $ticket->balasan,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Ticket (Siswa)
     * POST /api/helpdesk
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kategori' => 'required|in:Akun,Kuis,Materi,PBL,Lainnya',
                'judul' => 'required|string|max:255',
                'pesan' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticket = Helpdesk::create([
                'siswa_id' => auth()->id(),
                'kategori' => $request->kategori,
                'judul' => $request->judul,
                'pesan' => $request->pesan
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dibuat',
                'data' => $ticket
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Ticket Status (Guru/Admin)
     * PUT /api/helpdesk/{id}/status
     */
    public function updateStatus(Request $request, string $id)
    {
        try {
            $ticket = Helpdesk::find($id);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:open,progress,solved',
                'balasan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $ticket->update([
                'status' => $request->status,
                'balasan' => $request->balasan
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status ticket berhasil diupdate',
                'data' => $ticket
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update status ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Ticket
     * DELETE /api/helpdesk/{id}
     */
    public function destroy(string $id)
    {
        try {
            $ticket = Helpdesk::find($id);

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket tidak ditemukan'
                ], 404);
            }

            // Only admin/guru or ticket owner can delete
            $user = auth()->user();
            if ($user->role === 'siswa' && $ticket->siswa_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket berhasil dihapus'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
