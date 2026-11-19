<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ResolvesUuidRouteBindings
{
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $routeKey = $this->getRouteKeyName();

        if (preg_match('/^\d+$/D', (string) $value) !== 1) {
            return $query->where($routeKey, $value);
        }

        return $query->where(function (Builder $binding) use ($routeKey, $value): void {
            $binding
                ->where($routeKey, $value)
                ->orWhere($this->getKeyName(), $value);
        });
    }
}
