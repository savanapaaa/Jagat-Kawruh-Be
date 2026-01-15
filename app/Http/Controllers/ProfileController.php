<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get current user profile
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'nama' => $user->nama,
                'role' => $user->role,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'kelas' => $user->kelas,
                'jurusan_id' => $user->jurusan_id,
                'nis' => $user->nis,
                'nip' => $user->nip,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:255',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png|max:2048', // max 2MB
            'kelas' => 'sometimes|in:X,XI,XII',
            'jurusan_id' => 'sometimes|exists:jurusans,id',
        ], [
            'nama.string' => 'Nama harus berupa teks',
            'nama.max' => 'Nama maksimal 255 karakter',
            'avatar.image' => 'Avatar harus berupa gambar',
            'avatar.mimes' => 'Avatar harus berformat jpeg, jpg, atau png',
            'avatar.max' => 'Avatar maksimal 2MB',
            'kelas.in' => 'Kelas harus X, XI, atau XII',
            'jurusan_id.exists' => 'Jurusan tidak ditemukan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Update nama jika ada
        if ($request->has('nama')) {
            $user->nama = $request->nama;
        }

        // Update kelas jika ada (untuk siswa)
        if ($request->has('kelas') && $user->role === 'siswa') {
            $user->kelas = $request->kelas;
        }

        // Update jurusan_id jika ada (untuk siswa)
        if ($request->has('jurusan_id') && $user->role === 'siswa') {
            $user->jurusan_id = $request->jurusan_id;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diupdate',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'nama' => $user->nama,
                'role' => $user->role,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'kelas' => $user->kelas,
                'jurusan_id' => $user->jurusan_id,
                'nis' => $user->nis,
                'nip' => $user->nip,
            ]
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Password lama wajib diisi',
            'new_password.required' => 'Password baru wajib diisi',
            'new_password.min' => 'Password baru minimal 8 karakter',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai',
                'errors' => [
                    'current_password' => ['Password lama tidak sesuai']
                ]
            ], 400);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Revoke all tokens for security (user must login again)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan login kembali',
        ]);
    }
}
