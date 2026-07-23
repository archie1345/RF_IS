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
            'rows' => $announcements->map(fn (Announcement $announcement) => [
                'id' => 'ANN-'.$announcement->id,
                'announcement_id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message,
                'target' => $this->targetLabel($announcement->target_role),
                'target_role' => $announcement->target_role,
                'author' => $announcement->creator?->name ?? 'System',
                'status' => $this->announcementStatus($announcement),
                'is_active' => (bool) $announcement->is_active,
                'publish_at_value' => $announcement->publish_at?->format('Y-m-d\TH:i') ?? '',
                'expire_at_value' => $announcement->expire_at?->format('Y-m-d\TH:i') ?? '',
                'published' => $announcement->publish_at?->format('d M Y H:i')
                    ?? ($announcement->created_at?->format('d M Y H:i') ?? '-'),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $validated = $request->validate($this->rules($request));

        Announcement::query()->create([
            'created_by' => $request->user()->id,
            ...$this->payload($validated),
        ]);

        return redirect()->route('announcements.index')->with('status', 'Pengumuman berhasil diterbitkan.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $validated = $request->validate($this->rules($request));
        $announcement->update($this->payload($validated));

        return back()->with('status', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $announcement->delete();

        return back()->with('status', 'Pengumuman berhasil dihapus.');
    }

    private function rules(Request $request): array
    {
        $expireRules = ['nullable', 'date'];
        if ($request->filled('publish_at')) {
            $expireRules[] = 'after_or_equal:publish_at';
        }

        return [
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string'],
            'target_role' => ['required', Rule::in(['ALL', 'ADMIN', 'COACH', 'PARENT', 'ATHLETE'])],
            'publish_at' => ['nullable', 'date'],
            'expire_at' => $expireRules,
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function payload(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'message' => $validated['message'],
            'target_role' => $validated['target_role'],
            'is_active' => $validated['is_active'] ?? true,
            'publish_at' => $validated['publish_at'] ?? null,
            'expire_at' => $validated['expire_at'] ?? null,
        ];
    }

    private function targetLabel(string $targetRole): string
    {
        return match ($targetRole) {
            'ADMIN' => 'Admin',
            'COACH' => 'Pelatih',
            'PARENT' => 'Orang tua',
            'ATHLETE' => 'Atlet',
            default => 'Semua pengguna',
        };
    }

    private function announcementStatus(Announcement $announcement): array
    {
        if (! $announcement->is_active) {
            return $this->badge('Disembunyikan', 'neutral');
        }

        if ($announcement->publish_at && $announcement->publish_at->isFuture()) {
            return $this->badge('Terjadwal', 'info');
        }

        if ($announcement->expire_at && $announcement->expire_at->isPast()) {
            return $this->badge('Kedaluwarsa', 'neutral');
        }

        return $this->badge('Diterbitkan', 'success');
    }
}
