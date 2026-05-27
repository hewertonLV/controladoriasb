<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Grava arquivos no disco local (storage/app/private) com permissões legíveis pelo Apache (www-data).
 */
final class PrivateStorage
{
    public static function put(string $path, string $contents): void
    {
        Storage::disk('local')->put($path, $contents);
        self::ensureWebReadable($path);
    }

    public static function ensureWebReadable(string $path): void
    {
        if (! Storage::disk('local')->exists($path)) {
            return;
        }

        $fullPath = Storage::disk('local')->path($path);
        $directory = dirname($fullPath);

        if (is_dir($directory)) {
            @chmod($directory, 0775);
        }

        if (is_file($fullPath)) {
            @chmod($fullPath, 0664);
        }

        if (! function_exists('posix_getuid') || posix_getuid() !== 0) {
            return;
        }

        $account = posix_getpwnam('www-data');
        if ($account === false) {
            return;
        }

        $uid = (int) $account['uid'];
        $gid = (int) $account['gid'];

        if (is_dir($directory)) {
            @chown($directory, $uid);
            @chgrp($directory, $gid);
        }

        if (is_file($fullPath)) {
            @chown($fullPath, $uid);
            @chgrp($fullPath, $gid);
        }
    }
}
