<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Jurusan extends Model
{
    use HasFactory;

    // Custom primary key (string)
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama',
        'deskripsi',
    ];

    protected $hidden = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Auto-generate ID saat create
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                // Generate ID format: JUR-1, JUR-2, etc
                // Ambil semua ID yang ada dan cari yang terbesar
                $allIds = static::pluck('id')->map(function ($id) {
                    return (int) str_replace('JUR-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'JUR-' . ($maxNumber + 1);
            }
        });
    }
}
