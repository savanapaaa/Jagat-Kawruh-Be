<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Kuis extends Model
{
    use HasFactory;

    protected $table = 'kuis';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'judul',
        'kelas',
        'batas_waktu',
        'status',
        'created_by'
    ];

    protected $casts = [
        'kelas' => 'array',
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
                    return (int) str_replace('kuis-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'kuis-' . ($maxNumber + 1);
            }
        });
    }

    // Relationships
    public function soal()
    {
        return $this->hasMany(Soal::class, 'kuis_id', 'id')->orderBy('urutan');
    }

    public function hasil()
    {
        return $this->hasMany(HasilKuis::class, 'kuis_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Many-to-many relationship with Kelas
     * Pivot table: kuis_kelas
     */
    public function kelasRelation()
    {
        return $this->belongsToMany(Kelas::class, 'kuis_kelas', 'kuis_id', 'kelas_id')
            ->withTimestamps();
    }
}
