<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\StatusTransferenciaOperacional;
use App\Enums\TipoEmpresaRegistro;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Frutas\FrutaIcmsCalculoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\EmpresaEntidadeQuery;
use App\Support\Movimentacoes\FrutasComEstoqueOrigem;
use App\Support\TextoCadastro;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras e persistência exclusivas da categoria TRANSFERÊNCIA (saída origem + entrada pendente destino).
 */
final class TransferenciaMovimentacaoService
{
    public function __construct(
        private readonly ReconciliacaoTransferenciaService $reconciliacao,
    ) {}

    /**
     * @param  array{
     *     id_empresa_origem:int,
     *     id_empresa_destino:int,
     *     id_fruta:int,
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     numero_nf_origem?:string|null,
     *     id_frete?:int|null,
     *     observacao?:string|null,
     * }  $input
     * @return array{saida: Movimentacao, entrada: Movimentacao}
     */
    public function criarTransferencia(array $input): array
    {
        return DB::transaction(fn (): array => $this->criarTransferenciaInterno(
            $input,
            null,
            1,
            now(),
            null,
            null,
        ));
    }

    /**
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     empresas_destino: Collection<int, Empresa>,
     *     frutas: Collection<int, Fruta>,
     *     fretes: Collection<int, Frete>,
     * }
     */
    public function opcoesFormularioTransferencia(): array
    {
        $empresasUn = EmpresaEntidadeQuery::unidadesComEstoque()
            ->with('entidade')
            ->get()
            ->filter(fn (Empresa $e): bool => app(UnidadeNegocioAccessService::class)->canAccess(auth()->user(), (int) $e->entidade->id))
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        $fretes = Frete::query()
            ->where('status_situacao', FreteStatusSituacao::ABERTA->value)
            ->orderBy('nome')
            ->get();

        return [
            'empresas_origem' => $empresasUn,
            'empresas_destino' => $empresasUn,
            'frutas' => FrutasComEstoqueOrigem::listar(),
            'fretes' => $fretes,
        ];
    }

    public function obterParAtivoPorTransferenciaOrigemId(int $transferenciaOrigemId): array
    {
        $saida = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('versao')
            ->orderByDesc('id')
            ->firstOrFail();

        $entrada = Movimentacao::query()
            ->whereKey((int) $saida->pareada_movimentacao_id)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->firstOrFail();

        return ['saida' => $saida, 'entrada' => $entrada];
    }

