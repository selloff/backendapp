<?php

namespace App\Modules\Selloff\Admin\Services;

use App\LegacyImport\Data\LegacyRouteSlugs;
use App\Modules\Selloff\Admin\Models\RouteSlug;

class RouteSlugService
{
    public function slug(string $routeKey): string
    {
        $slug = RouteSlug::query()->where('route_key', $routeKey)->value('slug');

        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        foreach (LegacyRouteSlugs::rows() as $row) {
            if ($row['route_key'] === $routeKey) {
                return $row['slug'];
            }
        }

        return str_replace('_', '-', $routeKey);
    }
}
