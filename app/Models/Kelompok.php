<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kelompok extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pbl_id',
        'nama_kelompok',
        'anggota'
    ];

    protected $casts = [
        'anggota' => 'array',
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
                    return (int) str_replace('kelompok-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'kelompok-' . ($maxNumber + 1);
            }
        });
    }

    // Relationships
    public function pbl()
    {
        return $this->belongsTo(PBL::class, 'pbl_id', 'id');
    }

    public function submissions()
    {
        return $this->hasMany(PBLSubmission::class, 'kelompok_id', 'id');
    }

    // Get anggota details (siswa)
    public function anggotaDetails()
    {
        if (!$this->anggota || !is_array($this->anggota)) {
            return collect([]);
        }
        
        $siswaIds = array_map(function($id) {
            return (int) str_replace('siswa-', '', $id);
        }, $this->anggota);
        
        return User::whereIn('id', $siswaIds)->get();
    }
}
