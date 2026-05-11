<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUnitKerja extends Model
{
    use HasFactory;

    protected $table = 'user_unit_kerja';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'unit_kerja_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this relation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the unit kerja associated with this relation.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }
}
