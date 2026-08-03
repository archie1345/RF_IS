<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventAccessService;
use App\Support\ActivityLogger;
use App\Support\CsvCell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChampionshipExportController extends Controller
{
    public function __construct(private readonly EventAccessService $eventAccess) {}

    public function __invoke(Request $request, Event $event): StreamedResponse
    {
        abort_unless($this->eventAccess->canManage($request->user(), $event), 403);

        $event->load('registrations.athlete.user');
        $columns = [
            'NAMA LENGKAP',
            'Jenis Kelamin',
            'Tanggal Lahir',
            'Berat Badan',
            'Tinggi Badan',
            'Sabuk',
            'Klasifikasi',
            'Divisi',
            'Class/Kategori',
            'Tim/Kontingen',
            'NIK',
            'ASAL SEKOLAH',
            'No. Hp',
            'Email',
        ];

        ActivityLogger::log(
            $request,
            'event.roster.exported',
            'event',
            'Exported championship athlete roster',
            $event,
            ['registration_count' => $event->registrations->count()],
        );

        return response()->streamDownload(function () use ($event, $columns): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);

            foreach ($event->registrations as $registration) {
                $athlete = $registration->athlete;
                $user = $athlete?->user;

                fputcsv($handle, CsvCell::row([
                    $user?->name ?? '',
                    $user?->gender ?? '',
                    $user?->bday ? Carbon::parse($user->bday)->format('Y-m-d') : '',
                    $athlete?->weight_kg ?? '',
                    $athlete?->height_cm ?? '',
                    $athlete?->geup ?? '',
                    $registration->classification ?? '',
                    $registration->division ?? '',
                    $registration->class_name ?: $registration->category,
                    $registration->team_contingent ?: 'Rhino Fighter',
                    $athlete?->displayValue('nik') ?? '',
                    $athlete?->school_origin ?? '',
                    $user?->phone ?? '',
                    $user?->email ?? '',
                ]));
            }

            fclose($handle);
        }, str($event->e_name)->slug('-').'-championship-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
