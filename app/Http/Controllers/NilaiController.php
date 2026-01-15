<?php

namespace App\Http\Controllers;

use App\Models\HasilKuis;
use App\Models\PBLSubmission;
use App\Models\User;
use Illuminate\Http\Request;

class NilaiController extends Controller
{
    /**
     * Get Nilai Siswa (aggregate dari Kuis & PBL)
     * GET /api/nilai
     * Query params: siswa_id (optional for guru/admin), kelas (optional for guru/admin), type (kuis/pbl/all)
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $type = $request->get('type', 'all');

            // Jika siswa, hanya bisa lihat nilai sendiri
            if ($user->role === 'siswa') {
                $siswaId = $user->id;
            } else {
                // Guru/Admin bisa pilih siswa atau filter by kelas
                $siswaId = $request->get('siswa_id');
                $kelas = $request->get('kelas');
            }

            $data = [];

            // Get Nilai Kuis
            if ($type === 'kuis' || $type === 'all') {
                $kuisQuery = HasilKuis::with(['kuis:id,judul', 'siswa:id,name,kelas'])
                    ->orderBy('created_at', 'desc');

                if (isset($siswaId)) {
                    $kuisQuery->where('siswa_id', $siswaId);
                } elseif (isset($kelas) && $user->role !== 'siswa') {
                    $kuisQuery->whereHas('siswa', function($q) use ($kelas) {
                        $q->where('kelas', $kelas);
                    });
                }

                $nilaiKuis = $kuisQuery->get()->map(function($hasil) {
                    return [
                        'id' => 'nilai-kuis-' . $hasil->id,
                        'kuis_id' => $hasil->kuis_id,
                        'kuis_judul' => $hasil->kuis ? $hasil->kuis->judul : '-',
                        'siswa_id' => $hasil->siswa_id,
                        'siswa_nama' => $hasil->siswa ? $hasil->siswa->name : '-',
                        'kelas' => $hasil->siswa ? $hasil->siswa->kelas : '-',
                        'nilai' => $hasil->nilai,
                        'tanggal' => $hasil->created_at,
                        'type' => 'kuis'
                    ];
                });

                $data['kuis'] = $nilaiKuis;
                
                // Calculate average
                $avgKuis = $nilaiKuis->count() > 0 ? round($nilaiKuis->avg('nilai'), 2) : 0;
                $data['rata_rata_kuis'] = $avgKuis;
            }

            // Get Nilai PBL
            if ($type === 'pbl' || $type === 'all') {
                $pblQuery = PBLSubmission::with([
                    'pbl:id,judul',
                    'kelompok'
                ])
                    ->whereNotNull('nilai')
                    ->orderBy('submitted_at', 'desc');

                // Filter by siswa (check if siswa is in kelompok anggota)
                if (isset($siswaId)) {
                    $pblQuery->whereHas('kelompok', function($q) use ($siswaId) {
                        $q->whereJsonContains('anggota', (string)$siswaId);
                    });
                } elseif (isset($kelas) && $user->role !== 'siswa') {
                    // Filter by kelas through PBL
                    $pblQuery->whereHas('pbl', function($q) use ($kelas) {
                        $q->whereJsonContains('kelas', $kelas);
                    });
                }

                $nilaiPBL = $pblQuery->get()->map(function($submission) {
                    return [
                        'id' => 'nilai-pbl-' . $submission->id,
                        'project_id' => $submission->pbl_id,
                        'project_judul' => $submission->pbl ? $submission->pbl->judul : '-',
                        'kelompok_id' => $submission->kelompok_id,
                        'kelompok_nama' => $submission->kelompok ? $submission->kelompok->nama_kelompok : '-',
                        'nilai' => $submission->nilai,
                        'feedback' => $submission->feedback,
                        'tanggal' => $submission->submitted_at,
                        'type' => 'pbl'
                    ];
                });

                $data['pbl'] = $nilaiPBL;
                
                // Calculate average
                $avgPBL = $nilaiPBL->count() > 0 ? round($nilaiPBL->avg('nilai'), 2) : 0;
                $data['rata_rata_pbl'] = $avgPBL;
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data nilai',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Nilai by Kelas (Guru)
     * GET /api/nilai/kelas/{kelas}
     */
    public function getByKelas(string $kelas)
    {
        try {
            // Verify user is guru or admin
            $user = auth()->user();
            if (!in_array($user->role, ['admin', 'guru'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            // Get all siswa in this kelas
            $siswaList = User::where('role', 'siswa')
                ->where('kelas', $kelas)
                ->get();

            $result = [];

            foreach ($siswaList as $siswa) {
                // Get nilai kuis
                $nilaiKuis = HasilKuis::where('siswa_id', $siswa->id)
                    ->with('kuis:id,judul')
                    ->get()
                    ->map(function($hasil) {
                        return [
                            'kuis_id' => $hasil->kuis_id,
                            'kuis_judul' => $hasil->kuis ? $hasil->kuis->judul : null,
                            'nilai' => $hasil->nilai,
                            'tanggal' => $hasil->created_at
                        ];
                    });

                // Get nilai PBL (submissions where siswa is in kelompok anggota)
                $nilaiPBL = PBLSubmission::whereHas('kelompok', function($q) use ($siswa) {
                        $q->whereJsonContains('anggota', 'siswa-' . $siswa->id);
                    })
                    ->whereNotNull('nilai')
                    ->with('pbl:id,judul')
                    ->get()
                    ->map(function($submission) {
                        return [
                            'project_id' => $submission->pbl_id,
                            'project_judul' => $submission->pbl ? $submission->pbl->judul : null,
                            'nilai' => $submission->nilai,
                            'tanggal' => $submission->submitted_at
                        ];
                    });

                $result[] = [
                    'siswa_id' => 'siswa-' . $siswa->id,
                    'nama' => $siswa->name,
                    'nis' => $siswa->nis,
                    'kelas' => $siswa->kelas,
                    'nilai_kuis' => $nilaiKuis,
                    'nilai_pbl' => $nilaiPBL,
                    'rata_rata_kuis' => $nilaiKuis->avg('nilai'),
                    'rata_rata_pbl' => $nilaiPBL->avg('nilai')
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data nilai kelas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
