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
        return $this->hasRole('admin');
    }

    public function isKepalaBiro(): bool
    {
        return $this->hasRole('kepala_biro');
    }

    public function isKepalaDepartemen(): bool
    {
        return $this->hasRole('kepala_departemen');
    }

    public function isDireksi(): bool
    {
        return $this->hasRole('direksi');
    }

    public function isVerifikator(): bool
    {
        return $this->hasRole('verifikator');
    }

    public function isPresidentDirector(): bool
    {
        return $this->hasRole('president_director');
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
