<?php

namespace App\Models;

class HumanVerification extends TenantModel
{
    public $timestamps = false;

    protected $hidden = ['session_token_hash'];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }
}
