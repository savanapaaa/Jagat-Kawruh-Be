<?php

namespace App\Http\Controllers;

use App\Models\PanduanFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class PanduanController extends Controller
{
    public function show(Request $request, string $role)
    {
        $user = $request->user();

        if (!$user || $user->role !== $role) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki akses ke resource ini.',
            ], 403);
        }

        $panduan = PanduanFile::query()->where('role', $role)->first();

        if (!$panduan) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->toResponseData($panduan),
        ]);
    }

    public function store(Request $request, string $role)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'file' => 'required|file|mimes:pdf|max:20480',
        ], [
            'file.required' => 'File panduan wajib diupload',
            'file.mimes' => 'File panduan harus berupa PDF',
            'file.max' => 'File panduan maksimal 20MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 400);
        }

        $file = $request->file('file');
        $disk = env('PANDUAN_DISK', 'local');

        $objectKey = "panduan/{$role}/panduan.pdf";

        $putOptions = [];
        if ($disk === 's3') {
            $putOptions = [
                'visibility' => 'private',
                'ContentType' => 'application/pdf',
                'ContentDisposition' => 'inline',
            ];
        }

        Storage::disk($disk)->putFileAs("panduan/{$role}", $file, 'panduan.pdf', $putOptions);

        $panduan = PanduanFile::query()->updateOrCreate(
            ['role' => $role],
            [
                'title' => $request->input('title'),
                'object_key' => $objectKey,
                'mime_type' => 'application/pdf',
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $this->toResponseData($panduan),
        ]);
    }

    public function destroy(Request $request, string $role)
    {
        $panduan = PanduanFile::query()->where('role', $role)->first();

        if (!$panduan) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        $disk = env('PANDUAN_DISK', 'local');
        Storage::disk($disk)->delete($panduan->object_key);
        $panduan->delete();

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    private function toResponseData(PanduanFile $panduan): array
    {
        $expiresAt = now()->addMinutes(10);
        $disk = env('PANDUAN_DISK', 'local');

        if ($disk === 's3') {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
            $s3 = Storage::disk('s3');
            $pdfUrl = $s3->temporaryUrl(
                $panduan->object_key,
                $expiresAt,
                [
                    'ResponseContentType' => 'application/pdf',
                    'ResponseContentDisposition' => 'inline',
                ]
            );
        } else {
            // Local/private storage fallback: signed URL to a streaming endpoint.
            $pdfUrl = URL::temporarySignedRoute('panduan.file', $expiresAt, [
                'role' => $panduan->role,
            ]);
        }

        return [
            'id' => $panduan->id,
            'role' => $panduan->role,
            'title' => $panduan->title,
            'object_key' => $panduan->object_key,
            'mime_type' => $panduan->mime_type,
            'size_bytes' => $panduan->size_bytes,
            'uploaded_by' => $panduan->uploaded_by,
            'created_at' => $panduan->created_at,
            'updated_at' => $panduan->updated_at,
            'pdf_url' => $pdfUrl,
        ];
    }

    /**
     * Signed URL endpoint to stream PDF (used when PANDUAN_DISK != s3).
     */
    public function file(Request $request, string $role)
    {
        $panduan = PanduanFile::query()->where('role', $role)->first();

        if (!$panduan) {
            return response()->json([
                'success' => false,
                'message' => 'Panduan belum tersedia',
            ], 404);
        }

        $disk = env('PANDUAN_DISK', 'local');

        if ($disk === 's3') {
            // If someone hits this endpoint while using S3, just redirect to the presigned URL.
            /** @var \Illuminate\Filesystem\FilesystemAdapter $s3 */
            $s3 = Storage::disk('s3');
            return redirect()->away($s3->temporaryUrl(
                $panduan->object_key,
                now()->addMinutes(10),
                [
                    'ResponseContentType' => 'application/pdf',
                    'ResponseContentDisposition' => 'inline',
                ]
            ));
        }

        if (!Storage::disk($disk)->exists($panduan->object_key)) {
            return response()->json([
                'success' => false,
                'message' => 'File panduan tidak ditemukan',
            ], 404);
        }

        $absolutePath = Storage::disk($disk)->path($panduan->object_key);

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="panduan.pdf"',
        ]);
    }
}
