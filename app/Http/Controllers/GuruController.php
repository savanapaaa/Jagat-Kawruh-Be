<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GuruController extends Controller
{
    /**
     * GET /api/guru
     * List semua guru (admin only via route middleware)
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');

        $query = User::query()
            ->where('role', 'guru')
            ->with('jurusan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $gurus = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $gurus,
        ], 200);
    }

    /**
     * POST /api/guru
     * Tambah guru baru
     */
    public function store(Request $request)
    {
        if (!$request->filled('name') && $request->filled('nama')) {
            $request->merge(['name' => $request->input('nama')]);
        }

        $kelasDiampu = $this->normalizeKelasDiampu($request->input('kelas_diampu'));
        if ($request->has('kelas_diampu')) {
            $request->merge(['kelas_diampu' => $kelasDiampu]);
        }

        $jurusanId = $this->normalizeJurusanId($request->input('jurusan_id'));
        if ($jurusanId !== null) {
            $request->merge(['jurusan_id' => $jurusanId]);
        }

        $validated = $request->validate([
            'nip' => 'required|string|max:50|unique:users,nip',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'jurusan_id' => 'required|string|exists:jurusans,id',
            'kelas_diampu' => 'nullable|array',
            'kelas_diampu.*' => 'integer|exists:kelas,id',
        ]);

        $user = User::create([
            'nip' => $validated['nip'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'guru',
            'jurusan_id' => $validated['jurusan_id'],
            'kelas_diampu' => $validated['kelas_diampu'] ?? null,
            'is_active' => true,
        ]);

        $user->load('jurusan');

        return response()->json([
            'success' => true,
            'message' => 'Guru berhasil dibuat',
            'data' => $user,
        ], 201);
    }

    /**
     * PUT /api/guru/{id}
     * Update data guru
     */
    public function update(Request $request, $id)
    {
        $user = User::where('role', 'guru')->findOrFail($id);

        if (!$request->filled('name') && $request->filled('nama')) {
            $request->merge(['name' => $request->input('nama')]);
        }

        if ($request->has('jurusan_id')) {
            $jurusanId = $this->normalizeJurusanId($request->input('jurusan_id'));
            $request->merge(['jurusan_id' => $jurusanId]);
        }

        if ($request->has('kelas_diampu')) {
            $kelasDiampu = $this->normalizeKelasDiampu($request->input('kelas_diampu'));
            $request->merge(['kelas_diampu' => $kelasDiampu]);
        }

        $validated = $request->validate([
            'nip' => 'sometimes|required|string|max:50|unique:users,nip,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'jurusan_id' => 'sometimes|required|string|exists:jurusans,id',
            'kelas_diampu' => 'nullable|array',
            'kelas_diampu.*' => 'integer|exists:kelas,id',
        ]);

        if (array_key_exists('password', $validated)) {
            if ($validated['password']) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }
        }

        $user->update($validated);
        $user->load('jurusan');

        return response()->json([
            'success' => true,
            'message' => 'Guru berhasil diupdate',
            'data' => $user,
        ], 200);
    }

    /**
     * DELETE /api/guru/{id}
     * Hapus guru
     */
    public function destroy($id)
    {
        $user = User::where('role', 'guru')->findOrFail($id);

        // Cegah admin menghapus dirinya sendiri (kalau admin juga kebetulan punya role guru, atau salah id)
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri',
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Guru berhasil dihapus',
        ], 200);
    }

    private function normalizeJurusanId($jurusanId): ?string
    {
        if ($jurusanId === null) {
            return null;
        }

        // FE kadang ngirim number (1,2,3). Di DB jurusan pakai string: JUR-1, JUR-2, ...
        if (is_int($jurusanId) || (is_string($jurusanId) && ctype_digit($jurusanId))) {
            return 'JUR-' . (string) $jurusanId;
        }

        return (string) $jurusanId;
    }

    private function normalizeKelasDiampu($kelasDiampu): ?array
    {
        if ($kelasDiampu === null) {
            return null;
        }

        if (is_string($kelasDiampu)) {
            $decoded = json_decode($kelasDiampu, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $kelasDiampu = $decoded;
            }
        }

        if (!is_array($kelasDiampu)) {
            return null;
        }

        // Normalize to integer IDs and remove duplicates
        $normalized = collect($kelasDiampu)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(function ($v) {
                if (is_int($v)) {
                    return $v;
                }
                if (is_string($v) && ctype_digit($v)) {
                    return (int) $v;
                }
                return null;
            })
            ->filter(fn ($v) => is_int($v) && $v > 0)
            ->unique()
            ->values()
            ->all();

        return $normalized;
    }
}
