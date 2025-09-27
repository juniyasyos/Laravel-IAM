<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Config;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected ?Application $currentApplication = null;

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

    public function setCurrentApplication(?Application $application): void
    {
        $this->currentApplication = $application;

        $teamId = $application?->getKey();
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
    }

    public function currentApplication(): ?Application
    {
        return $this->currentApplication;
    }

    /**
     * Applications where the user holds roles within a team-scoped context.
     */
    public function applications(): BelongsToMany
    {
        $pivotTable = Config::get('permission.table_names.model_has_roles');
        $teamForeignKey = Config::get('permission.column_names.team_foreign_key');
        $modelMorphKey = Config::get('permission.column_names.model_morph_key');

        return $this->belongsToMany(Application::class, $pivotTable, $modelMorphKey, $teamForeignKey)
            ->wherePivot('model_type', self::class)
            ->withPivot('role_id');
    }
}
