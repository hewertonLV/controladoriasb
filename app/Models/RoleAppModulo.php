<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RoleAppModulo extends Model
{
    protected $table = 'role_app_modulos';

    protected $fillable = [
        'role_id',
        'app_modulo',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
