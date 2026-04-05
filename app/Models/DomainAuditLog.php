<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DomainAuditLog extends Model
{
    protected $table = 'domain_audit_log';

    protected $fillable = [
        'stream',
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'environment',
        'metadata',
        'correlation_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        $cid = (string) $companyId;

        return $query->where(function (Builder $q) use ($cid): void {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $q->whereRaw('json_extract(metadata, \'$.company_id\') = ?', [$cid]);
            } else {
                $q->where('metadata->company_id', $cid);
            }

            $q->orWhere(function (Builder $inner) use ($cid): void {
                $inner->where('resource_type', 'companies')
                    ->where('resource_id', $cid);
            });
        });
    }
}