    /**
     * @param  array{
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     numero_nf_origem?:string|null,
     *     id_frete?:int|null,
     *     observacao?:string|null,
     *     motivo_substituicao?:string|null,
     * }  $input
     * @return array{saida: Movimentacao, entrada: Movimentacao}
     */
    public function reenviarAposDivergencia(int $transferenciaOrigemId, array $input): array
    {
        return DB::transaction(function () use ($transferenciaOrigemId, $input): array {
            ['saida' => $saidaAntiga, 'entrada' => $entradaAntiga] = $this->obterParAtivoPorTransferenciaOrigemId($transferenciaOrigemId);

            if ($entradaAntiga->status_transferencia !== StatusTransferenciaOperacional::RECEBIDA_DIVERGENTE->value) {
                throw new InvalidArgumentException('Reenvio permitido somente após recebimento divergente.');
            }

            $this->estornarSaidaNoEstoqueOrigem($saidaAntiga);

            $motivo = isset($input['motivo_substituicao']) ? trim((string) $input['motivo_substituicao']) : null;
            if ($motivo === '') {
                $motivo = null;
            }

            $novaVersao = (int) $saidaAntiga->versao + 1;
            $agora = $saidaAntiga->data_movimentacao;

            $saidaAntiga->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::SUBSTITUIDO->value,
                'status_transferencia' => StatusTransferenciaOperacional::REENVIADA->value,
                'motivo_substituicao' => $motivo,
                'substituida_em' => now(),
            ])->saveQuietly();

            $entradaAntiga->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::SUBSTITUIDO->value,
                'status_transferencia' => StatusTransferenciaOperacional::REENVIADA->value,
                'motivo_substituicao' => $motivo,
                'substituida_em' => now(),
            ])->saveQuietly();

            $payload = [
                'id_empresa_origem' => (int) $saidaAntiga->id_empresa_origem,
                'id_empresa_destino' => (int) $saidaAntiga->id_empresa_destino,
                'id_fruta' => (int) $saidaAntiga->id_fruta,
                'qtd_fruta_um' => $input['qtd_fruta_um'],
                'numero_nf_origem' => $input['numero_nf_origem'] ?? $saidaAntiga->numero_nf_origem,
                'id_frete' => array_key_exists('id_frete', $input) ? $input['id_frete'] : $saidaAntiga->id_frete,
                'observacao' => $input['observacao'] ?? $saidaAntiga->observacao,
            ];

            $criado = $this->criarTransferenciaInterno($payload, $transferenciaOrigemId, $novaVersao, $agora, $saidaAntiga->id, $entradaAntiga->id);

            $saidaNova = $criado['saida'];
            $entradaNova = $criado['entrada'];

            $saidaAntiga->forceFill(['substituida_por_id' => $saidaNova->id])->saveQuietly();
            $entradaAntiga->forceFill(['substituida_por_id' => $entradaNova->id])->saveQuietly();

            $saidaNova->forceFill([
                'movimentacao_origem_id' => $saidaAntiga->id,
            ])->saveQuietly();

            $entradaNova->forceFill([
                'movimentacao_origem_id' => $entradaAntiga->id,
            ])->saveQuietly();

            return ['saida' => $saidaNova->fresh(), 'entrada' => $entradaNova->fresh()];
        });
    }

    public function cancelarTransferencia(int $transferenciaOrigemId, ?string $motivo = null): void
    {
        DB::transaction(function () use ($transferenciaOrigemId, $motivo): void {
            ['saida' => $saida, 'entrada' => $entrada] = $this->obterParAtivoPorTransferenciaOrigemId($transferenciaOrigemId);

            $st = $entrada->status_transferencia;
            if (! in_array($st, [
                StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value,
                StatusTransferenciaOperacional::RECEBIDA_DIVERGENTE->value,
            ], true)) {
                throw new InvalidArgumentException('Cancelamento não permitido para o status atual da transferência.');
            }

            $this->estornarSaidaNoEstoqueOrigem($saida);

            $mot = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;

            $saida->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'status_transferencia' => StatusTransferenciaOperacional::CANCELADA->value,
                'motivo_substituicao' => $mot,
                'substituida_em' => now(),
            ])->saveQuietly();

            $entrada->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::CANCELADO->value,
                'status_transferencia' => StatusTransferenciaOperacional::CANCELADA->value,
                'motivo_substituicao' => $mot,
                'substituida_em' => now(),
            ])->saveQuietly();

            if ($saida->id_frete !== null) {
                $this->reconciliacao->recalcularRateioFreteParaTransferencias((int) $saida->id_frete);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{saida: Movimentacao, entrada: Movimentacao}
     */
    private function criarTransferenciaInterno(
        array $payload,
        ?int $transferenciaOrigemId,
        int $versao,
        Carbon $dataMovimentacao,
        ?int $substituiSaidaId = null,
        ?int $substituiEntradaId = null,
    ): array {
        $categoriaId = CategoriaMovimentacaoTipo::Transferencia->value;

        $empresaOrigem = Empresa::query()->with(['entidade.estado'])->findOrFail((int) $payload['id_empresa_origem']);
        $empresaDestino = Empresa::query()->with(['entidade.estado'])->findOrFail((int) $payload['id_empresa_destino']);
        $this->assertEmpresaTipo($empresaOrigem, TipoEmpresaRegistro::UNIDADE_NEGOCIO);
        $this->assertEmpresaTipo($empresaDestino, TipoEmpresaRegistro::UNIDADE_NEGOCIO);

        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
        $unidadeDestino = $this->unidadeDaEmpresa($empresaDestino);

        if ($unidadeOrigem->id === $unidadeDestino->id) {
            throw new InvalidArgumentException('Origem e destino não podem ser a mesma unidade de negócio.');
        }

        if (! $unidadeOrigem->possui_estoque || ! $unidadeDestino->possui_estoque) {
            throw new InvalidArgumentException('Origem e destino devem controlar estoque.');
        }

        $fruta = Fruta::query()->findOrFail((int) $payload['id_fruta']);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
        }

        $qtdUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($payload['qtd_fruta_um']), 2);
        if ($qtdUm <= 0) {
            throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
        }

        $qtdKg = round($qtdUm * $kgPorUm, 2);

        $frete = $this->resolverFreteOpcional($payload['id_frete'] ?? null);

        $estoqueOrigem = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->lockForUpdate()
            ->first();

        if ($estoqueOrigem === null) {
            throw new InvalidArgumentException(
                'A unidade de origem nunca recebeu este produto; por isso não é possível executar esta transferência.',
            );
        }

        $this->garantirPosicaoInicialSeNecessario($estoqueOrigem, $unidadeOrigem->id, $fruta->id);

        $posicaoOrigem = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->first();

        $saldoUmAnt = $posicaoOrigem ? (float) $posicaoOrigem->qtd_fruta_um : 0.0;
        $saldoKgAnt = $posicaoOrigem ? (float) $posicaoOrigem->qtd_fruta_kg : 0.0;

        $Vprev = (float) $estoqueOrigem->valor_total_acumulado;
        $Qprev = (float) $estoqueOrigem->qtd_fruta_kg;
        $precoMedioOrigemKg = $Qprev > 0 ? round($Vprev / $Qprev, 2) : 0.0;
        $precoMedioOrigemUm = round($precoMedioOrigemKg * $kgPorUm, 2);

        $valorEconomicoTotal = round($precoMedioOrigemKg * $qtdKg, 2);
        $valorNfUm = $qtdUm > 0 ? round($valorEconomicoTotal / $qtdUm, 2) : 0.0;
        $valorNfKg = $qtdKg > 0 ? round($valorEconomicoTotal / $qtdKg, 2) : 0.0;

        $coDestino = $this->obterCustoOperacionalDestino($unidadeDestino->id);

        $valorFreteKg = 0.0;
        $valorFreteRateio = 0.0;
        $valorFreteUm = 0.0;
        if ($frete !== null) {
            $kgOutros = (float) Movimentacao::query()
                ->where('categoria_movimentacao_id', $categoriaId)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->where('id_frete', $frete->id)
                ->sum('qtd_fruta_kg');
            $totalKgFrete = round($kgOutros + $qtdKg, 2);
            if ($totalKgFrete <= 0) {
                throw new InvalidArgumentException('Não foi possível calcular o rateio de frete (total KG).');
            }
            $valorFreteKg = round((float) $frete->valor / $totalKgFrete, 2);
            $valorFreteRateio = round($valorFreteKg * $qtdKg, 2);
            $valorFreteUm = $qtdUm > 0 ? round($valorFreteRateio / $qtdUm, 2) : 0.0;
        }

        $icmsKg = (float) app(FrutaIcmsCalculoService::class)
            ->calcularEntradaPorKg($fruta, $unidadeDestino, null, $unidadeOrigem, $dataMovimentacao);
        $icmsHistoricoEntrada = $this->camposIcmsHistorico($icmsKg, $qtdKg, $qtdUm);
        $valorCoDest = (float) $coDestino->custo_operacional;
        $precoEntradaKg = round($precoMedioOrigemKg + $valorFreteKg + $valorCoDest + $icmsKg, 2);
        $precoEntradaUm = round($precoEntradaKg * $kgPorUm, 2);
        $valorEntradaTotal = round($qtdKg * $precoEntradaKg, 2);

        $estoqueDestino = $this->obterOuCriarEstoqueComLock($unidadeDestino->id, $fruta->id);
        $saldoDestKg = (float) $estoqueDestino->qtd_fruta_kg;
        $saldoDestUm = (float) $estoqueDestino->qtd_fruta_um;

        $saldoUmNovoOrigem = round($saldoUmAnt - $qtdUm, 2);
        $saldoKgNovoOrigem = round($saldoKgAnt - $qtdKg, 2);
        $VnovoOrigem = round($Vprev - ($precoMedioOrigemKg * $qtdKg), 2);
        $precoConsolidadoKgOrigem = $saldoKgNovoOrigem > 0 ? round($VnovoOrigem / $saldoKgNovoOrigem, 2) : 0.0;
        $precoConsolidadoUmOrigem = round($precoConsolidadoKgOrigem * $kgPorUm, 2);
        $valorTotalSnapshotOrigem = round($saldoKgNovoOrigem * $precoConsolidadoKgOrigem, 2);

        $numeroNfOrigem = isset($payload['numero_nf_origem']) ? trim((string) $payload['numero_nf_origem']) : null;
        $observacao = isset($payload['observacao']) ? trim((string) $payload['observacao']) : null;
        if ($numeroNfOrigem === '') {
            $numeroNfOrigem = null;
        }
        if ($observacao === '') {
            $observacao = null;
        }

        /** @var Movimentacao $saida */
        $saida = Movimentacao::query()->create([
            'id_movimentacao_estoque_old' => $posicaoOrigem?->id,
            'id_movimentacao_estoque_new' => null,
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'valor_nf_total' => number_format($valorEconomicoTotal, 2, '.', ''),
            'valor_nf_um' => number_format($valorNfUm, 2, '.', ''),
            'valor_nf_kg' => number_format($valorNfKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
            'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
            'id_frete' => $frete?->id,
            'valor_frete_rateio' => number_format($valorFreteRateio, 2, '.', ''),
            'valor_frete_um' => number_format($valorFreteUm, 2, '.', ''),
            'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
            'id_custo_operacional' => null,
            'valor_custo_operacional' => number_format(0, 2, '.', ''),
            'saldo_estoque_fruta_kg' => number_format($saldoKgNovoOrigem, 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format($saldoUmNovoOrigem, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoMedioOrigemKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoMedioOrigemUm, 2, '.', ''),
            'icms_convertido_kg' => number_format(0, 2, '.', ''),
            'valor_icms_total' => number_format(0, 2, '.', ''),
            'valor_icms_kg' => number_format(0, 2, '.', ''),
            'valor_icms_um' => number_format(0, 2, '.', ''),
            'categoria_movimentacao_id' => $categoriaId,
            'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
            'status_transferencia' => StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value,
            'transferencia_origem_id' => $transferenciaOrigemId,
            'pareada_movimentacao_id' => null,
            'numero_nf_origem' => $numeroNfOrigem,
            'numero_nf_destino' => null,
            'qtd_recebida_um' => null,
            'qtd_recebida_kg' => null,
            'status_recebimento' => null,
            'observacao' => $observacao,
            'observacao_recebimento' => null,
            'movimentacao_origem_id' => $substituiSaidaId,
            'versao' => $versao,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'data_movimentacao' => $dataMovimentacao,
        ]);

        $anchorId = $transferenciaOrigemId ?? $saida->id;
        if ($transferenciaOrigemId === null) {
            $saida->forceFill(['transferencia_origem_id' => $anchorId])->saveQuietly();
        }

        if ($posicaoOrigem !== null) {
            $posicaoOrigem->forceFill(['status_ultima_posicao' => false])->save();
        }

        $novaPosicaoOrigem = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueOrigem->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => $saida->id,
            'qtd_fruta_kg' => number_format($saldoKgNovoOrigem, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovoOrigem, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKgOrigem, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUmOrigem, 2, '.', ''),
            'valor_total_fruta' => number_format($valorTotalSnapshotOrigem, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $saida->forceFill(['id_movimentacao_estoque_new' => $novaPosicaoOrigem->id])->saveQuietly();

        $estoqueOrigem->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovoOrigem, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovoOrigem, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKgOrigem, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUmOrigem, 2, '.', ''),
            'valor_total_acumulado' => number_format($VnovoOrigem, 2, '.', ''),
        ])->save();

        /** @var Movimentacao $entrada */
        $entrada = Movimentacao::query()->create([
            'id_movimentacao_estoque_old' => null,
            'id_movimentacao_estoque_new' => null,
            'id_empresa_origem' => $empresaOrigem->id,
            'id_empresa_destino' => $empresaDestino->id,
            'id_fruta' => $fruta->id,
            'valor_nf_total' => number_format($valorEntradaTotal, 2, '.', ''),
            'valor_nf_um' => number_format($qtdUm > 0 ? round($valorEntradaTotal / $qtdUm, 2) : 0, 2, '.', ''),
            'valor_nf_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
            'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
            'id_frete' => $frete?->id,
            'valor_frete_rateio' => number_format($valorFreteRateio, 2, '.', ''),
            'valor_frete_um' => number_format($valorFreteUm, 2, '.', ''),
            'valor_frete_kg' => number_format($valorFreteKg, 2, '.', ''),
            'id_custo_operacional' => $coDestino->id,
            'valor_custo_operacional' => number_format($valorCoDest, 2, '.', ''),
            'saldo_estoque_fruta_kg' => number_format($saldoDestKg, 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format($saldoDestUm, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoEntradaKg, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoEntradaUm, 2, '.', ''),
            'icms_convertido_kg' => number_format($icmsKg, 2, '.', ''),
            'valor_icms_total' => $icmsHistoricoEntrada['valor_icms_total'],
            'valor_icms_kg' => $icmsHistoricoEntrada['valor_icms_kg'],
            'valor_icms_um' => $icmsHistoricoEntrada['valor_icms_um'],
            'categoria_movimentacao_id' => $categoriaId,
            'status_movimentacao_id' => StatusMovimentacao::ID_ENTRADA,
            'status_transferencia' => StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value,
            'transferencia_origem_id' => $anchorId,
            'pareada_movimentacao_id' => $saida->id,
            'numero_nf_origem' => $numeroNfOrigem,
            'numero_nf_destino' => null,
            'qtd_recebida_um' => null,
            'qtd_recebida_kg' => null,
            'status_recebimento' => null,
            'observacao' => $observacao,
            'observacao_recebimento' => null,
            'movimentacao_origem_id' => $substituiEntradaId,
            'versao' => $versao,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'data_movimentacao' => $dataMovimentacao,
        ]);

        $saida->forceFill(['pareada_movimentacao_id' => $entrada->id])->saveQuietly();

        if ($frete !== null) {
            $this->reconciliacao->recalcularRateioFreteParaTransferencias((int) $frete->id);
        }

        return ['saida' => $saida->fresh(), 'entrada' => $entrada->fresh()];
    }

    public function estornarSaidaNoEstoqueOrigem(Movimentacao $saida): void
    {
        if ((int) $saida->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
            throw new InvalidArgumentException('Somente perna de saída pode ser estornada no estoque de origem.');
        }

        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $saida->id_empresa_origem);
        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
        $fruta = Fruta::query()->findOrFail((int) $saida->id_fruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;

        $qtdUm = (float) $saida->qtd_fruta_um;
        $qtdKg = (float) $saida->qtd_fruta_kg;

        $estoqueOrigem = $this->obterOuCriarEstoqueComLock($unidadeOrigem->id, $fruta->id);

        $posicaoAtual = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $fruta->id)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->firstOrFail();

        $saldoUmAnt = (float) $posicaoAtual->qtd_fruta_um;
        $saldoKgAnt = (float) $posicaoAtual->qtd_fruta_kg;
        $Vprev = (float) $estoqueOrigem->valor_total_acumulado;
        $Qprev = (float) $estoqueOrigem->qtd_fruta_kg;
        $precoMedioKg = $Qprev > 0 ? round($Vprev / $Qprev, 2) : 0.0;

        $saldoUmNovo = round($saldoUmAnt + $qtdUm, 2);
        $saldoKgNovo = round($saldoKgAnt + $qtdKg, 2);
        $Vnovo = round($Vprev + ($precoMedioKg * $qtdKg), 2);
        $precoConsolidadoKg = $saldoKgNovo > 0 ? round($Vnovo / $saldoKgNovo, 2) : 0.0;
        $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);
        $valorTotalSnapshot = round($saldoKgNovo * $precoConsolidadoKg, 2);

        $posicaoAtual->forceFill(['status_ultima_posicao' => false])->save();

        $novaMe = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueOrigem->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $fruta->id,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorTotalSnapshot, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoqueOrigem->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
        ])->save();
    }

    private function unidadeDaEmpresa(Empresa $empresa): UnidadeNegocio
    {
        $entidade = $empresa->entidade;
        if (! $entidade instanceof UnidadeNegocio) {
            throw new InvalidArgumentException('Empresa não referencia uma unidade de negócio.');
        }

        return $entidade;
    }

    private function assertEmpresaTipo(Empresa $empresa, TipoEmpresaRegistro $tipo): void
    {
        if ($empresa->tipoRegistro() !== $tipo) {
            throw new InvalidArgumentException(
                sprintf('Empresa «%d» deve ser do tipo %s.', $empresa->id, $tipo->rotulo()),
            );
        }
    }

    private function resolverFreteOpcional(?int $idFrete): ?Frete
    {
        if ($idFrete === null || $idFrete < 1) {
            return null;
        }

        $frete = Frete::query()->whereKey($idFrete)->lockForUpdate()->firstOrFail();
        if ($frete->status_situacao !== FreteStatusSituacao::ABERTA->value) {
            throw new InvalidArgumentException('Frete deve estar com situação ABERTA.');
        }

        return $frete;
    }

    private function obterCustoOperacionalDestino(int $idUnidadeNegocio): HistoricoCOUnNg
    {
        $co = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $idUnidadeNegocio)
            ->where('status_position', true)
            ->first();
        if ($co === null) {
            throw new InvalidArgumentException('Não existe custo operacional vigente para a unidade de destino.');
        }

        return $co;
    }

    /**
     * @return array{valor_icms_total: string, valor_icms_kg: string, valor_icms_um: string}
     */
    private function camposIcmsHistorico(float $icmsKg, float $qtdKg, float $qtdUm): array
    {
        $total = round($icmsKg * $qtdKg, 2);

        return [
            'valor_icms_total' => number_format($total, 2, '.', ''),
            'valor_icms_kg' => number_format($icmsKg, 2, '.', ''),
            'valor_icms_um' => number_format($qtdUm > 0 ? $total / $qtdUm : 0, 2, '.', ''),
        ];
    }

    private function obterOuCriarEstoqueComLock(int $idUnidade, int $idFruta): Estoque
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque !== null) {
            return $estoque;
        }

        try {
            return Estoque::query()->create([
                'id_unidade_negocio' => $idUnidade,
                'id_fruta' => $idFruta,
                'qtd_fruta_kg' => 0,
                'qtd_fruta_um' => 0,
                'preco_medio_kg' => 0,
                'preco_medio_um' => 0,
                'valor_total_acumulado' => 0,
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque === null) {
            throw new \RuntimeException('Falha concorrente ao criar estoque.');
        }

        return $estoque;
    }

    private function garantirPosicaoInicialSeNecessario(Estoque $estoque, int $idUnidade, int $idFruta): void
    {
        $existe = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->exists();

        if ($existe) {
            return;
        }

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
