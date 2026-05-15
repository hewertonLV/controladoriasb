<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\StoreTransferenciaMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\MovimentacaoService;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;

final class RegistrarTransferenciaMovimentacaoAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencia,
        private readonly MovimentacaoService $movimentacoes,
    ) {}

    public function __invoke(StoreTransferenciaMovimentacaoRequest $request): Movimentacao
    {
        $dados = $request->validated();
        $this->transferencia->assertPodeRegistrar($dados);

        $modelo = Movimentacao::make($dados);
        $modelo->loadMissing('fruta');
        $this->movimentacoes->sincronizarQuantidadeUnidadeMedida($modelo);

        return $modelo;
    }
}
