<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitKerja extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unit_kerja';

    protected $fillable = [
        'unit_name',
        'description',
        'slug',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public static function isCrudAllowed(): bool
    {
        $center = (bool) config('manage-unit-kerja.center_application', false);
        $appEnv = (string) config('manage-unit-kerja.app_env', app()->environment());

        return $center || in_array(strtolower($appEnv), ['local', 'dev', 'development'], true) || app()->environment('local');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (! static::isCrudAllowed()) {
                throw new \Exception('CRUD tidak diizinkan kecuali pada app center atau environment local.');
            }

            $model->slug = \Illuminate\Support\Str::slug($model->unit_name);
        });

        static::deleting(function ($model) {
            if (! static::isCrudAllowed()) {
                throw new \Exception('CRUD tidak diizinkan kecuali pada app center atau environment local.');
            }
        });

        static::forceDeleting(function ($model) {
            if (! static::isCrudAllowed()) {
                throw new \Exception('CRUD tidak diizinkan kecuali pada app center atau environment local.');
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'user_unit_kerja', 'unit_kerja_id', 'user_id')->withTimestamps();
    }

    public function getUniqueValidationRules(?int $ignoreId = null): array
    {
        return [
            'unit_name' => ['required', 'string', 'max:100', 'unique:unit_kerja,unit_name,' . ($ignoreId ?? 'NULL') . ',id,deleted_at,NULL'],
        ];
    }
}
