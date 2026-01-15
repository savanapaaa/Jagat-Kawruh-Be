<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SiswaController extends Controller
{
    /**
     * Display a listing of siswa
     * GET /api/siswa
     * Query params: kelas, jurusan, search
     */
    public function index(Request $request)
    {
        try {
            $query = User::where('role', 'siswa')->with('jurusan:id,nama');

            // Filter by kelas
            if ($request->has('kelas')) {
                $query->where('kelas', $request->kelas);
            }

            // Filter by jurusan
            if ($request->has('jurusan')) {
                $query->where('jurusan_id', $request->jurusan);
            }

            // Search by nama or NIS
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('nis', 'like', "%{$search}%");
                });
            }

            $siswa = $query->orderBy('created_at', 'desc')->get();

            // Format response sesuai spec
            $data = $siswa->map(function($s) {
                return [
                    'id' => 'siswa-' . $s->id,
                    'nis' => $s->nis,
                    'nama' => $s->name,
                    'email' => $s->email,
                    'kelas' => $s->kelas,
                    'jurusan_id' => $s->jurusan_id,
                    'jurusan' => $s->jurusan ? [
                        'id' => $s->jurusan->id,
                        'nama' => $s->jurusan->nama
                    ] : null,
                    'avatar' => $s->avatar,
                    'created_at' => $s->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created siswa
     * POST /api/siswa
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nis' => 'required|string|unique:users,nis',
                'nama' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'kelas' => 'required|in:X,XI,XII',
                'jurusan_id' => 'required|exists:jurusans,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $siswa = User::create([
                'name' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'siswa',
                'nis' => $request->nis,
                'kelas' => $request->kelas,
                'jurusan_id' => $request->jurusan_id,
                'is_active' => true
            ]);

            $siswa->load('jurusan:id,nama');

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dibuat',
                'data' => [
                    'id' => 'siswa-' . $siswa->id,
                    'nis' => $siswa->nis,
                    'nama' => $siswa->name,
                    'email' => $siswa->email,
                    'kelas' => $siswa->kelas,
                    'jurusan_id' => $siswa->jurusan_id,
                    'jurusan' => $siswa->jurusan ? [
                        'id' => $siswa->jurusan->id,
                        'nama' => $siswa->jurusan->nama
                    ] : null,
                    'avatar' => $siswa->avatar,
                    'created_at' => $siswa->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified siswa
     * GET /api/siswa/{id}
     */
    public function show(string $id)
    {
        try {
            // Extract numeric ID from siswa-{id} format
            $numericId = str_replace('siswa-', '', $id);
            
            $siswa = User::where('role', 'siswa')
                ->where('id', $numericId)
                ->with('jurusan:id,nama')
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => 'siswa-' . $siswa->id,
                    'nis' => $siswa->nis,
                    'nama' => $siswa->name,
                    'email' => $siswa->email,
                    'kelas' => $siswa->kelas,
                    'jurusan_id' => $siswa->jurusan_id,
                    'jurusan' => $siswa->jurusan ? [
                        'id' => $siswa->jurusan->id,
                        'nama' => $siswa->jurusan->nama
                    ] : null,
                    'avatar' => $siswa->avatar,
                    'created_at' => $siswa->created_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified siswa
     * PUT /api/siswa/{id}
     */
    public function update(Request $request, string $id)
    {
        try {
            $numericId = str_replace('siswa-', '', $id);
            
            $siswa = User::where('role', 'siswa')
                ->where('id', $numericId)
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nis' => 'sometimes|string|unique:users,nis,' . $numericId,
                'nama' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $numericId,
                'password' => 'nullable|string|min:8',
                'kelas' => 'sometimes|in:X,XI,XII',
                'jurusan_id' => 'sometimes|exists:jurusans,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('nama')) $updateData['name'] = $request->nama;
            if ($request->has('email')) $updateData['email'] = $request->email;
            if ($request->has('nis')) $updateData['nis'] = $request->nis;
            if ($request->has('kelas')) $updateData['kelas'] = $request->kelas;
            if ($request->has('jurusan_id')) $updateData['jurusan_id'] = $request->jurusan_id;
            if ($request->has('password')) $updateData['password'] = Hash::make($request->password);

            $siswa->update($updateData);
            $siswa->load('jurusan:id,nama');

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil diupdate',
                'data' => [
                    'id' => 'siswa-' . $siswa->id,
                    'nis' => $siswa->nis,
                    'nama' => $siswa->name,
                    'email' => $siswa->email,
                    'kelas' => $siswa->kelas,
                    'jurusan_id' => $siswa->jurusan_id,
                    'jurusan' => $siswa->jurusan ? [
                        'id' => $siswa->jurusan->id,
                        'nama' => $siswa->jurusan->nama
                    ] : null,
                    'avatar' => $siswa->avatar,
                    'created_at' => $siswa->created_at
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified siswa
     * DELETE /api/siswa/{id}
     */
    public function destroy(string $id)
    {
        try {
            $numericId = str_replace('siswa-', '', $id);
            
            $siswa = User::where('role', 'siswa')
                ->where('id', $numericId)
                ->first();

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $siswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import siswa from Excel/CSV
     * POST /api/siswa/import
     */
    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            // TODO: Implement Excel/CSV import logic
            // Untuk sekarang return placeholder response
            return response()->json([
                'success' => true,
                'message' => 'Import siswa berhasil (placeholder - belum diimplementasi)',
                'data' => [
                    'imported' => 0,
                    'failed' => 0
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal import siswa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
