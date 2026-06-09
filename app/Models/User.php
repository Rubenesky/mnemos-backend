<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ActivityLog;

/**
 * Represents a system user with an assigned role (admin, editor, volunteer, or viewer).
 *
 * @package App\Models
 *
 * @property int                             $id
 * @property string                          $name
 * @property string                          $email
 * @property string                          $role
 * @property bool                            $is_active
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        'is_active',
        'last_login_at',
    ];

    /**
     * Default attribute values applied on model instantiation.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role'      => 'viewer',
        'is_active' => true,
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'expires_at'        => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    /** Returns all assets owned by this user. */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** Returns all activity log entries belonging to this user. */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** Returns true if the user has the admin role. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Returns true if the user has the editor role. */
    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    /** Returns true if the user has the viewer role. */
    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    /** Returns true if the user has the volunteer role and their account has not expired. */
    public function isVolunteer(): bool
    {
        if ($this->role !== 'volunteer') return false;
        if ($this->expires_at !== null && $this->expires_at->isPast()) return false;
        return true;
    }

    /** Returns true if the user's role matches the given role string. */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Returns true if the user account is active (not deactivated by an administrator).
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
