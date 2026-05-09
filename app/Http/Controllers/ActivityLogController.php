<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\ActivityLog;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    use FormatsMvpData;

    public function index(): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $rows = ActivityLog::query()
            ->with('actor:id,name,email')
            ->latest('id')
            ->paginate(50)
            ->through(fn (ActivityLog $log) => [
                'id' => 'LOG-'.$log->id,
                'time' => $log->created_at?->format('d M Y H:i:s') ?? '-',
                'actor' => $log->actor?->name ?? 'System',
                'email' => $log->actor?->email ?? '-',
                'action' => $log->action,
                'context' => $log->context,
                'description' => $log->description,
                'ip' => $log->ip_address ?? '-',
            ]);

        return Inertia::render('AdminActivityLogsPage', [
            'rows' => $rows->items(),
            'total' => $rows->total(),
        ]);
    }
}

