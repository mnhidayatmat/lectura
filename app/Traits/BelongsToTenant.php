<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->bound('current_tenant') && $tenant = app('current_tenant')) {
                $query->where($query->getModel()->getTable() . '.tenant_id', $tenant->id);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->tenant_id && app()->bound('current_tenant') && $tenant = app('current_tenant')) {
                $model->tenant_id = $tenant->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
