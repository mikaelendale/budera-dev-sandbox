<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuthorizationLedgerEntry extends Model
{
    protected $table = 'authorization_ledger';

    protected $fillable = [
        'stream',
        'actor_type',
        'actor_id',
        'authorization_text',
        'authorization_hash',
        'authorization_signature',
        'ip_address',
        'user_agent',
        'account_id',
        'correlation_id',
        'environment',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('authorization_ledger is append-only; updates are not allowed.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('authorization_ledger is append-only; deletes are not allowed.');
        });
    }
}
