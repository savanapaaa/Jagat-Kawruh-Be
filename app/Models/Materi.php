<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Materi extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'judul',
        'pesan_pembelajaran',
        'link_video',
        'deskripsi',
        'kelas',
        'jurusan_id',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'tugas_enabled',
        'created_by'
    ];

    protected $casts = [
        'kelas' => 'array',
        'tugas_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Auto-generate ID
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $allIds = static::pluck('id')->map(function ($id) {
                    return (int) str_replace('materi-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'materi-' . ($maxNumber + 1);
            }
        });

        // Delete file when model is deleted
        static::deleting(function ($model) {
            if ($model->file_path && Storage::disk('public')->exists($model->file_path)) {
                Storage::disk('public')->delete($model->file_path);
            }
        });
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id');
    }

    /**
     * Many-to-many relationship with Kelas
     * Pivot table: materi_kelas
     */
    public function kelasRelation()
    {
        return $this->belongsToMany(Kelas::class, 'materi_kelas', 'materi_id', 'kelas_id')
            ->withTimestamps();
    }

    public function submissions()
    {
        return $this->hasMany(MateriSubmission::class, 'materi_id', 'id');
    }
}
