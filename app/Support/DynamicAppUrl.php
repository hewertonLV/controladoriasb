<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Resolve a URL base da aplicação sem depender de IP fixo no .env.
 *
 * - HTTP: usa o host/porta da requisição (o que o usuário digitou no navegador).
 * - CLI/fila: detecta IPv4 local + APP_PORT quando APP_URL está vazio.
 */
final class DynamicAppUrl
{
    public static function configured(): ?string
    {
        $configured = env('APP_URL');

        if (! is_string($configured)) {
            return null;
        }

        $configured = trim($configured);

        return $configured !== '' ? rtrim($configured, '/') : null;
    }

    public static function fromRequest(Request $request): string
    {
        $root = $request->getSchemeAndHttpHost();

        return $root !== '' ? rtrim($root, '/') : self::fallback();
    }

    public static function fallback(): string
    {
        if ($configured = self::configured()) {
            return $configured;
        }

        $host = self::detectServerIpv4();
        $port = (int) env('APP_PORT', 80);

        if ($port > 0 && ! in_array($port, [80, 443], true)) {
            return "http://{$host}:{$port}";
        }

        return "http://{$host}";
    }

    public static function apply(?Request $request = null): string
    {
        $url = $request instanceof Request
            ? self::fromRequest($request)
            : self::fallback();

        config([
            'app.url' => $url,
            'filesystems.disks.public.url' => $url.'/storage',
        ]);

        URL::forceRootUrl($url);

        return $url;
    }

    public static function detectServerIpv4(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $output = shell_exec('hostname -I 2>/dev/null');

            if (is_string($output)) {
                foreach (preg_split('/\s+/', trim($output)) ?: [] as $ip) {
                    if (self::isUsableIpv4($ip)) {
                        return $ip;
                    }
                }
            }
        }

        if (function_exists('socket_create')) {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

            if ($socket !== false) {
                @socket_connect($socket, '8.8.8.8', 53);
                @socket_getsockname($socket, $address);
                @socket_close($socket);

                if (is_string($address) && self::isUsableIpv4($address)) {
                    return $address;
                }
            }
        }

        return 'localhost';
    }

    private static function isUsableIpv4(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        return true;
    }
}
