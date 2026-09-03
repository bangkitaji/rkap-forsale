<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Traits\Searchable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'bureau_id',
        'department_id',
        'directorate_id',
        'position',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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
            'must_change_password' => 'boolean',
        ];
    }

    // ── Organisation Relations ──

    public function bureau(): BelongsTo
    {
        return $this->belongsTo(Bureau::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }

    // ── RKAP Relations ──

    public function rkapSubmissions(): HasMany
    {
        return $this->hasMany(RkapSubmission::class, 'created_by');
    }

    public function rkapApprovals(): HasMany
    {
        return $this->hasMany(RkapApproval::class);
    }

    // ── Role Helper Methods ──

    public function isAdmin(): bool
    {
        $role = config('rkap.roles.admin', 'admin');
        return $this->hasRole($role) || $this->hasRole(['admin', 'administrator', 'superadmin']);
    }

    public function isKepalaBiro(): bool
    {
        $biroRole = config('rkap.roles.kepala_biro', 'kepala_biro');
        $userRole = config('rkap.roles.user', 'user');
        return $this->hasRole($biroRole) || $this->hasRole($userRole);
    }

    public function isKepalaDepartemen(): bool
    {
        $deptRole = config('rkap.roles.kepala_departemen', 'kepala_departemen');
        return $this->hasRole($deptRole);
    }

    public function isDireksi(): bool
    {
        $dirRole = config('rkap.roles.direksi', 'direksi');
        return $this->hasRole($dirRole);
    }

    public function isVerifikator(): bool
    {
        $verifRole = config('rkap.roles.verifikator', 'verifikator');
        return $this->hasRole($verifRole);
    }

    public function isDirekturUtama(): bool
    {
        $pdirRole = config('rkap.roles.direktur_utama', 'direktur_utama');
        return $this->hasRole($pdirRole);
    }

    public function isPresidentDirector(): bool
    {
        return $this->isDirekturUtama();
    }

    public function isDirekturFinance(): bool
    {
        return $this->isDireksi() && $this->directorate?->code === config('rkap.finance_directorate_code', 'HF');
    }

    public function getOrganizationNameAttribute(): string
    {
        if ($this->bureau) {
            return $this->bureau->name;
        }
        if ($this->department) {
            return $this->department->name;
        }
        if ($this->directorate) {
            return $this->directorate->name;
        }
        return '-';
    }
}
