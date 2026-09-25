<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'admin_input_code', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'admin_input_code'  => 'integer',
            'is_active'         => 'boolean',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /** Semua permission slug milik semua role user ini. */
    public function permissionSlugs(): array
    {
        return $this->roles
            ->flatMap(fn (Role $r) => $r->permissions->pluck('slug'))
            ->unique()
            ->all();
    }

    public function hasPermission(string $slug): bool
    {
        return $this->isSuperAdmin() || in_array($slug, $this->permissionSlugs(), true);
    }
}
