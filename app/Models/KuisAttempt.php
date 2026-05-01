<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class KuisAttempt extends Model
{
    use HasFactory;

    protected $table = 'kuis_attempts';

    protected $fillable = [
        'kuis_id',
        'siswa_id',
        'token',
        'started_at',
        'ends_at',
        'submitted_at',
        'status',
        'retake_allowed',
        'retake_approved_by',
        'retake_approved_at',
        'score',
        'benar',
        'salah',
        'total_soal',
        'answers',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'submitted_at' => 'datetime',
        'retake_allowed' => 'boolean',
        'retake_approved_at' => 'datetime',
        'score' => 'float',
        'benar' => 'integer',
        'salah' => 'integer',
        'total_soal' => 'integer',
        'answers' => 'array',
    ];

    protected $hidden = [
        'answers', // Jangan expose jawaban di response default
    ];

    // Auto-generate token saat create
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->token)) {
                $model->token = Str::random(64);
            }
        });
    }

    // Relationships
    public function kuis()
    {
        return $this->belongsTo(Kuis::class, 'kuis_id', 'id');
    }

    public function siswa()
    {
        return $this->belongsTo(User::class, 'siswa_id', 'id');
    }

    // Helper: hitung remaining time dalam detik
    public function getRemainingSecondsAttribute()
    {
        if (!$this->ends_at) {
            return 0;
        }

        $now = now();
        if ($now->gte($this->ends_at)) {
            return 0;
        }

        return $now->diffInSeconds($this->ends_at);
    }

    // Helper: cek apakah attempt sudah expired
    public function isExpired()
    {
        if ($this->status === 'expired' || $this->status === 'submitted') {
            return true;
        }

        if ($this->ends_at && now()->gte($this->ends_at)) {
            return true;
        }

        return false;
    }

    // Helper: mark as expired
    public function markAsExpired()
    {
        if ($this->status === 'in_progress') {
            $this->status = 'expired';
            $this->save();
        }
    }
}
