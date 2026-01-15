<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class PBLSubmission extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pbl_id',
        'kelompok_id',
        'file_name',
        'file_path',
        'file_size',
        'catatan',
        'nilai',
        'feedback',
        'submitted_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
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
                    return (int) str_replace('submit-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'submit-' . ($maxNumber + 1);
            }
        });

        // Delete file when submission is deleted
        static::deleting(function ($model) {
            if ($model->file_path && Storage::exists($model->file_path)) {
                Storage::delete($model->file_path);
            }
        });
    }

    // Relationships
    public function pbl()
    {
        return $this->belongsTo(PBL::class, 'pbl_id', 'id');
    }

    public function kelompok()
    {
        return $this->belongsTo(Kelompok::class, 'kelompok_id', 'id');
    }
}
