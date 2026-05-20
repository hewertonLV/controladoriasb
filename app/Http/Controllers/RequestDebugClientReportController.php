<?php

namespace App\Http\Controllers;

use App\Support\RequestDebug\RequestDebugTraceStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RequestDebugClientReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! config('request_debug.enabled') || ! config('request_debug.client_tracking')) {
            return response()->json(null, Response::HTTP_NO_CONTENT);
        }

        $data = $request->validate([
            'trace_id' => ['required', 'uuid'],
            'clicked_at' => ['required', 'string', 'max:50'],
            'clicked_epoch_ms' => ['required', 'numeric'],
            'page_loaded_at' => ['required', 'string', 'max:50'],
            'page_loaded_epoch_ms' => ['required', 'numeric'],
            'path' => ['required', 'string', 'max:500'],
            'href' => ['nullable', 'string', 'max:1000'],
            'navigation_type' => ['nullable', 'string', 'max:50'],
            'durations_ms' => ['required', 'array'],
            'durations_ms.click_to_fetch' => ['required', 'numeric'],
            'durations_ms.fetch_to_response_start' => ['required', 'numeric'],
            'durations_ms.response_start_to_end' => ['required', 'numeric'],
            'durations_ms.response_end_to_dom' => ['required', 'numeric'],
            'durations_ms.dom_to_load' => ['required', 'numeric'],
            'durations_ms.click_to_load' => ['required', 'numeric'],
            'timestamps' => ['nullable', 'array'],
            'slow_resources' => ['nullable', 'array'],
            'slow_resources.*.name' => ['required_with:slow_resources', 'string', 'max:500'],
            'slow_resources.*.duration_ms' => ['required_with:slow_resources', 'numeric'],
            'slow_resources.*.initiator_type' => ['nullable', 'string', 'max:50'],
        ]);

        RequestDebugTraceStore::appendClientAndMerged($data);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
