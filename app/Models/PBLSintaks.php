<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PBLSintaks extends Model
{
    use HasFactory;

    protected $table = 'pbl_sintaks';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pbl_id',
        'judul',
        'instruksi',
        'urutan',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pbl()
    {
        return $this->belongsTo(PBL::class, 'pbl_id', 'id');
    }
}
