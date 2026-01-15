<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SiswaController extends Controller
{
    /**
     * Get all siswa
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = User::where('role', 'siswa');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $siswa = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $siswa
        ], 200);
    }

    /**
     * Create new siswa
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'nisn' => 'required|string|unique:users',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $siswa = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'siswa',
            'nisn' => $validated['nisn'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil dibuat',
            'data' => $siswa
        ], 201);
    }

    /**
     * Get specific siswa
     */
    public function show($id)
    {
        $siswa = User::where('role', 'siswa')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $siswa
        ], 200);
    }

    /**
     * Update siswa
     */
    public function update(Request $request, $id)
    {
        $siswa = User::where('role', 'siswa')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'nisn' => 'sometimes|string|unique:users,nisn,' . $id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $siswa->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil diupdate',
            'data' => $siswa
        ], 200);
    }

    /**
     * Delete siswa
     */
    public function destroy($id)
    {
        $siswa = User::where('role', 'siswa')->findOrFail($id);
        $siswa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil dihapus'
        ], 200);
    }

    /**
     * Toggle siswa status
     */
    public function toggleStatus($id)
    {
        $siswa = User::where('role', 'siswa')->findOrFail($id);
        $siswa->is_active = !$siswa->is_active;
        $siswa->save();

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil diubah',
            'data' => $siswa
        ], 200);
    }
}
