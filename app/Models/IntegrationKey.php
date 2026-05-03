<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationKey extends Model
{
    protected $table = 'integration_keys';

    public $timestamps = false;

    protected $fillable = [
        'key',
        'client_name',
    ];
}
