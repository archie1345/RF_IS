<?php

namespace App\Http\Controllers\Admin\Features;

use App\Models\Athlete;
use App\Models\Coach;
use Illuminate\Http\Request;
use Inertia\Response;

class AdminPeopleFeatureController extends BaseAdminFeatureController
{
    public function members(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $athletes = Athlete::query()->with(['user', 'branch', 'group'])->latest('created_at')->take(100)->get();

        return $this->renderFeature('Manajemen Anggota', 'Total '.$athletes->count().' anggota terdaftar', [], ['No.', 'Anggota', 'Sabuk', 'Kontak', 'Status', 'Aksi'], 'Tidak ada data anggota', 'members', $athletes->values()->map(fn (Athlete $athlete, int $index) => [
            'No.' => (string) ($index + 1),
            'Anggota' => ($athlete->user?->name ?? '-').'\n'.$athlete->athlete_id,
            'Sabuk' => $athlete->geup ?? '-',
            'Kontak' => $athlete->user?->phone_number ?? $athlete->user?->phone ?? '-',
            'Status' => $athlete->user?->status ?? 'Active',
            'Aksi' => 'Profil',
        ])->all());
    }

    public function instructors(Request $request): Response
    {
        $this->authorizeAdmin($request);
        $coaches = Coach::query()->with('user')->latest('created_at')->take(100)->get();

        return $this->renderFeature('Master Pelatih', 'Total '.$coaches->count().' pelatih terdaftar', [], ['No.', 'Pelatih', 'Spesialisasi', 'Sabuk', 'Kontak', 'Status'], 'Data tidak ditemukan', 'instructors', $coaches->values()->map(fn (Coach $coach, int $index) => [
            'No.' => (string) ($index + 1),
            'Pelatih' => ($coach->user?->name ?? '-').'\n'.$coach->coach_id,
            'Spesialisasi' => $coach->specialization ?? '-',
            'Sabuk' => $coach->belt ?? '-',
            'Kontak' => $coach->user?->email ?? '-',
            'Status' => $coach->status ?? 'Active',
        ])->all());
    }
}
