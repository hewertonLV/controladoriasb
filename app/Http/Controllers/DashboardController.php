<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardIndexRequest;
use App\Services\Dashboard\DashboardFinanceiroService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardIndexRequest $request, DashboardFinanceiroService $financeiro): View
    {
        $user = $request->user();
        $filtro = $request->unidadeIdsFiltro() ?? $financeiro->unidadeIdsPadrao($user);
        $mes = $request->mesReferencia();
        $dia = $request->diaReferencia();

        return view('dashboard', [
            'financeiro' => $financeiro->forUser($user, $filtro, $mes, $dia),
            'dadosUrl' => route('dashboard.dados'),
            'mesAtual' => $mes ?? now()->format('Y-m'),
            'diaAtual' => $dia ?? now()->format('Y-m-d'),
            'periodoTipoInicial' => $dia !== null ? 'dia' : 'mes',
            'pollIntervalMs' => (int) config('dashboard_financeiro.poll_interval_ms', 45_000),
        ]);
    }

    public function dados(DashboardIndexRequest $request, DashboardFinanceiroService $financeiro): JsonResponse
    {
        $user = $request->user();
        $filtro = $request->unidadeIdsFiltro();

        if ($filtro === null) {
            $filtro = $financeiro->unidadeIdsPadrao($user);
        }

        return response()->json([
            ...$financeiro->forUser($user, $filtro, $request->mesReferencia(), $request->diaReferencia()),
            'proximo_poll_ms' => (int) config('dashboard_financeiro.poll_interval_ms', 45_000),
        ]);
    }
}
