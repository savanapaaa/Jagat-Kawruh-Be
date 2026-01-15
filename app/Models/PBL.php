<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PBL extends Model
{
    use HasFactory;

    protected $table = 'pbls';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'judul',
        'masalah',
        'tujuan_pembelajaran',
        'panduan',
        'referensi',
        'kelas',
        'jurusan_id',
        'status',
        'deadline',
        'created_by'
    ];

    protected $casts = [
        'deadline' => 'date',
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
                    return (int) str_replace('pbl-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'pbl-' . ($maxNumber + 1);
            }
        });
    }

    // Relationships
    public function kelompok()
    {
        return $this->hasMany(Kelompok::class, 'pbl_id', 'id');
    }

    public function submissions()
    {
        return $this->hasMany(PBLSubmission::class, 'pbl_id', 'id');
    }

    public function sintaks()
    {
        return $this->hasMany(PBLSintaks::class, 'pbl_id', 'id')->orderBy('urutan');
    }

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
