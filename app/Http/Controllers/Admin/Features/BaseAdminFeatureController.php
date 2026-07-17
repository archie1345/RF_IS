<?php

namespace App\Http\Controllers\Admin\Features;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

abstract class BaseAdminFeatureController extends Controller
{
    protected function renderFeature(string $title, string $subtitle, array $metrics, array $columns, string $emptyText, string $mode, array $rows = [], array $extra = []): Response
    {
        return Inertia::render('AdminFeaturePage', array_merge([
            'mode' => $mode,
            'title' => $title,
            'subtitle' => $subtitle,
            'metrics' => $metrics,
            'columns' => $columns,
            'rows' => $rows,
            'emptyText' => $emptyText,
            'todaySessions' => [],
            'billingSettings' => null,
        ], $extra));
    }

    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
