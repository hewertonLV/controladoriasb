<?php

namespace App\Http\Controllers;

use App\Http\Requests\OlhoDeDeusPollRequest;
use App\Services\Dashboard\OlhoDeDeusAlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OlhoDeDeusController extends Controller
{
    public function index(): View
    {
        return view('dashboard.olho-de-fabio', [
            'pollIntervalMs' => (int) config('olho_de_fabio.poll_interval_ms', 45_000),
            'pollUrl' => route('olho-de-fabio.poll'),
            'mesAtual' => now()->format('Y-m'),
        ]);
    }

    public function poll(OlhoDeDeusPollRequest $request, OlhoDeDeusAlertaService $alertas): JsonResponse
    {
        return response()->json($alertas->poll(
            $request->user(),
            $request->mesReferencia(),
            $request->sinceCursor(),
            $request->cargaInicial(),
        ));
    }
}
