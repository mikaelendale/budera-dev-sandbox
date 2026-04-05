<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_outbox_id',
        'webhook_endpoint_id',
        'event',
        'event_id',
        'payload',
        'status',
        'attempts',
        'last_attempted_at',
        'response_status',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempted_at' => 'datetime',
        ];
    }

    public function webhookEndpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class);
    }

    public function webhookOutbox(): BelongsTo
    {
        return $this->belongsTo(WebhookOutbox::class);
    }

    public function isEligibleForDispatch(): bool
    {
        return $this->status === 'queued' && (int) $this->attempts < 5;
    }
}
