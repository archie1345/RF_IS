<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    use FormatsPresentationData;

    public function index(Request $request): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:80'],
            'context' => ['nullable', 'string', 'max:80'],
            'actor_email' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $query = ActivityLog::query()
            ->with('actor:id,name,email')
            ->when(! empty($validated['q']), function ($builder) use ($validated) {
                $term = trim((string) $validated['q']);
                $builder->where(function ($inner) use ($term) {
                    $inner->where('action', 'like', "%{$term}%")
                        ->orWhere('context', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('ip_address', 'like', "%{$term}%")
                        ->orWhereHas('actor', fn ($actorQuery) => $actorQuery
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->when(! empty($validated['action']), fn ($builder) => $builder->where('action', $validated['action']))
            ->when(! empty($validated['context']), fn ($builder) => $builder->where('context', $validated['context']))
            ->when(! empty($validated['actor_email']), fn ($builder) => $builder->whereHas('actor', fn ($actorQuery) => $actorQuery->where('email', 'like', '%'.$validated['actor_email'].'%')))
            ->latest('id');

        $rows = $query
            ->paginate($perPage)
            ->withQueryString()
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
            'currentPage' => $rows->currentPage(),
            'lastPage' => $rows->lastPage(),
            'perPage' => $rows->perPage(),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')->values(),
            'contexts' => ActivityLog::query()->select('context')->distinct()->orderBy('context')->pluck('context')->values(),
            'filters' => [
                'q' => (string) ($validated['q'] ?? ''),
                'action' => (string) ($validated['action'] ?? ''),
                'context' => (string) ($validated['context'] ?? ''),
                'actor_email' => (string) ($validated['actor_email'] ?? ''),
                'per_page' => (string) $perPage,
            ],
        ]);
    }
}

