<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class RequestDebugLogger
{
    public static function append(array $payload): void
    {
        $path = config('request_debug.path');

        File::ensureDirectoryExists(dirname($path));

        $line = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )."\n";

        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
