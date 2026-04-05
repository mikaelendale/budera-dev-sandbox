<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Auto-generates a prefixed public ID on model creation.
 *
 * Models using this trait must define a `publicIdPrefix()` method
 * and have a `public_id` column in their database table.
 */
trait HasPublicId
{
    abstract public static function publicIdPrefix(): string;

    protected static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->public_id)) {
                $model->public_id = static::publicIdPrefix().Str::random(24);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
