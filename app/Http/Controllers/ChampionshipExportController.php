<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChampionshipExportController extends Controller
{
    public function __invoke(Event $event): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isCoach(), 403);

        $event->load('registrations.athlete.user');
        $columns = ['NAMA LENGKAP', 'Jenis Kelamin', 'Tanggal Lahir', 'Berat Badan', 'Tinggi Badan', 'Sabuk', 'Klasifikasi', 'Divisi', 'Class/Kategori', 'Tim/Kontingen', 'NIK', 'ASAL SEKOLAH', 'No. Hp', 'Email'];

        return response()->streamDownload(function () use ($event, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($event->registrations as $registration) {
                $athlete = $registration->athlete;
                $user = $athlete?->user;

                fputcsv($handle, [
                    $user?->name ?? '',
                    $user?->gender ?? '',
                    $user?->bday ? Carbon::parse($user->bday)->format('Y-m-d') : '',
                    $athlete?->weight_kg ?? '',
                    $athlete?->height_cm ?? '',
                    $athlete?->geup ?? '',
                    $registration->classification ?? '',
                    $registration->division ?? '',
                    $registration->class_name ?: $registration->category,
                    $registration->team_contingent ?: 'rhino fighter',
                    $athlete?->displayValue('nik_ciphertext') ?? '',
                    $athlete?->school_origin ?? '',
                    $user?->phone ?? '',
                    $user?->email ?? '',
                ]);
            }

            fclose($handle);
        }, str($event->e_name)->slug('-').'-championship-export.csv', ['Content-Type' => 'text/csv']);
    }
}
