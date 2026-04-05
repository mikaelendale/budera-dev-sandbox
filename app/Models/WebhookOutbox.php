<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookOutbox extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'webhook_outbox';

    protected $fillable = [
        'company_id',
        'event',
        'event_id',
        'environment',
        'payload',
        'destination_url',
        'destination_key',
        'attempts',
        'status',
        'last_error',
        'reserved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reserved_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_outbox_id');
    }
}
