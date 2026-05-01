<?php

namespace App\Http\Controllers;

use App\Models\Kelompok;
use App\Models\PBL;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PBLNilaiIndividuController extends Controller
{
    public function show(Request $request, string $pblId, string $kelompokId)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi dibutuhkan'
            ], 401);
        }

        $project = PBL::find($pblId);
        if (!$project) {
            return response()->json([
                'success' => false,
                'message' => 'Project PBL tidak ditemukan'
            ], 404);
        }

        // Access rules:
        // - admin: full access
        // - guru: only if created the project
        // - siswa: only if anggota di kelompok
        if ($user->role === 'admin') {
            // allowed
        } elseif ($user->role === 'guru') {
            if ((int) $project->created_by !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke project ini'
                ], 403);
            }
        } elseif ($user->role === 'siswa') {
            // we'll check membership after retrieving kelompok
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Role tidak diizinkan'
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

        // If user is siswa, ensure they are member of this kelompok
        if ($user->role === 'siswa') {
            $anggota = is_array($kelompok->anggota) ? $kelompok->anggota : [];
            $isMember = false;
            foreach ($anggota as $raw) {
                $uid = $this->extractUserId($raw);
                if ($uid !== null && (int) $uid === (int) $user->id) {
                    $isMember = true;
                    break;
                }
            }
            if (!$isMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda bukan anggota kelompok ini'
                ], 403);
            }
        }

        // Fetch latest submission for nilai_kelompok fallback
        $submission = \App\Models\PBLSubmission::where('kelompok_id', $kelompokId)
            ->orderBy('submitted_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $this->formatKelompokNilai(
                $kelompok,
                $user->role === 'siswa' ? (int) $user->id : null,
                $submission?->nilai
            )
        ], 200);
    }

    public function update(Request $request, string $pblId, string $kelompokId)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'guru'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya guru/admin yang dapat mengubah nilai individu'
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

        $payload = $this->extractPayload($request);

        if ($payload === null) {
            return response()->json([
                'success' => false,
                'message' => 'Payload nilai individu tidak ditemukan (kirimkan `nilai_individu`/`nilai` atau body array langsung)',
                'received_keys' => array_keys((array) $request->all()),
            ], 422);
        }

        try {
            $existing = is_array($kelompok->nilai_individu) ? $kelompok->nilai_individu : (array) ($kelompok->nilai_individu ?? []);
            $updates = $this->normalizeNilaiPayload($payload);

            // normalize anggota to allowed keys
            $allowed = $this->normalizeAnggotaKeys($kelompok->anggota ?? []);

            foreach ($updates as $siswaKey => $nilai) {
                if (!in_array($siswaKey, $allowed, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ada siswa yang bukan anggota kelompok',
                        'detail' => ['siswa_id' => $siswaKey]
                    ], 422);
                }
                if ($nilai === null) {
                    unset($existing[$siswaKey]);
                } else {
                    $existing[$siswaKey] = $nilai;
                }
            }

            $kelompok->nilai_individu = $existing;
            $kelompok->save();

            return response()->json([
                'success' => true,
                'message' => 'Nilai individu berhasil disimpan',
                'data' => $this->formatKelompokNilai($kelompok->fresh())
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format nilai individu tidak valid',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan nilai individu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function extractPayload(Request $request): mixed
    {
        // 1) Preferred keys
        $candidates = [
            'nilai_individu',
            'nilai',
            'nilaiIndividu',
            'nilaiIndividual',
            'nilai_individual',
            'nilaiIndividuList',
            'nilai_individu_list',
            'nilaiSiswa',
            'nilai_siswa',
            'items',
            'data',
            'payload',
            'members',
            'anggota',
            'scores',
        ];

        foreach ($candidates as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);
                // If wrapped in { data: { nilai_individu: ... } }
                if ($key === 'data' && is_array($value)) {
                    foreach (['nilai_individu', 'nilai', 'nilaiIndividu', 'items'] as $innerKey) {
                        if (array_key_exists($innerKey, $value)) {
                            return $value[$innerKey];
                        }
                    }
                }
                return $value;
            }
        }

        // 2) Raw JSON body might be an array at the root
        $jsonAll = $request->json()?->all();
        if (is_array($jsonAll) && $this->isListArray($jsonAll)) {
            return $jsonAll;
        }

        // 3) Fallback: request->all() might also be a list
        $all = $request->all();
        if (is_array($all) && $this->isListArray($all)) {
            return $all;
        }

        return null;
    }

    private function isListArray(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function formatKelompokNilai(Kelompok $kelompok, ?int $onlyUserId = null, ?int $nilaiKelompok = null): array
    {
        $anggota = is_array($kelompok->anggota) ? $kelompok->anggota : [];
        $nilaiMap = is_array($kelompok->nilai_individu) ? $kelompok->nilai_individu : (array) ($kelompok->nilai_individu ?? []);

        $ids = array_map(fn ($key) => $this->extractUserId($key), $anggota);
        $ids = array_values(array_filter($ids, fn ($id) => $id !== null));

        $users = User::query()
            ->select(['id', 'name', 'nis'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($anggota as $key) {
            $userId = $this->extractUserId($key);
            if ($userId === null) {
                continue;
            }

            if ($onlyUserId !== null && (int) $userId !== (int) $onlyUserId) {
                continue;
            }

            $siswaKey = 'siswa-' . $userId;
            $u = $users->get($userId);
            $override = array_key_exists($siswaKey, $nilaiMap) ? $nilaiMap[$siswaKey] : null;
            $finalNilai = $override !== null ? $override : $nilaiKelompok;
            
            $rows[] = [
                'siswa_id' => $siswaKey,
                'nama' => $u?->name,
                'nis' => $u?->nis,
                'nilai' => $finalNilai,
                'nilai_kelompok' => $nilaiKelompok,
                'nilai_override' => $override,
            ];
        }

        return [
            'pbl_id' => $kelompok->pbl_id,
            'kelompok_id' => $kelompok->id,
            'nama_kelompok' => $kelompok->nama_kelompok,
            'nilai_individu' => $rows,
        ];
    }

    /**
     * Accept payload shapes:
     * - {"siswa-1": 80, "siswa-2": 90}
     * - [{"siswa_id": "siswa-1", "nilai": 80}, ...]
     * - [{"id": "siswa-1", "nilai": 80}, ...]
     */
    private function normalizeNilaiPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            // associative map
            $isAssoc = array_keys($payload) !== range(0, count($payload) - 1);
            if ($isAssoc) {
                $out = [];
                foreach ($payload as $key => $value) {
                    $siswaKey = $this->normalizeSiswaKey($key);
                    $out[$siswaKey] = $this->normalizeNilaiOrNull($value);
                }
                return $out;
            }

            // list of objects
            $out = [];
            foreach ($payload as $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException('Item nilai_individu harus object/array');
                }
                $rawId = $item['siswa_id'] ?? $item['id'] ?? $item['user_id'] ?? null;
                if ($rawId === null) {
                    throw new \InvalidArgumentException('siswa_id tidak ditemukan di salah satu item');
                }
                if (!array_key_exists('nilai', $item)) {
                    throw new \InvalidArgumentException('nilai tidak ditemukan di salah satu item');
                }
                $siswaKey = $this->normalizeSiswaKey($rawId);
                $out[$siswaKey] = $this->normalizeNilaiOrNull($item['nilai']);
            }
            return $out;
        }

        throw new \InvalidArgumentException('nilai_individu harus berupa object/map atau array');
    }

    private function normalizeAnggotaKeys(array $anggota): array
    {
        $out = [];
        foreach ($anggota as $key) {
            $id = $this->extractUserId($key);
            if ($id === null) {
                continue;
            }
            $out[] = 'siswa-' . $id;
        }
        return array_values(array_unique($out));
    }

    private function normalizeSiswaKey(mixed $raw): string
    {
        $id = $this->extractUserId($raw);
        if ($id === null) {
            throw new \InvalidArgumentException('siswa_id tidak valid');
        }
        return 'siswa-' . $id;
    }

    private function extractUserId(mixed $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        if (is_int($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return (int) $raw;
        }
        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return null;
            }
            if (str_starts_with($raw, 'siswa-')) {
                $raw = substr($raw, strlen('siswa-'));
            }
            if (is_numeric($raw)) {
                return (int) $raw;
            }
        }
        return null;
    }

    private function normalizeNilaiOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && trim($value) === '') {
            return null;
        }
        if (is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        }
        if (!is_int($value)) {
            throw new \InvalidArgumentException('nilai harus integer 0-100');
        }
        if ($value < 0 || $value > 100) {
            throw new \InvalidArgumentException('nilai harus antara 0-100');
        }
        return $value;
    }
}
