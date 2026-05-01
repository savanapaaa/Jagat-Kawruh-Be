<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PBLProgress extends Model
{
    use HasFactory;

    protected $table = 'pbl_progress';

    protected $fillable = [
        'pbl_id',
        'sintaks_id',
        'kelompok_id',
        'catatan',
        'file_path',
        'file_name',
        'submitted_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function pbl()
    {
        return $this->belongsTo(PBL::class, 'pbl_id', 'id');
    }

    public function sintaks()
    {
        return $this->belongsTo(PBLSintaks::class, 'sintaks_id', 'id');
    }

    public function kelompok()
    {
        return $this->belongsTo(Kelompok::class, 'kelompok_id', 'id');
    }

    /**
     * Get file URL
     */
    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }
        return null;
    }
}
