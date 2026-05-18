<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClientErrorController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:500'],
            'lineno' => ['nullable', 'integer', 'min:0'],
            'colno' => ['nullable', 'integer', 'min:0'],
            'stack' => ['nullable', 'string', 'max:3000'],
            'url' => ['nullable', 'string', 'max:1000'],
        ]);

        Log::warning('Client-side JavaScript error', [
            'message' => $data['message'],
            'source' => $data['source'] ?? null,
            'lineno' => $data['lineno'] ?? null,
            'colno' => $data['colno'] ?? null,
            'stack' => $data['stack'] ?? null,
            'url' => $data['url'] ?? null,
            'user_id' => $request->user()?->getKey(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
