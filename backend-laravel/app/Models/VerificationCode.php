<?php

namespace App\Models;

class VerificationCode extends TenantModel
{
    public $timestamps = false;

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
