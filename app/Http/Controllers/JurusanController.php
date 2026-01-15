<?php

namespace App\Http\Controllers;

use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JurusanController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/jurusan
     */
    public function index()
    {
        try {
            $jurusan = Jurusan::orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $jurusan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jurusan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/jurusan
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'deskripsi' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jurusan = Jurusan::create([
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil dibuat',
                'data' => $jurusan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jurusan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * GET /api/jurusan/{id}
     */
    public function show(string $id)
    {
        try {
            $jurusan = Jurusan::find($id);

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $jurusan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data jurusan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * PUT /api/jurusan/{id}
     */
    public function update(Request $request, string $id)
    {
        try {
            $jurusan = Jurusan::find($id);

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:255',
                'deskripsi' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jurusan->update([
                'nama' => $request->nama,
                'deskripsi' => $request->deskripsi
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil diupdate',
                'data' => $jurusan
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate jurusan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/jurusan/{id}
     */
    public function destroy(string $id)
    {
        try {
            $jurusan = Jurusan::find($id);

            if (!$jurusan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jurusan tidak ditemukan'
                ], 404);
            }

            $jurusan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Jurusan berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jurusan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
