<?php

namespace App\Http\Controllers\Admin\Relatorios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Relatorios\RentabilidadeLojaRequest;
use App\Services\Relatorios\RentabilidadeLojaService;
use Illuminate\View\View;

final class RentabilidadeLojaController extends Controller
{
    public function __invoke(RentabilidadeLojaRequest $request, RentabilidadeLojaService $service): View
    {
        $user = $request->user();
        $dados = $service->gerar($user, $request->validated());
        $opcoes = $service->opcoesFiltro($user);

        return view('admin.relatorios.rentabilidade-loja.index', [
            'dados' => $dados,
            'unidadesOrigem' => $opcoes['unidades_origem'],
            'clientes' => $opcoes['clientes'],
        ]);
    }
}
