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
 * Represents a system user with an assigned role (admin, editor, or viewer).
 *
 * @package App\Models
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
        'password' => 'hashed',
    ];

    // A user can own many assets
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    // A user can have many activity log entries
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
    // Check whether the user has the admin role
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Check whether the user has the editor role
    public function isEditor(): bool
    {
        return $this->role === 'editor';
    }

    // Check whether the user has the viewer role
    public function isViewer(): bool
    {
        return $this->role === 'viewer';
    }

    // Check whether the user has the given role
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}
