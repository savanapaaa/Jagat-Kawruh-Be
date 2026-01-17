<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register new user (admin atau guru saja)
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,guru',
            'nip' => 'nullable|string|unique:users',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'nip' => $validated['nip'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'nama' => $user->name,
                'role' => $user->role,
                'avatar' => $user->avatar ?? null,
            ],
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'nama' => $user->name, // sesuai spec menggunakan 'nama'
                    'role' => $user->role,
                    'avatar' => $user->avatar ?? null,
                ],
                'token' => $token,
            ]
        ], 201);
    }

    /**
     * Login untuk semua role
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.'
            ], 403);
        }

        // Hapus token lama
        $user->tokens()->delete();

        // Buat token baru
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'nama' => $user->name,
                'role' => $user->role,
                'avatar' => $user->avatar ?? null,
            ],
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'nama' => $user->name, // sesuai spec menggunakan 'nama'
                    'role' => $user->role,
                    'avatar' => $user->avatar ?? null,
                ],
                'token' => $token,
            ]
        ], 200);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ], 200);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'nisn' => $user->nisn,
                'nip' => $user->nip,
                'phone' => $user->phone,
                'address' => $user->address,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ], 200);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diupdate',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'nisn' => $user->nisn,
                'nip' => $user->nip,
                'phone' => $user->phone,
                'address' => $user->address,
            ]
        ], 200);
    }

    /**
     * Get current authenticated user (sesuai spec: GET /auth/me)
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $userData = [
            'id' => $user->id,
            'email' => $user->email,
            'nama' => $user->name, // menggunakan 'nama' sesuai spec
            'role' => $user->role,
            'avatar' => $user->avatar ?? null,
        ];

        // Tambahan data untuk guru: kelas yang diampu dengan detail
        if ($user->role === 'guru' && !empty($user->kelas_diampu)) {
            $kelasIds = $user->kelas_diampu;
            $kelasDetail = \App\Models\Kelas::whereIn('id', $kelasIds)
                ->select('id', 'nama', 'tingkat', 'jurusan_id')
                ->with('jurusan:id,nama')
                ->get();
            $userData['kelas_diampu'] = $kelasDetail;
        } else if ($user->role === 'guru') {
            $userData['kelas_diampu'] = [];
        }

        // Tambahan data untuk siswa
        if ($user->role === 'siswa') {
            $userData['nisn'] = $user->nisn;
            $userData['nis'] = $user->nis;
            $userData['kelas'] = $user->kelas;
            $userData['jurusan_id'] = $user->jurusan_id;
            $userData['kelas_id'] = $user->kelas_id;
            
            // Load kelas relation dengan detail lengkap
            if ($user->kelas_id) {
                $kelasDetail = \App\Models\Kelas::where('id', $user->kelas_id)
                    ->select('id', 'nama', 'tingkat', 'jurusan_id')
                    ->with('jurusan:id,nama')
                    ->first();
                $userData['kelas_relation'] = $kelasDetail;
            } else {
                $userData['kelas_relation'] = null;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $userData
            ]
        ], 200);
    }
}
