<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PBLKontribusi extends Model
{
    use HasFactory;

    protected $table = 'pbl_kontribusi';

    protected $fillable = [
        'pbl_id',
        'kelompok_id',
        'sintaks_id',
        'siswa_id',
        'catatan',
        'file_path',
        'file_name',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pbl()
    {
        return $this->belongsTo(PBL::class, 'pbl_id', 'id');
    }

    public function kelompok()
    {
        return $this->belongsTo(Kelompok::class, 'kelompok_id', 'id');
    }

    public function sintaks()
    {
        return $this->belongsTo(PBLSintaks::class, 'sintaks_id', 'id');
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'siswa_id', 'id');
    }

    public function getFileUrlAttribute()
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }
}
