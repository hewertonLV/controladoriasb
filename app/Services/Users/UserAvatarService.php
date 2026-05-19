<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UserAvatarService
{
    /**
     * @return non-empty-string
     */
    public function store(User $user, UploadedFile $file): string
    {
        $this->deleteStoredFile($user);

        $path = $file->store('avatars/'.$user->getKey(), 'public');
        $user->forceFill(['avatar_path' => $path])->save();

        return $path;
    }

    public function delete(User $user): void
    {
        $this->deleteStoredFile($user);
        $user->forceFill(['avatar_path' => null])->save();
    }

    private function deleteStoredFile(User $user): void
    {
        $path = $user->avatar_path;

        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
