<?php

namespace App\Actions\Movimentacoes\Venda;

use App\Http\Requests\Admin\Movimentacoes\StoreVendaMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\MovimentacaoService;
use App\Services\Movimentacoes\VendaMovimentacaoService;

final class RegistrarVendaMovimentacaoAction
{
    public function __construct(
        private readonly VendaMovimentacaoService $venda,
        private readonly MovimentacaoService $movimentacoes,
    ) {}

    public function __invoke(StoreVendaMovimentacaoRequest $request): Movimentacao
    {
        $dados = $request->validated();
        $this->venda->assertPodeRegistrar($dados);

        $modelo = Movimentacao::make($dados);
        $modelo->loadMissing('fruta');
        $this->movimentacoes->sincronizarQuantidadeUnidadeMedida($modelo);

        return $modelo;
    }
}
