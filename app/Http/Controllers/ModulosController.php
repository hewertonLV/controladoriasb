<?php

namespace App\Http\Controllers;

use App\Enums\AppModulo;
use App\Services\Modulos\ModuloHubService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModulosController extends Controller
{
    public function index(Request $request, ModuloHubService $hub): View|RedirectResponse
    {
        $user = $request->user();
        $modulos = $hub->modulosDisponiveis($user);

        if ($modulos->isEmpty()) {
            $request->session()->forget('app_modulo');

            return view('modulos.index', [
                'modulos' => $modulos,
                'semModulos' => true,
            ]);
        }

        $request->session()->forget('app_modulo');

        return view('modulos.index', [
            'modulos' => $modulos,
        ]);
    }

    public function entrar(Request $request, AppModulo $modulo, ModuloHubService $hub): RedirectResponse
    {
        $user = $request->user();

        if (! $hub->podeAcessarModulo($user, $modulo)) {
            abort(403);
        }

        $request->session()->put('app_modulo', $modulo->value);

        return redirect()->to($hub->urlEntrada($user, $modulo));
    }
}
