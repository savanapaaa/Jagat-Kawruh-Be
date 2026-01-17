<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'nisn',
        'nis',
        'nip',
        'kelas',
        'kelas_id',
        'jurusan_id',
        'kelas_diampu',
        'phone',
        'address',
        'avatar',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'kelas_diampu' => 'array',
        ];
    }

    /**
     * Get nama attribute (alias for name)
     */
    public function getNamaAttribute()
    {
        return $this->attributes['name'];
    }

    /**
     * Set nama attribute (alias for name)
     */
    public function setNamaAttribute($value)
    {
        $this->attributes['name'] = $value;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is guru
     */
    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    /**
     * Check if user is siswa
     */
    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    /**
     * Check if user has role
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }
        return $this->role === $roles;
    }

    /**
     * Relationship: User belongs to Jurusan
     */
    public function jurusan()
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id');
    }

    /**
     * Relationship: User (Siswa) belongs to Kelas
     */
    public function kelasRelation()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }
}
