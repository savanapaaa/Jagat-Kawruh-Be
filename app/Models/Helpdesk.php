<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Helpdesk extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'siswa_id',
        'kategori',
        'judul',
        'pesan',
        'status',
        'balasan'
    ];

    protected $casts = [
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
                    return (int) str_replace('ticket-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'ticket-' . ($maxNumber + 1);
            }
        });
    }

    // Relationships
    public function siswa()
    {
        return $this->belongsTo(User::class, 'siswa_id');
    }
}
