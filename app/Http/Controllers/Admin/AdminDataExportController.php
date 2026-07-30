<?php

namespace App\Http\Controllers\Admin;

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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'include_deleted' => ['nullable', 'boolean'],
        ]);

        $filters = [
            'status' => $validated['status'] ?? null,
            'role' => $validated['role'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'include_deleted' => $validated['include_deleted'] ?? false,
        ];
        $export = $this->exports->makeExport($validated['dataset'], $validated['fields'], $filters);
        $filename = $this->exports->filename($validated['dataset']);

        ActivityLogger::log(
            $request,
            'admin.data_export.downloaded',
            'admin',
            'Exported selected system data to Excel',
            null,
            [
                'dataset' => $validated['dataset'],
                'fields' => $validated['fields'],
                'filters' => array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== false),
            ],
        );

        return Excel::download($export, $filename);
    }
}
