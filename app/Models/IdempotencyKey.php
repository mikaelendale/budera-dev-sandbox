<?php

namespace App\Models;

use App\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
        'company_id',
        'request_hash',
        'response_status',
        'response_body',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
