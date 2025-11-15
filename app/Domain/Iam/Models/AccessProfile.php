<?php

namespace App\Domain\Iam\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_system',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Default attribute values.
     */
    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    /**
     * Scope: hanya profile yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Relation many-to-many with ApplicationRole.
     */
    public function roles()
    {
        return $this->belongsToMany(ApplicationRole::class,
            'access_profile_role_iam_map',
            'access_profile_id',
            'role_id')
            ->withTimestamps();
    }

    /**
     * Relation many-to-many with User.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_access_profiles')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

}
