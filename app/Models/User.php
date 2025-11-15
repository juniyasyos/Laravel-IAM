<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Domain\Iam\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
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
            'active' => 'boolean',
        ];
    }

    /**
     * Get all application roles for this user.
     */
    public function applicationRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Iam\Models\ApplicationRole::class,
            'iam_user_application_roles',
            'user_id',
            'role_id'
        )
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Get all access profiles assigned to this user.
     */
    public function accessProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domain\Iam\Models\AccessProfile::class,
            'user_access_profiles',
            'user_id',
            'access_profile_id'
        )
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    /**
     * Get all application roles via access profiles.
     * This returns roles that are assigned through access profiles.
     */
    public function rolesViaAccessProfiles()
    {
        return \App\Domain\Iam\Models\ApplicationRole::query()
            ->whereIn('id', function ($query) {
                $query->select('role_id')
                    ->from('access_profile_role_iam_map')
                    ->whereIn('access_profile_id', function ($subQuery) {
                        $subQuery->select('access_profile_id')
                            ->from('user_access_profiles')
                            ->where('user_id', $this->id);
                    });
            });
    }

    /**
     * Get all effective application roles (direct + via access profiles).
     */
    public function effectiveApplicationRoles()
    {
        $directRoles = $this->applicationRoles()->pluck('iam_roles.id');
        $profileRoles = $this->rolesViaAccessProfiles()->pluck('iam_roles.id');

        return \App\Domain\Iam\Models\ApplicationRole::query()
            ->whereIn('id', $directRoles->merge($profileRoles)->unique());
    }

    /**
     * Get user's roles grouped by application as: ['app_key' => ['slug1', 'slug2'], ...].
     */
    public function rolesByApp(): array
    {
        $roles = $this->applicationRoles()->with('application')->get();

        $grouped = [];
        foreach ($roles as $role) {
            $appKey = $role->application->app_key;
            if (! isset($grouped[$appKey])) {
                $grouped[$appKey] = [];
            }
            $grouped[$appKey][] = $role->slug;
        }

        return $grouped;
    }

    /**
     * Get list of app_keys this user has access to.
     */
    public function accessibleApps(): array
    {
        return $this->applicationRoles()
            ->with('application')
            ->get()
            ->pluck('application.app_key')
            ->unique()
            ->values()
            ->toArray();
    }
}
