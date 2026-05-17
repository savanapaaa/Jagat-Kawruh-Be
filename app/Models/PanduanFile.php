<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanduanFile extends Model
{
    use HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_GURU = 'guru';
    public const ROLE_SISWA = 'siswa';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GURU,
        self::ROLE_SISWA,
    ];

    protected $table = 'panduan_files';

    protected $fillable = [
        'role',
        'title',
        'object_key',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
