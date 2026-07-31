<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Group;
use App\Services\AdminDataExportService;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class AdminDataExportController extends Controller
{
    public function __construct(private readonly AdminDataExportService $exports) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        // Fetch dynamic Branch options from database
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('branch_name')
            ->get(['branch_id', 'branch_name'])
            ->map(fn (Branch $b) => [
                'value' => (string) $b->branch_id,
                'label' => $b->branch_name,
            ])
            ->values()
            ->all();

        // Fetch dynamic Group options from database
        $groups = Group::query()
            ->where('is_active', true)
            ->orderBy('group_name')
            ->get(['group_id', 'group_name'])
            ->map(fn (Group $g) => [
                'value' => (string) $g->group_id,
                'label' => $g->group_name,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/AdminDataExportPage', [
            'datasets' => $this->exports->catalog(),
            'branches' => $branches,
            'groups' => $groups,
        ]);
    }

    public function download(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'dataset' => ['required', Rule::in($this->exports->datasetKeys())],
            'fields' => ['required', 'array', 'min:1', 'max:40'],
            'fields.*' => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:80'],
            'role' => ['nullable', Rule::in(['admin', 'coach', 'parent', 'athlete'])],
            'group' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'include_deleted' => ['nullable', 'boolean'],
        ]);

        $filters = [
            'status' => $validated['status'] ?? null,
            'role' => $validated['role'] ?? null,
            'group' => $validated['group'] ?? null,
            'branch' => $validated['branch'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'include_deleted' => (bool) ($validated['include_deleted'] ?? false),
        ];

        $from = $filters['date_from'];
        $to = $filters['date_to'];

        $periodLabel = match (true) {
            blank($from) && blank($to) => 'All Time',
            filled($from) && filled($to) => "{$from} to {$to}",
            filled($from) => "From {$from}",
            default => "Until {$to}",
        };

        $export = $this->exports->makeExport($validated['dataset'], $validated['fields'], $filters);
        $filename = $this->exports->filename($validated['dataset']);

        ActivityLogger::log(
            $request,
            'download',
            'data_export',
            "Downloaded {$validated['dataset']} export excel (Period: {$periodLabel})."
        );

        return Excel::download($export, $filename);
    }
}