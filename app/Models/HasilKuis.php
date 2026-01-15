<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HasilKuis extends Model
{
    use HasFactory;

    protected $table = 'hasil_kuis';

    protected $fillable = [
        'kuis_id',
        'siswa_id',
        'jawaban',
        'nilai',
        'benar',
        'salah',
        'waktu_mulai',
        'waktu_selesai'
    ];

    protected $casts = [
        'jawaban' => 'array',
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function kuis()
    {
        return $this->belongsTo(Kuis::class, 'kuis_id', 'id');
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'siswa_id');
    }
}
