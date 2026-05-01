<?php

namespace App\Http\Controllers;

use App\Models\Kelompok;
use App\Models\PBL;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PBLJobdeskController extends Controller
{
    private const ALLOWED_ROLES = ['Ketua', 'Penyelidik', 'Analis', 'Notulis'];

    public function show(Request $request, string $pblId, string $kelompokId)
    {
        try {
            $user = auth()->user();

            $project = PBL::find($pblId);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            $kelompok = Kelompok::where('id', $kelompokId)
                ->where('pbl_id', $pblId)
                ->first();

            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelompok tidak ditemukan'
                ], 404);
            }

            if (!$this->canAccessJobdesk($user, $project, $kelompok)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'pbl_id' => $pblId,
                    'kelompok_id' => $kelompokId,
                    'jobdesk' => $this->normalizeJobdeskOutput($kelompok->jobdesk ?? []),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil jobdesk kelompok',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $pblId, string $kelompokId)
    {
        try {
            $user = auth()->user();

            if (!$user || !in_array($user->role, ['admin', 'guru'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya guru/admin yang dapat mengubah jobdesk'
                ], 403);
            }

            $project = PBL::find($pblId);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project PBL tidak ditemukan'
                ], 404);
            }

            if ($user->role === 'guru' && (int) $project->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke project ini'
                ], 403);
            }

            $kelompok = Kelompok::where('id', $kelompokId)
                ->where('pbl_id', $pblId)
                ->first();

            if (!$kelompok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelompok tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'jobdesk' => 'required|array',
                'jobdesk.*.siswa_id' => 'required',
                'jobdesk.*.role' => 'required|string|in:Ketua,Penyelidik,Analis,Notulis',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $anggotaNormalized = $this->normalizeAnggota($kelompok->anggota ?? []);
            $jobdeskInput = $request->input('jobdesk', []);
            $normalizedToStore = [];

            foreach ($jobdeskInput as $row) {
                $siswaIdValue = $row['siswa_id'] ?? null;
                $role = (string) ($row['role'] ?? '');

                $normalizedSiswaId = $this->normalizeSiswaId($siswaIdValue);
                if ($normalizedSiswaId === null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'siswa_id tidak valid dalam jobdesk'
                    ], 422);
                }

                if (!in_array($normalizedSiswaId, $anggotaNormalized, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'siswa_id harus anggota kelompok ini',
                        'data' => [
                            'siswa_id' => $normalizedSiswaId,
                            'anggota' => $anggotaNormalized,
                        ]
                    ], 422);
                }

                if (!in_array($role, self::ALLOWED_ROLES, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Role jobdesk tidak valid',
                    ], 422);
                }

                $normalizedToStore[] = [
                    'siswa_id' => $normalizedSiswaId,
                    'role' => $role,
                ];
            }

            // Hindari duplikasi siswa_id
            $uniqueCheck = array_column($normalizedToStore, 'siswa_id');
            if (count($uniqueCheck) !== count(array_unique($uniqueCheck))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setiap siswa hanya boleh memiliki satu role jobdesk'
                ], 422);
            }

            $kelompok->jobdesk = $normalizedToStore;
            $kelompok->save();

            return response()->json([
                'success' => true,
                'message' => 'Jobdesk kelompok berhasil diperbarui',
                'data' => [
                    'pbl_id' => $pblId,
                    'kelompok_id' => $kelompokId,
                    'jobdesk' => $this->normalizeJobdeskOutput($kelompok->jobdesk ?? []),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan jobdesk kelompok',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function canAccessJobdesk($user, PBL $project, Kelompok $kelompok): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'guru') {
            return (int) $project->created_by === (int) $user->id;
        }

        if ($user->role === 'siswa') {
            $anggota = $this->normalizeAnggota($kelompok->anggota ?? []);
            return in_array('siswa-' . $user->id, $anggota, true);
        }

        return false;
    }

    private function normalizeSiswaId($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\d+$/', trim($value)))) {
            return 'siswa-' . (int) $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^siswa-(\d+)$/i', $trimmed, $m)) {
                return 'siswa-' . (int) $m[1];
            }
        }

        return null;
    }

    private function normalizeAnggota(array $anggota): array
    {
        $normalized = [];
        foreach ($anggota as $item) {
            $value = $this->normalizeSiswaId($item);
            if ($value !== null) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeJobdeskOutput($jobdesk): array
    {
        if (!is_array($jobdesk)) {
            return [];
        }

        $result = [];
        foreach ($jobdesk as $row) {
            if (!is_array($row)) {
                continue;
            }

            $siswaId = $this->normalizeSiswaId($row['siswa_id'] ?? null);
            $role = isset($row['role']) ? (string) $row['role'] : null;

            if ($siswaId === null || $role === null || !in_array($role, self::ALLOWED_ROLES, true)) {
                continue;
            }

            $result[] = [
                'siswa_id' => $siswaId,
                'role' => $role,
            ];
        }

        return $result;
    }
}
