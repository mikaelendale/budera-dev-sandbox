<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LedgerEntry extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'wallet_account_id',
        'type',
        'amount_cents',
        'reference_type',
        'reference_id',
        'balance_after_cents',
        'description',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Ledger entries are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Ledger entries are append-only and cannot be deleted.');
        });
    }

    public function walletAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class);
    }
}
