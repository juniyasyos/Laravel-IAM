<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class Application extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'app_key',
        'name',
        'description',
        'enabled',
        'redirect_uris',
        'logo_url',
        'created_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'redirect_uris' => 'array',
        'enabled' => 'boolean',
    ];

    /**
     * Scope only enabled applications.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Find an application by its key or throw.
     */
    public static function findByKey(string $key): self
    {
        return static::where('app_key', Str::slug($key, '.'))->firstOrFail();
    }

    /**
     * Users that have roles scoped to this application.
     */
    public function users(): BelongsToMany
    {
        $pivotTable = Config::get('permission.table_names.model_has_roles');
        $teamForeignKey = Config::get('permission.column_names.team_foreign_key');
        $modelKey = Config::get('permission.column_names.model_morph_key');

        return $this->belongsToMany(User::class, $pivotTable, $teamForeignKey, $modelKey)
            ->wherePivot('model_type', User::class)
            ->withPivot('role_id');
    }

    /**
     * Roles scoped to this application.
     */
    public function roles(): HasMany
    {
        $teamForeignKey = Config::get('permission.column_names.team_foreign_key');

        return $this->hasMany(Role::class, $teamForeignKey);
    }
}
