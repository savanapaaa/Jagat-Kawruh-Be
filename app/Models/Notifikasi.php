<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notifikasi extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'judul',
        'pesan',
        'tipe',
        'read'
    ];

    protected $casts = [
        'read' => 'boolean',
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
                    return (int) str_replace('notif-', '', $id);
                });
                
                $maxNumber = $allIds->max() ?? 0;
                $model->id = 'notif-' . ($maxNumber + 1);
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
