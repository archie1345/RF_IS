<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserFileController extends Controller
{
    public function __invoke(Request $request, UserFile $userFile): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($this->canDownload($viewer, $userFile), 403);

        $disk = $userFile->storageDisk();
        abort_unless($userFile->file_path && Storage::disk($disk)->exists($userFile->file_path), 404);

        $downloadName = basename((string) $userFile->original_name);
        if ($downloadName === '' || $downloadName === '.' || $downloadName === '..') {
            $downloadName = 'document';
        }

        return Storage::disk($disk)->download(
            $userFile->file_path,
            $downloadName,
            ['Content-Type' => $userFile->mime_type ?: 'application/octet-stream'],
        );
    }

    private function canDownload(User $viewer, UserFile $userFile): bool
    {
        if ($viewer->isAdmin() || (int) $viewer->id === (int) $userFile->user_id) {
            return true;
        }

        if (! $viewer->isParent()) {
            return false;
        }

        return $viewer->children()
            ->where('athletes.id', $userFile->user_id)
            ->exists();
    }
}
