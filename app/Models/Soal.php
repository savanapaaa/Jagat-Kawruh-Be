<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Soal extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'kuis_id',
        'pertanyaan',
        'image',
        'pilihan',
        'jawaban',
        'urutan'
    ];

    protected $casts = [
        'pilihan' => 'array',
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
                    return (int) str_replace('soal-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'soal-' . ($maxNumber + 1);
            }
        });
    }

    // Relationships
    public function kuis()
    {
        return $this->belongsTo(Kuis::class, 'kuis_id', 'id');
    }
}
