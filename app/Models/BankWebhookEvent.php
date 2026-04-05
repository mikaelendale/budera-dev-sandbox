<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankWebhookEvent extends Model
{
    protected $fillable = [
        'event',
        'payload',
        'transfer_id',
        'mock_kyc_submission_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
