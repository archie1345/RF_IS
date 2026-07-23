<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    use FormatsPresentationData;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $activeRole = $user?->primaryRole() ?? 'athlete';
        $isAdmin = $activeRole === 'admin';
        $roleTargets = collect([strtoupper($activeRole), 'ALL']);

        $announcements = Announcement::query()
            ->with('creator:id,name')
            ->when(! $isAdmin, fn ($q) => $q
                ->where('is_active', true)
                ->whereIn('target_role', $roleTargets)
                ->where(fn ($inner) => $inner->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
                ->where(fn ($inner) => $inner->whereNull('expire_at')->orWhere('expire_at', '>=', now())))
            ->latest('publish_at')
            ->latest('id')
            ->get();

        return Inertia::render('AnnouncementsPage', [
            'isAdmin' => $isAdmin,
            'rows' => $announcements->map(fn (Announcement $a) => [
                'id' => 'ANN-'.$a->id,
                'title' => $a->title,
                'message' => $a->message,
                'target' => $this->targetLabel($a->target_role),
                'author' => $a->creator?->name ?? 'System',
                'status' => $this->announcementStatus($a),
                'published' => $a->publish_at?->format('d M Y H:i') ?? ($a->created_at?->format('d M Y H:i') ?? '-'),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $expireRules = ['nullable', 'date'];
        if ($request->filled('publish_at')) {
            $expireRules[] = 'after_or_equal:publish_at';
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string'],
            'target_role' => ['required', Rule::in(['ALL', 'ADMIN', 'COACH', 'PARENT', 'ATHLETE'])],
            'publish_at' => ['nullable', 'date'],
            'expire_at' => $expireRules,
        ]);

        Announcement::query()->create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'target_role' => $validated['target_role'],
            'is_active' => true,
            'publish_at' => $validated['publish_at'] ?? null,
            'expire_at' => $validated['expire_at'] ?? null,
        ]);

        return redirect()->route('announcements.index');
    }

    private function targetLabel(string $targetRole): string
    {
        return match ($targetRole) {
            'ADMIN' => 'Admins',
            'COACH' => 'Coaches',
            'PARENT' => 'Parents',
            'ATHLETE' => 'Athletes',
            default => 'Everyone',
        };
    }

    private function announcementStatus(Announcement $announcement): array
    {
        if (! $announcement->is_active) {
            return $this->badge('Hidden', 'neutral');
        }

        if ($announcement->publish_at && $announcement->publish_at->isFuture()) {
            return $this->badge('Scheduled', 'info');
        }

        if ($announcement->expire_at && $announcement->expire_at->isPast()) {
            return $this->badge('Expired', 'neutral');
        }

        return $this->badge('Published', 'success');
    }
}
