<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MultiSheetExport;
use App\Http\Controllers\Controller;
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

        return Inertia::render('admin/AdminDataExportPage', [
            'datasets' => $this->exports->catalog(),
            'branches' => \App\Models\Branch::query()
                ->orderBy('branch_name')
                ->get(['branch_id', 'branch_name'])
                ->map(fn ($b) => ['value' => (string) $b->branch_id, 'label' => $b->branch_name]),
            'groups' => \App\Models\Group::query()
                ->orderBy('group_name')
                ->get(['group_id', 'group_name'])
                ->map(fn ($g) => ['value' => (string) $g->group_id, 'label' => $g->group_name]),
        ]);
    }

    public function download(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'datasets' => ['required', 'array', 'min:1'],
            'datasets.*' => ['required', 'string', Rule::in($this->exports->datasetKeys())],
            'fields' => ['required', 'array'],
            'fields.*' => ['required', 'array', 'min:1', 'max:40'],
            'fields.*.*' => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'array'],
            'status.*' => ['required', 'string', 'max:80'],
            'role' => ['nullable', 'array'],
            'role.*' => ['required', 'string', Rule::in(['admin', 'coach', 'parent', 'athlete'])],
            'branch' => ['nullable', 'array'],
            'branch.*' => ['required', 'integer'],
            'group' => ['nullable', 'array'],
            'group.*' => ['required', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'include_deleted' => ['nullable', 'boolean'],
            'single_sheet' => ['nullable', 'boolean'],
        ]);

        $filters = [
            'status' => $validated['status'] ?? null,
            'role' => $validated['role'] ?? null,
            'branch' => $validated['branch'] ?? null,
            'group' => $validated['group'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'include_deleted' => $validated['include_deleted'] ?? false,
        ];

        $isSingleSheet = $validated['single_sheet'] ?? false;

        if ($isSingleSheet) {
            $export = $this->exports->makeCombinedExport($validated['datasets'], $validated['fields'], $filters);
        } else {
            $sheets = [];
            foreach ($validated['datasets'] as $datasetKey) {
                $datasetFields = $validated['fields'][$datasetKey] ?? [];
                if (empty($datasetFields)) {
                    continue;
                }
                
                $sheets[] = $this->exports->makeExport($datasetKey, $datasetFields, $filters);
            }

            $export = new MultiSheetExport($sheets);
        }
        $filename = 'System_Data_Export_' . now()->format('Y-m-d_Hi') . '.xlsx';

        ActivityLogger::log(
            $request,
            'admin.data_export.downloaded',
            'admin',
            'Exported selected system data to Excel',
            null,
            [
                'datasets' => $validated['datasets'],
                'filters' => array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== false),
            ],
        );

        return Excel::download($export, $filename);
    }
}
