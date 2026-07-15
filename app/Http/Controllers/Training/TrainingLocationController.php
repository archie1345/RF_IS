<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingLocationController extends Controller
{
    use BuildsTrainingPayloads;

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $locations = Branch::query()
            ->withCount(['groups', 'athletes'])
            ->orderBy('branch_name')
            ->get()
            ->map(fn (Branch $branch) => $this->branchPayload($branch))
            ->values();

        return Inertia::render('AdminLocationsPage', [
            'title' => 'Lokasi Latihan',
            'subtitle' => 'Master data dojang / lokasi latihan RTFCM.',
            'locations' => $locations,
        ]);
    }
}
