<?php

namespace App\Http\Controllers;

use App\Models\KuisAttempt;
use App\Models\Kelompok;
use App\Models\PBLSubmission;
use App\Models\MateriSubmission;
use App\Models\User;
use App\Models\Kelas;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NilaiController extends Controller
{
    /**
     * Get Nilai Siswa (aggregate dari Kuis & PBL)
     * GET /api/nilai
     * Query params: siswa_id (optional for guru/admin), kelas (optional for guru/admin), type (kuis/pbl/all)
     * 
     * NOTE: Sekarang menggunakan KuisAttempt (bukan HasilKuis yang deprecated)
     * Untuk siswa dengan multiple attempts, ambil nilai tertinggi per kuis
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
                $kelasId = $request->get('kelas_id'); // Support kelas_id juga

                // Normalisasi input kosong dari FE
                $kelas = is_string($kelas) ? trim($kelas) : $kelas;
                if ($kelas === '') {
                    $kelas = null;
                }
                if ($kelasId === '') {
                    $kelasId = null;
                }
            }

            $data = [];

            // Get Nilai Kuis dari KuisAttempt (hanya yang sudah submitted/expired)
            if ($type === 'kuis' || $type === 'all') {
                $kuisQuery = KuisAttempt::with(['kuis:id,judul', 'siswa:id,name,kelas,kelas_id', 'siswa.kelasRelation:id,nama'])
                    ->whereIn('status', ['submitted', 'expired'])
                    ->orderBy('submitted_at', 'desc');

                if ($user->role === 'guru') {
                    $kuisQuery->whereHas('kuis', function($q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
                }

                if (isset($siswaId)) {
                    $kuisQuery->where('siswa_id', $siswaId);
                } elseif ($user->role !== 'siswa') {
                    // Prioritaskan filter by nama kelas jika ada (contoh: X RPL 2)
                    if (isset($kelas)) {
                        $kuisQuery->whereHas('siswa', function($q) use ($kelas) {
                            $q->where('kelas', $kelas)
                                ->orWhereHas('kelasRelation', function($kelasQ) use ($kelas) {
                                    $kelasQ->where('nama', $kelas);
                                });
                        });
                    } elseif (isset($kelasId)) {
                        $kuisQuery->whereHas('siswa', function($q) use ($kelasId) {
                            $q->where('kelas_id', $kelasId);
                        });
                    }
                }

                $nilaiKuis = $kuisQuery->get()->map(function($attempt) {
                    return [
                        'id' => 'nilai-kuis-' . $attempt->id,
                        'attempt_id' => $attempt->id,
                        'kuis_id' => $attempt->kuis_id,
                        'kuis_judul' => $attempt->kuis ? $attempt->kuis->judul : '-',
                        'siswa_id' => $attempt->siswa_id,
                        'siswa_nama' => $attempt->siswa ? $attempt->siswa->name : '-',
                        'kelas' => $attempt->siswa ? ($attempt->siswa->kelasRelation->nama ?? $attempt->siswa->kelas) : '-',
                        'kelas_id' => $attempt->siswa ? $attempt->siswa->kelas_id : null,
                        'nilai' => $attempt->score,
                        'benar' => $attempt->benar,
                        'salah' => $attempt->salah,
                        'total_soal' => $attempt->total_soal,
                        'status' => $attempt->status,
                        'started_at' => $attempt->started_at,
                        'submitted_at' => $attempt->submitted_at,
                        'tanggal' => $attempt->submitted_at,
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
                // For siswa (or when siswa_id explicitly provided):
                // build PBL rows from kelompok membership so that projects still appear
                // even when nilai kelompok belum di-set.
                if (isset($siswaId)) {
                    $kelompokQuery = Kelompok::with('pbl:id,judul')
                        ->where(function ($q) use ($siswaId) {
                            $q->whereJsonContains('anggota', $siswaId)
                                ->orWhereJsonContains('anggota', (string) $siswaId)
                                ->orWhereJsonContains('anggota', 'siswa-' . $siswaId);
                        });

                    if ($user->role === 'guru') {
                        $kelompokQuery->whereHas('pbl', function($q) use ($user) {
                            $q->where('created_by', $user->id);
                        });
                    }

                    $kelompokRows = $kelompokQuery->get();

                    $kelompokIds = $kelompokRows->pluck('id')->values()->all();
                    $latestByKelompok = collect();
                    if (count($kelompokIds) > 0) {
                        $latestByKelompok = PBLSubmission::query()
                            ->whereIn('kelompok_id', $kelompokIds)
                            ->orderBy('submitted_at', 'desc')
                            ->get(['id', 'pbl_id', 'kelompok_id', 'nilai', 'feedback', 'submitted_at'])
                            ->groupBy('kelompok_id')
                            ->map(fn ($g) => $g->first());
                    }

                    $nilaiPBL = $kelompokRows->map(function (Kelompok $kelompok) use ($siswaId, $latestByKelompok) {
                        $latest = $latestByKelompok->get($kelompok->id);
                        $nilaiKelompok = $latest?->nilai;

                        $overrideKey = 'siswa-' . $siswaId;
                        $overrideMap = is_array($kelompok->nilai_individu) ? $kelompok->nilai_individu : (array) ($kelompok->nilai_individu ?? []);
                        $hasOverride = array_key_exists($overrideKey, $overrideMap);
                        $nilaiFinal = $hasOverride ? $overrideMap[$overrideKey] : $nilaiKelompok;

                        return [
                            // Keep existing shape keys for FE compatibility
                            'id' => 'nilai-pbl-' . ($latest?->id ?? ($kelompok->pbl_id . '-' . $kelompok->id)),
                            'project_id' => $kelompok->pbl_id,
                            'project_judul' => $kelompok->pbl ? $kelompok->pbl->judul : '-',
                            'kelompok_id' => $kelompok->id,
                            'kelompok_nama' => $kelompok->nama_kelompok,
                            'nilai' => $nilaiFinal,
                            // Extra context (safe additions)
                            'nilai_kelompok' => $nilaiKelompok,
                            'nilai_override' => $hasOverride ? $overrideMap[$overrideKey] : null,
                            'feedback' => $latest?->feedback,
                            'tanggal' => $latest?->submitted_at,
                            'type' => 'pbl'
                        ];
                    })
                    ->sortByDesc(function ($row) {
                        return $row['tanggal'] ?? $row['project_id'];
                    })
                    ->values();
                } else {
                    // Legacy behavior for guru/admin aggregate listing
                    $pblQuery = PBLSubmission::with([
                        'pbl:id,judul',
                        'kelompok'
                    ])
                        ->whereNotNull('nilai')
                        ->orderBy('submitted_at', 'desc');

                    if ($user->role === 'guru') {
                        $pblQuery->whereHas('pbl', function($q) use ($user) {
                            $q->where('created_by', $user->id);
                        });
                    }

                    if (isset($kelas) && $user->role !== 'siswa') {
                        $pblQuery->whereHas('pbl', function($q) use ($kelas) {
                            $q->where('kelas', $kelas);
                        });
                    } elseif (isset($kelasId) && $user->role !== 'siswa') {
                        $pblQuery->whereHas('pbl.kelasRelation', function($q) use ($kelasId) {
                            $q->where('kelas.id', $kelasId);
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
                }

                $data['pbl'] = $nilaiPBL;
                
                // Calculate average
                $avgPBL = 0;
                $nilaiUntukAvg = $nilaiPBL->filter(function ($row) {
                    return isset($row['nilai']) && is_numeric($row['nilai']);
                });
                if ($nilaiUntukAvg->count() > 0) {
                    $avgPBL = round($nilaiUntukAvg->avg('nilai'), 2);
                }
                $data['rata_rata_pbl'] = $avgPBL;
            }

            // Get Nilai Materi
            if ($type === 'materi' || $type === 'all') {
                $materiQuery = MateriSubmission::with(['materi:id,judul', 'siswa:id,name,kelas,kelas_id', 'siswa.kelasRelation:id,nama'])
                    ->whereNotNull('nilai')
                    ->orderBy('submitted_at', 'desc');

                if ($user->role === 'guru') {
                    $materiQuery->whereHas('materi', function($q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
                }

                if (isset($siswaId)) {
                    $materiQuery->where('siswa_id', $siswaId);
                } elseif ($user->role !== 'siswa') {
                    if (isset($kelas)) {
                        $materiQuery->whereHas('siswa', function($q) use ($kelas) {
                            $q->where('kelas', $kelas)
                                ->orWhereHas('kelasRelation', function($kelasQ) use ($kelas) {
                                    $kelasQ->where('nama', $kelas);
                                });
                        });
                    } elseif (isset($kelasId)) {
                        $materiQuery->whereHas('siswa', function($q) use ($kelasId) {
                            $q->where('kelas_id', $kelasId);
                        });
                    }
                }

                $nilaiMateri = $materiQuery->get()->map(function($submission) {
                    return [
                        'id' => 'nilai-materi-' . $submission->id,
                        'submission_id' => $submission->id,
                        'materi_id' => $submission->materi_id,
                        'materi_judul' => $submission->materi ? $submission->materi->judul : '-',
                        'siswa_id' => $submission->siswa_id,
                        'siswa_nama' => $submission->siswa ? $submission->siswa->name : '-',
                        'kelas' => $submission->siswa ? ($submission->siswa->kelasRelation->nama ?? $submission->siswa->kelas) : '-',
                        'kelas_id' => $submission->siswa ? $submission->siswa->kelas_id : null,
                        'nilai' => $submission->nilai,
                        'feedback' => $submission->feedback,
                        'tanggal' => $submission->submitted_at,
                        'type' => 'materi'
                    ];
                });

                $data['materi'] = $nilaiMateri;
                
                $avgMateri = $nilaiMateri->count() > 0 ? round($nilaiMateri->avg('nilai'), 2) : 0;
                $data['rata_rata_materi'] = $avgMateri;
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
     * 
     * NOTE: Sekarang menggunakan KuisAttempt (bukan HasilKuis)
     * Parameter bisa berupa kelas_id (integer) atau nama kelas (string)
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

            // Support both kelas_id (integer) and kelas name (string)
            $siswaQuery = User::where('role', 'siswa');
            if (is_numeric($kelas)) {
                $siswaQuery->where('kelas_id', $kelas);
            } else {
                $siswaQuery->where(function($q) use ($kelas) {
                    $q->where('kelas', $kelas)
                        ->orWhereHas('kelasRelation', function($kelasQ) use ($kelas) {
                            $kelasQ->where('nama', $kelas);
                        });
                });
            }
            $siswaList = $siswaQuery->get();

            $result = [];

            foreach ($siswaList as $siswa) {
                // Get nilai kuis dari KuisAttempt (hanya submitted/expired)
                $kuisQuery = KuisAttempt::where('siswa_id', $siswa->id)
                    ->whereIn('status', ['submitted', 'expired'])
                    ->with('kuis:id,judul');

                if ($user->role === 'guru') {
                    $kuisQuery->whereHas('kuis', function($q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
                }

                $nilaiKuis = $kuisQuery->get()
                    ->map(function($attempt) {
                        return [
                            'attempt_id' => $attempt->id,
                            'kuis_id' => $attempt->kuis_id,
                            'kuis_judul' => $attempt->kuis ? $attempt->kuis->judul : null,
                            'nilai' => $attempt->score,
                            'benar' => $attempt->benar,
                            'salah' => $attempt->salah,
                            'total_soal' => $attempt->total_soal,
                            'status' => $attempt->status,
                            'tanggal' => $attempt->submitted_at
                        ];
                    });

                // Get nilai PBL (submissions where siswa is in kelompok anggota)
                $pblQuery = PBLSubmission::whereHas('kelompok', function($q) use ($siswa) {
                        $q->whereJsonContains('anggota', $siswa->id)
                            ->orWhereJsonContains('anggota', (string)$siswa->id)
                            ->orWhereJsonContains('anggota', 'siswa-' . $siswa->id);
                    })
                    ->whereNotNull('nilai')
                    ->with('pbl:id,judul');

                if ($user->role === 'guru') {
                    $pblQuery->whereHas('pbl', function($q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
                }

                $nilaiPBL = $pblQuery->get()
                    ->map(function($submission) {
                        return [
                            'project_id' => $submission->pbl_id,
                            'project_judul' => $submission->pbl ? $submission->pbl->judul : null,
                            'nilai' => $submission->nilai,
                            'tanggal' => $submission->submitted_at
                        ];
                    });

                // Get nilai Materi
                $materiQuery = MateriSubmission::where('siswa_id', $siswa->id)
                    ->whereNotNull('nilai')
                    ->with('materi:id,judul');

                if ($user->role === 'guru') {
                    $materiQuery->whereHas('materi', function($q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
                }

                $nilaiMateri = $materiQuery->get()
                    ->map(function($submission) {
                        return [
                            'submission_id' => $submission->id,
                            'materi_id' => $submission->materi_id,
                            'materi_judul' => $submission->materi ? $submission->materi->judul : null,
                            'nilai' => $submission->nilai,
                            'tanggal' => $submission->submitted_at
                        ];
                    });

                $result[] = [
                    'siswa_id' => 'siswa-' . $siswa->id,
                    'nama' => $siswa->name,
                    'nis' => $siswa->nis,
                    'kelas' => $siswa->kelasRelation->nama ?? $siswa->kelas,
                    'nilai_kuis' => $nilaiKuis,
                    'nilai_pbl' => $nilaiPBL,
                    'nilai_materi' => $nilaiMateri,
                    'rata_rata_kuis' => $nilaiKuis->avg('nilai'),
                    'rata_rata_pbl' => $nilaiPBL->avg('nilai'),
                    'rata_rata_materi' => $nilaiMateri->avg('nilai')
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

    /**
     * Export Nilai to Excel (Guru/Admin)
     * GET /api/nilai/export
     * Query params: siswa_id (optional), kelas (optional), kelas_id (optional), type (kuis/pbl/all)
     */
    public function exportExcel(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user || !in_array($user->role, ['admin', 'guru'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses'
                ], 403);
            }

            $type = $request->get('type', 'all');
            $siswaId = $request->get('siswa_id');
            $kelas = $request->get('kelas');
            $kelasId = $request->get('kelas_id');

            $kelas = is_string($kelas) ? trim($kelas) : $kelas;
            if ($kelas === '') {
                $kelas = null;
            }
            if ($kelasId === '') {
                $kelasId = null;
            }

            $spreadsheet = new Spreadsheet();
            // Remove default sheet so we can control names/order
            $spreadsheet->removeSheetByIndex(0);

            if ($type === 'kuis' || $type === 'all') {
                $rows = $this->buildKuisRowsForExport($user, $siswaId, $kelas, $kelasId);
                $this->addSheet($spreadsheet, 'Kuis', [
                    'Judul Kuis',
                    'Nama Siswa',
                    'Kelas',
                    'Nilai',
                ], $rows);
            }

            if ($type === 'pbl' || $type === 'all') {
                $rows = $this->buildPblRowsForExport($user, $siswaId, $kelas, $kelasId);
                $this->addSheet($spreadsheet, 'PBL', [
                    'Judul Project',
                    'Nama Siswa',
                    'Kelas',
                    'Nilai',
                ], $rows);
            }

            if ($type === 'materi' || $type === 'all') {
                $rows = $this->buildMateriRowsForExport($user, $siswaId, $kelas, $kelasId);
                $this->addSheet($spreadsheet, 'Materi', [
                    'Judul Materi',
                    'Nama Siswa',
                    'Kelas',
                    'Nilai',
                ], $rows);
            }

            if ($spreadsheet->getSheetCount() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data untuk diexport'
                ], 400);
            }

            $tmpDir = storage_path('app/tmp');
            if (!is_dir($tmpDir)) {
                @mkdir($tmpDir, 0777, true);
            }

            $filename = 'nilai_export_' . now()->format('Ymd_His') . '.xlsx';
            $filePath = $tmpDir . DIRECTORY_SEPARATOR . $filename;

            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            return response()->download($filePath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal export nilai ke Excel',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function addSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(substr($title, 0, 31));

        // Header
        foreach ($headers as $colIndex => $header) {
            $sheet->setCellValue([$colIndex + 1, 1], $header);
        }

        // Rows
        $rowIndex = 2;
        foreach ($rows as $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValue([$colIndex + 1, $rowIndex], $value);
            }
            $rowIndex++;
        }
    }

    private function buildKuisRowsForExport($authUser, $siswaId, $kelas, $kelasId): array
    {
        $kuisQuery = KuisAttempt::with(['kuis:id,judul', 'siswa:id,name,kelas,kelas_id', 'siswa.kelasRelation:id,nama'])
            ->whereIn('status', ['submitted', 'expired'])
            ->orderBy('submitted_at', 'desc');

        if ($authUser->role === 'guru') {
            $kuisQuery->whereHas('kuis', function($q) use ($authUser) {
                $q->where('created_by', $authUser->id);
            });
        }

        if (isset($siswaId)) {
            $kuisQuery->where('siswa_id', $siswaId);
        } else {
            if (isset($kelas)) {
                $kuisQuery->whereHas('siswa', function($q) use ($kelas) {
                    $q->where('kelas', $kelas)
                        ->orWhereHas('kelasRelation', function($kelasQ) use ($kelas) {
                            $kelasQ->where('nama', $kelas);
                        });
                });
            } elseif (isset($kelasId)) {
                $kuisQuery->whereHas('siswa', function($q) use ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                });
            }
        }

        $attempts = $kuisQuery->get();

        $rows = [];
        foreach ($attempts as $attempt) {
            $kelasNama = $attempt->siswa ? ($attempt->siswa->kelasRelation->nama ?? $attempt->siswa->kelas) : null;
            $rows[] = [
                $attempt->kuis ? $attempt->kuis->judul : null,
                $attempt->siswa ? $attempt->siswa->name : null,
                $kelasNama,
                $attempt->score,
            ];
        }

        return $rows;
    }

    private function buildPblRowsForExport($authUser, $siswaId, $kelas, $kelasId): array
    {
        $pblQuery = PBLSubmission::with(['pbl:id,judul,kelas', 'pbl.kelasRelation:id,nama', 'kelompok'])
            ->whereNotNull('nilai')
            ->orderBy('submitted_at', 'desc');

        if ($authUser->role === 'guru') {
            $pblQuery->whereHas('pbl', function($q) use ($authUser) {
                $q->where('created_by', $authUser->id);
            });
        }

        if (isset($siswaId)) {
            $pblQuery->whereHas('kelompok', function($q) use ($siswaId) {
                $q->whereJsonContains('anggota', $siswaId)
                    ->orWhereJsonContains('anggota', (string)$siswaId)
                    ->orWhereJsonContains('anggota', 'siswa-' . $siswaId);
            });
        } elseif (isset($kelas)) {
            $pblQuery->whereHas('pbl', function($q) use ($kelas) {
                $q->whereJsonContains('kelas', $kelas);
            });
        } elseif (isset($kelasId)) {
            $pblQuery->whereHas('pbl.kelas', function($q) use ($kelasId) {
                $q->where('id', $kelasId);
            });
        }

        $subs = $pblQuery->get();
        $rows = [];

        $anggotaIds = [];
        foreach ($subs as $submission) {
            $anggota = $submission->kelompok ? $submission->kelompok->anggota : null;
            if (!is_array($anggota)) {
                continue;
            }
            foreach ($anggota as $rawId) {
                if (is_int($rawId) || (is_string($rawId) && ctype_digit($rawId))) {
                    $anggotaIds[] = (int) $rawId;
                    continue;
                }
                if (is_string($rawId) && str_starts_with($rawId, 'siswa-')) {
                    $anggotaIds[] = (int) str_replace('siswa-', '', $rawId);
                }
            }
        }
        $anggotaIds = array_values(array_unique(array_filter($anggotaIds)));
        $anggotaNamesById = empty($anggotaIds)
            ? collect([])
            : User::query()->whereIn('id', $anggotaIds)->pluck('name', 'id');

        $kelasNamaFromId = null;
        if (isset($kelasId)) {
            $kelasNamaFromId = Kelas::query()->where('id', $kelasId)->value('nama');
        }

        foreach ($subs as $submission) {
            $kelasNama = null;
            if (isset($kelas)) {
                $kelasNama = $kelas;
            } elseif (isset($kelasId)) {
                $kelasNama = $kelasNamaFromId;
            }

            if (!$kelasNama && $submission->pbl) {
                $kelasFromRelation = $submission->pbl->kelasRelation?->pluck('nama')?->filter()?->values()?->all();
                if (!empty($kelasFromRelation)) {
                    $kelasNama = implode(', ', $kelasFromRelation);
                } elseif (!empty($submission->pbl->kelas)) {
                    $decoded = json_decode($submission->pbl->kelas, true);
                    if (is_array($decoded)) {
                        $kelasNama = implode(', ', array_values(array_filter($decoded)));
                    } elseif (is_string($submission->pbl->kelas)) {
                        $kelasNama = $submission->pbl->kelas;
                    }
                }
            }

            $anggotaNames = [];
            $anggota = $submission->kelompok ? $submission->kelompok->anggota : null;
            if (is_array($anggota)) {
                foreach ($anggota as $rawId) {
                    $id = null;
                    if (is_int($rawId) || (is_string($rawId) && ctype_digit($rawId))) {
                        $id = (int) $rawId;
                    } elseif (is_string($rawId) && str_starts_with($rawId, 'siswa-')) {
                        $id = (int) str_replace('siswa-', '', $rawId);
                    }

                    if ($id && isset($anggotaNamesById[$id])) {
                        $anggotaNames[] = $anggotaNamesById[$id];
                    }
                }
            }
            $anggotaNames = array_values(array_unique(array_filter($anggotaNames)));
            $anggotaText = !empty($anggotaNames) ? implode(', ', $anggotaNames) : null;

            $rows[] = [
                $submission->pbl ? $submission->pbl->judul : null,
                $anggotaText,
                $kelasNama,
                $submission->nilai,
            ];
        }

        return $rows;
    }

    private function buildMateriRowsForExport($authUser, $siswaId, $kelas, $kelasId): array
    {
        $materiQuery = MateriSubmission::with(['materi:id,judul', 'siswa:id,name,kelas,kelas_id', 'siswa.kelasRelation:id,nama'])
            ->whereNotNull('nilai')
            ->orderBy('submitted_at', 'desc');

        if ($authUser->role === 'guru') {
            $materiQuery->whereHas('materi', function($q) use ($authUser) {
                $q->where('created_by', $authUser->id);
            });
        }

        if (isset($siswaId)) {
            $materiQuery->where('siswa_id', $siswaId);
        } else {
            if (isset($kelas)) {
                $materiQuery->whereHas('siswa', function($q) use ($kelas) {
                    $q->where('kelas', $kelas)
                        ->orWhereHas('kelasRelation', function($kelasQ) use ($kelas) {
                            $kelasQ->where('nama', $kelas);
                        });
                });
            } elseif (isset($kelasId)) {
                $materiQuery->whereHas('siswa', function($q) use ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                });
            }
        }

        $submissions = $materiQuery->get();

        $rows = [];
        foreach ($submissions as $sub) {
            $kelasNama = $sub->siswa ? ($sub->siswa->kelasRelation->nama ?? $sub->siswa->kelas) : null;
            $rows[] = [
                $sub->materi ? $sub->materi->judul : null,
                $sub->siswa ? $sub->siswa->name : null,
                $kelasNama,
                $sub->nilai,
            ];
        }

        return $rows;
    }
}
