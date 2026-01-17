<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    protected $fillable = [
        'nama',
        'tingkat',
        'jurusan_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id');
    }

    /**
     * Many-to-many relationship with Materi
     */
    public function materis()
    {
        return $this->belongsToMany(Materi::class, 'materi_kelas', 'kelas_id', 'materi_id')
            ->withTimestamps();
    }

    /**
     * Many-to-many relationship with Kuis
     */
    public function kuis()
    {
        return $this->belongsToMany(Kuis::class, 'kuis_kelas', 'kelas_id', 'kuis_id')
            ->withTimestamps();
    }
}
