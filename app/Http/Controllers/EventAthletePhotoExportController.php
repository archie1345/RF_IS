<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\ActivityLogger;
use App\Support\CsvCell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class EventAthletePhotoExportController extends Controller
{
    public function __invoke(Request $request, Event $event): BinaryFileResponse
    {
        $event->load('registrations.athlete.user.profile');

        $directory = storage_path('app/tmp/event-photo-exports');
        File::ensureDirectoryExists($directory, 0775, true);
        $archivePath = $directory.'/event-'.$event->event_id.'-'.Str::uuid().'.zip';
        $archive = new ZipArchive;

        if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the athlete photo archive.');
        }

        $manifestHandle = fopen('php://temp', 'w+');
        fputcsv($manifestHandle, ['No', 'Member Number', 'Athlete', 'Email', 'Photo Status', 'Archive Filename']);

        $added = 0;
        $missing = 0;
        $seenAthletes = [];

        foreach ($event->registrations->sortBy(fn ($registration) => $registration->athlete?->user?->name ?? '') as $registration) {
            $athlete = $registration->athlete;
            $user = $athlete?->user;

            if (! $athlete || ! $user || isset($seenAthletes[$athlete->athlete_id])) {
                continue;
            }

            $seenAthletes[$athlete->athlete_id] = true;
            $path = $user->profile?->profile_picture_path;
            $sequence = count($seenAthletes);
            $memberNumber = $athlete->member_number ?: $athlete->athlete_id;
            $baseName = Str::of($memberNumber.'-'.$user->name)->ascii()->slug('-')->limit(120, '');
            $archiveName = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).'-'.$baseName.'.jpg';
            $status = 'Missing';
            $storedArchiveName = '';

            if ($path && Storage::disk('public')->exists($path)) {
                $contents = Storage::disk('public')->get($path);
                if ($contents !== '') {
                    $archive->addFromString('photos/'.$archiveName, $contents);
                    $added++;
                    $status = 'Included';
                    $storedArchiveName = 'photos/'.$archiveName;
                } else {
                    $missing++;
                    $status = 'Unreadable';
                }
            } else {
                $missing++;
            }

            fputcsv($manifestHandle, CsvCell::row([
                $sequence,
                $memberNumber,
                $user->name,
                $user->email,
                $status,
                $storedArchiveName,
            ]));
        }

        rewind($manifestHandle);
        $manifest = stream_get_contents($manifestHandle) ?: '';
        fclose($manifestHandle);

        $archive->addFromString('manifest.csv', "\xEF\xBB\xBF".$manifest);
        $archive->addFromString(
            'README.txt',
            "EVENT: {$event->e_name}\n"
            .'DATE: '.($event->e_date?->toDateString() ?? '-')."\n"
            ."INCLUDED PHOTOS: {$added}\n"
            ."MISSING OR UNREADABLE: {$missing}\n\n"
            .'Profile pictures uploaded through the system are normalized to a 3:4 ratio (600x800 JPEG).',
        );
        $archive->close();

        ActivityLogger::log(
            $request,
            'event.photos.exported',
            'event',
            'Downloaded registered athlete 3x4 photo archive',
            $event,
            ['included' => $added, 'missing' => $missing],
        );

        $downloadName = Str::of($event->e_name)->ascii()->slug('-').'-foto-atlet-3x4.zip';

        return response()
            ->download($archivePath, $downloadName, [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
            ])
            ->deleteFileAfterSend(true);
    }
}
