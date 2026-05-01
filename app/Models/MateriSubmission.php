<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MateriSubmission extends Model
{
    use HasFactory;

    protected $table = 'materi_submissions';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'materi_id',
        'siswa_id',
        'catatan',
        'file_path',
        'file_name',
        'file_size',
        'submitted_at',
        'nilai',
        'feedback',
        'graded_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $allIds = static::pluck('id')->map(function ($id) {
                    return (int) str_replace('msub-', '', $id);
                });

                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'msub-' . ($maxNumber + 1);
            }
        });

        static::deleting(function ($model) {
            if ($model->file_path && Storage::disk('public')->exists($model->file_path)) {
                Storage::disk('public')->delete($model->file_path);
            }
        });
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'materi_id', 'id');
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'siswa_id', 'id');
    }
}
