<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'requested_by_type',
        'requested_by_id',
        'approval_token',
        'expires_at',
        'status',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requestedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
