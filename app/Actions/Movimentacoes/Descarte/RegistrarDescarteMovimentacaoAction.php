<?php

namespace App\Actions\Movimentacoes\Descarte;

use App\Http\Requests\Admin\Movimentacoes\StoreDescarteMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DescarteMovimentacaoService;
use App\Services\Movimentacoes\MovimentacaoService;

final class RegistrarDescarteMovimentacaoAction
{
    public function __construct(
        private readonly DescarteMovimentacaoService $descarte,
        private readonly MovimentacaoService $movimentacoes,
    ) {}

    public function __invoke(StoreDescarteMovimentacaoRequest $request): Movimentacao
    {
        $dados = $request->validated();
        $this->descarte->assertPodeRegistrar($dados);

        $modelo = Movimentacao::make($dados);
        $modelo->loadMissing('fruta');
        $this->movimentacoes->sincronizarQuantidadeUnidadeMedida($modelo);

        return $modelo;
    }
}
