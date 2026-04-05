<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StateTransition extends Model
{
    protected $table = 'state_transitions';

    protected $fillable = [
        'model_type',
        'model_id',
        'from_state',
        'to_state',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
