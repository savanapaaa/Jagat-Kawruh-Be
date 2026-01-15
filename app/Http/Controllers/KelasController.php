<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    /**
     * GET /api/kelas (admin only via route middleware)
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 50);
        $search = $request->get('search');

        $query = Kelas::query()->with('jurusan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('tingkat', 'like', "%{$search}%")
                    ->orWhere('jurusan_id', 'like', "%{$search}%");
            });
        }

        $kelas = $query->orderBy('tingkat')->orderBy('nama')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $kelas,
        ], 200);
    }

    /**
     * POST /api/kelas
     */
    public function store(Request $request)
    {
        $jurusanId = $this->normalizeJurusanId($request->input('jurusan_id'));
        if ($jurusanId !== null) {
            $request->merge(['jurusan_id' => $jurusanId]);
        }

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'tingkat' => 'required|in:X,XI,XII',
            'jurusan_id' => 'required|string|exists:jurusans,id',
        ]);

        $kelas = Kelas::create($validated);
        $kelas->load('jurusan');

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil dibuat',
            'data' => $kelas,
        ], 201);
    }

    /**
     * PUT /api/kelas/{id}
     */
    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        if ($request->has('jurusan_id')) {
            $jurusanId = $this->normalizeJurusanId($request->input('jurusan_id'));
            $request->merge(['jurusan_id' => $jurusanId]);
        }

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:255',
            'tingkat' => 'sometimes|required|in:X,XI,XII',
            'jurusan_id' => 'sometimes|required|string|exists:jurusans,id',
        ]);

        $kelas->update($validated);
        $kelas->load('jurusan');

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil diupdate',
            'data' => $kelas,
        ], 200);
    }

    /**
     * DELETE /api/kelas/{id}
     */
    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);
        $kelas->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil dihapus',
        ], 200);
    }

    private function normalizeJurusanId($jurusanId): ?string
    {
        if ($jurusanId === null) {
            return null;
        }

        if (is_int($jurusanId) || (is_string($jurusanId) && ctype_digit($jurusanId))) {
            return 'JUR-' . (string) $jurusanId;
        }

        return (string) $jurusanId;
    }
}
