<?php

namespace App\Support\Profile;

use App\Models\User;
use App\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProfileMedia
{
    public function storeProfilePictureFromRequest(Request $request, User $user, string $input = 'profile_picture'): ?string
    {
        if (! $request->hasFile($input)) {
            return null;
        }

        if ($user->profile?->profile_picture_path) {
            Storage::disk('public')->delete($user->profile->profile_picture_path);
        }

        $image = Image::decodePath($request->file($input)->getRealPath())
            ->cover(600, 800)
            ->encodeUsingMediaType('image/jpeg', quality: 90);

        $path = 'profiles/'.str()->uuid().'.jpg';
        Storage::disk('public')->put($path, (string) $image);

        return $path;
    }

    public function storeUserFileFromRequest(Request $request, User $user, string $fileType, string $input = 'file'): ?UserFile
    {
        if (! $request->hasFile($input)) {
            return null;
        }

        $file = $request->file($input);
        $disk = UserFile::DISK_PRIVATE;
        $path = $file->store('user-files/'.$user->id, $disk);

        return UserFile::query()->create([
            'user_id' => $user->id,
            'file_type' => $fileType,
            'original_name' => basename($file->getClientOriginalName()),
            'file_path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    public function deleteUserFile(?UserFile $file): void
    {
        if (! $file) {
            return;
        }

        if ($file->file_path) {
            Storage::disk($file->storageDisk())->delete($file->file_path);
        }

        $file->delete();
    }
}
