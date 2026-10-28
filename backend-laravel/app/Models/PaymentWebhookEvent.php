<?php

namespace App\Models;

class PaymentWebhookEvent extends TenantModel
{
    public $timestamps = false;

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'suspended_at_receipt' => 'boolean',
            'locked_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
