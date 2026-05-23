<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEntradasDevolucaoDestino;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoDevolucao;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DevolucaoMovimentacaoService
{
    public function __construct(
        private readonly MovimentacaoVersionamentoService $versionamento,
        private readonly ReprocessaEntradasDevolucaoDestino $replayDevolucao,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    /**
     * @return array{vendas: Collection<int, Movimentacao>, tipos: list<TipoDevolucao>, unidades_retorno: Collection<int, UnidadeNegocio>}
     */
    public function opcoesFormularioDevolucao(): array
    {
        return [
            'vendas' => Movimentacao::query()
                ->with(['vendaNota', 'empresaDestino', 'empresaOrigem', 'fruta'])
                ->vigentesParaCalculo()
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->orderByDesc('data_movimentacao')
                ->limit(200)
                ->get()
                ->filter(function (Movimentacao $venda): bool {
                    $unidadeId = app(UnidadeNegocioAccessService::class)->unidadeIdDaEmpresa((int) $venda->id_empresa_origem);

                    return $unidadeId !== null && app(UnidadeNegocioAccessService::class)->canAccess(auth()->user(), $unidadeId);
                })
                ->values(),
            'tipos' => TipoDevolucao::cases(),
            'unidades_retorno' => UnidadeNegocio::query()->ativas()->permitidasPara(auth()->user())->orderBy('nome')->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function registrarDevolucao(array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($input, $user): Movimentacao {
            $venda = $this->resolverVendaOrigem((int) $input['movimentacao_venda_origem_id']);
            $tipo = TipoDevolucao::from((string) $input['tipo_devolucao']);
            $qtdUm = $this->normalizarQuantidade($input['qtd_fruta_um']);
            $this->validarSaldoDevolvivel($venda, $qtdUm);

            $attrs = $this->atributosCalculados($venda, $tipo, $qtdUm, $input);
            $movimentacao = $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE
                ? $this->criarComRetorno($attrs, $user)
                : $this->criarSemRetorno($attrs, $user);

            return $movimentacao->fresh(['vendaOrigem', 'fruta', 'empresaOrigem', 'empresaDestino']);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function atualizarDevolucao(Movimentacao $movimentacao, array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($movimentacao, $input, $user): Movimentacao {
            $ativa = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();
            $this->assertDevolucaoAtiva($ativa);
            $this->versionamento->validarPodeSubstituir($ativa);

            $venda = $this->resolverVendaOrigem((int) ($input['movimentacao_venda_origem_id'] ?? $ativa->movimentacao_venda_origem_id));
            $tipo = TipoDevolucao::from((string) ($input['tipo_devolucao'] ?? $ativa->tipo_devolucao));
            $qtdUm = $this->normalizarQuantidade($input['qtd_fruta_um']);
            $this->validarSaldoDevolvivel($venda, $qtdUm, $ativa);
            $unidadeAnterior = $ativa->tipo_devolucao === TipoDevolucao::COM_RETORNO_ESTOQUE->value && $ativa->vendaOrigem !== null
                ? $this->unidadeDestinoEstoque($ativa->vendaOrigem, $ativa->id_unidade_negocio_retorno)
                : null;

            $attrs = $this->atributosCalculados($venda, $tipo, $qtdUm, array_merge([
                'numero_nf_devolucao' => $ativa->numero_nf_devolucao,
                'observacao' => $ativa->observacao,
                'motivo_devolucao' => $ativa->motivo_devolucao,
                'id_unidade_negocio_retorno' => $ativa->id_unidade_negocio_retorno,
            ], $input));
            $attrs['devolucao_origem_id'] = $ativa->idCadeiaRaiz();

            $nova = $this->versionamento->criarNovaVersao(
                $ativa,
                $attrs,
                $this->nullableTrim($input['motivo_substituicao'] ?? null),
                $user,
            );

            if ($ativa->tipo_devolucao === TipoDevolucao::COM_RETORNO_ESTOQUE->value || $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE) {
                if ($unidadeAnterior !== null) {
                    $this->replayDevolucao->reprocessarEntradasDevolucaoNaUnidadeDestino(
                        $unidadeAnterior->id,
                        (int) $ativa->id_fruta,
                        $ativa->id,
                    );
                }

                $this->replayDevolucao->reprocessarEntradasDevolucaoNaUnidadeDestino(
                    $this->unidadeDestinoEstoque($venda, $nova->id_unidade_negocio_retorno)->id,
                    (int) $venda->id_fruta,
                    $nova->id,
                );
            }

            return $nova->fresh(['vendaOrigem', 'fruta', 'empresaOrigem', 'empresaDestino']);
        });
    }

    public function unidadeDestinoEstoque(Movimentacao $venda, int|string|null $idUnidadeRetorno = null): UnidadeNegocio
    {
        if ($idUnidadeRetorno !== null && $idUnidadeRetorno !== '') {
            return UnidadeNegocio::query()->findOrFail((int) $idUnidadeRetorno);
        }

        $empresaOrigemVenda = Empresa::query()->with('entidade')->findOrFail((int) $venda->id_empresa_origem);
        $unidadeOrigemVenda = $empresaOrigemVenda->entidade;
        if (! $unidadeOrigemVenda instanceof UnidadeNegocio) {
            throw new InvalidArgumentException('Venda original sem unidade de origem válida.');
        }

        return $unidadeOrigemVenda;
    }

    public function saldoDevolvivelUm(Movimentacao $venda, ?Movimentacao $ignorar = null): float
    {
        return $venda->saldoDevolvivelUm($ignorar);
    }

    private function criarComRetorno(array $attrs, ?User $user): Movimentacao
    {
        $unidadeDestino = UnidadeNegocio::query()->findOrFail((int) $attrs['_id_unidade_destino_estoque']);
        $estoque = $this->obterOuCriarEstoqueComLock($unidadeDestino->id, (int) $attrs['id_fruta']);
        $posicaoAnterior = $this->obterOuCriarPosicaoAtual($estoque, $unidadeDestino->id, (int) $attrs['id_fruta']);

        $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
        $meAntes = $this->auditoria->snapshotMovimentacaoEstoque($posicaoAnterior);

        $saldoKgNovo = round((float) $posicaoAnterior->qtd_fruta_kg + (float) $attrs['qtd_fruta_kg'], 2);
        $saldoUmNovo = round((float) $posicaoAnterior->qtd_fruta_um + (float) $attrs['qtd_fruta_um'], 2);
        $valorNovo = round((float) $estoque->valor_total_acumulado + (float) $attrs['valor_total_movimentacao'], 2);
        $precoKgNovo = $saldoKgNovo > 0 ? round($valorNovo / $saldoKgNovo, 2) : 0.0;
        $kgPorUm = (float) Fruta::query()->findOrFail((int) $attrs['id_fruta'])->kg_por_unidade_medicao;
        $precoUmNovo = round($precoKgNovo * $kgPorUm, 2);

        $posicaoAnterior->forceFill(['status_ultima_posicao' => false])->save();
        unset($attrs['_id_unidade_destino_estoque']);
        /** @var Movimentacao $mov */
        $mov = Movimentacao::query()->create(array_merge($attrs, [
            'id_movimentacao_estoque_old' => $posicaoAnterior->id,
            'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($precoKgNovo, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($precoUmNovo, 2, '.', ''),
        ]));

        $novaMe = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidadeDestino->id,
            'id_fruta' => $mov->id_fruta,
            'movimentacao_id' => $mov->id,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoKgNovo, 2, '.', ''),
            'preco_medio_um' => number_format($precoUmNovo, 2, '.', ''),
            'valor_total_fruta' => number_format($valorNovo, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $mov->forceFill(['id_movimentacao_estoque_new' => $novaMe->id])->saveQuietly();
        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoKgNovo, 2, '.', ''),
            'preco_medio_um' => number_format($precoUmNovo, 2, '.', ''),
            'valor_total_acumulado' => number_format($valorNovo, 2, '.', ''),
        ])->save();

        $mov = $mov->fresh(['vendaOrigem']);
        $this->auditoria->registrarRegistroDevolucao($mov, $user, $estoqueAntes, $this->auditoria->snapshotEstoque($estoque->fresh()), $meAntes, $this->auditoria->snapshotMovimentacaoEstoque($novaMe->fresh()));

        return $mov;
    }

    private function criarSemRetorno(array $attrs, ?User $user): Movimentacao
    {
        $unidadeDestino = UnidadeNegocio::query()->findOrFail((int) $attrs['_id_unidade_destino_estoque']);
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('id_fruta', (int) $attrs['id_fruta'])
            ->first();
        unset($attrs['_id_unidade_destino_estoque']);

        /** @var Movimentacao $mov */
        $mov = Movimentacao::query()->create(array_merge($attrs, [
            'id_movimentacao_estoque_old' => null,
            'id_movimentacao_estoque_new' => null,
            'saldo_estoque_fruta_kg' => (string) ($estoque?->qtd_fruta_kg ?? '0.00'),
            'saldo_estoque_fruta_um' => (string) ($estoque?->qtd_fruta_um ?? '0.00'),
            'preco_medio_fruta_kg' => (string) ($estoque?->preco_medio_kg ?? '0.00'),
            'preco_medio_fruta_um' => (string) ($estoque?->preco_medio_um ?? '0.00'),
        ]));

        $mov = $mov->fresh(['vendaOrigem']);
        $snapshot = $estoque !== null ? $this->auditoria->snapshotEstoque($estoque) : [];
        $this->auditoria->registrarRegistroDevolucao($mov, $user, $snapshot, $snapshot, null, null);

        return $mov;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function atributosCalculados(Movimentacao $venda, TipoDevolucao $tipo, float $qtdUm, array $input): array
    {
        $fruta = Fruta::query()->findOrFail((int) $venda->id_fruta);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            throw new InvalidArgumentException('Fruta com kg por unidade inválido.');
        }

        $qtdKg = round($qtdUm * $kgPorUm, 2);
        $proporcao = $qtdUm / (float) $venda->qtd_fruta_um;
        $valorDevolucao = round((float) $venda->valor_nf_total * $proporcao, 2);
        $custoBase = round((float) $venda->valor_custo_saida * $proporcao, 2);
        if ($tipo === TipoDevolucao::COM_RETORNO_ESTOQUE && (($input['id_unidade_negocio_retorno'] ?? null) === null || $input['id_unidade_negocio_retorno'] === '')) {
            throw new InvalidArgumentException('A unidade física de retorno é obrigatória para devolução com retorno ao estoque.');
        }

        $unidadeDestino = $this->unidadeDestinoEstoque(
            $venda,
            $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE ? ($input['id_unidade_negocio_retorno'] ?? null) : null,
        );
        $co = $this->custoOperacionalRetorno($venda, $tipo, $unidadeDestino);
        $valorCoTotal = round($co['valor'] * $qtdKg, 2);
        $custoDevolucao = round($custoBase + $valorCoTotal, 2);
        $resultado = $tipo === TipoDevolucao::SEM_RETORNO_ESTOQUE
            ? round(-((float) $venda->resultado_movimentacao * $proporcao), 2)
            : round($valorDevolucao - $custoDevolucao, 2);
        $empresaDestinoEstoque = $unidadeDestino->registroCorporativo()->firstOrFail();

        return [
            '_id_unidade_destino_estoque' => $unidadeDestino->id,
            'movimentacao_venda_origem_id' => $venda->id,
            'venda_nota_id' => $venda->venda_nota_id,
            'id_unidade_negocio_faturamento' => $venda->id_unidade_negocio_faturamento,
            'id_unidade_negocio_retorno' => $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE ? $unidadeDestino->id : null,
            'id_empresa_origem' => $venda->id_empresa_destino,
            'id_empresa_destino' => $empresaDestinoEstoque->id,
            'id_fruta' => $venda->id_fruta,
            'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
            'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
            'tipo_devolucao' => $tipo->value,
            'numero_nf_devolucao' => trim((string) $input['numero_nf_devolucao']),
            'motivo_devolucao' => $this->nullableTrim($input['motivo_devolucao'] ?? null),
            'observacao' => $this->nullableTrim($input['observacao'] ?? null),
            'valor_devolucao_total' => number_format($valorDevolucao, 2, '.', ''),
            'valor_devolucao_um' => number_format(round($valorDevolucao / $qtdUm, 2), 2, '.', ''),
            'valor_devolucao_kg' => number_format(round($valorDevolucao / $qtdKg, 2), 2, '.', ''),
            'valor_custo_devolucao' => number_format($custoDevolucao, 2, '.', ''),
            'resultado_devolucao' => number_format($resultado, 2, '.', ''),
            'valor_total_movimentacao' => number_format($custoDevolucao, 2, '.', ''),
            'valor_nf_total' => '0.00',
            'valor_nf_um' => '0.00',
            'valor_nf_kg' => '0.00',
            'valor_custo_saida' => '0.00',
            'resultado_movimentacao' => '0.00',
            'id_frete' => null,
            'valor_frete_rateio' => '0.00',
            'valor_frete_um' => '0.00',
            'valor_frete_kg' => '0.00',
            'id_custo_operacional' => $co['id'],
            'valor_custo_operacional' => number_format($co['valor'], 2, '.', ''),
            'preco_medio_fruta_kg' => number_format($qtdKg > 0 ? round($custoDevolucao / $qtdKg, 2) : 0, 2, '.', ''),
            'preco_medio_fruta_um' => number_format($qtdUm > 0 ? round($custoDevolucao / $qtdUm, 2) : 0, 2, '.', ''),
            'icms_convertido_kg' => '0.00',
            'valor_icms_total' => '0.00',
            'valor_icms_kg' => '0.00',
            'valor_icms_um' => '0.00',
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Devolucao->value,
            'status_movimentacao_id' => $tipo === TipoDevolucao::COM_RETORNO_ESTOQUE ? StatusMovimentacao::ID_ENTRADA : StatusMovimentacao::ID_SAIDA,
            'status_transferencia' => null,
            'transferencia_origem_id' => null,
            'pareada_movimentacao_id' => null,
            'numero_nf_origem' => null,
            'numero_nf_destino' => null,
            'qtd_recebida_um' => null,
            'qtd_recebida_kg' => null,
            'status_recebimento' => null,
            'observacao_recebimento' => null,
            'motivo_doacao' => null,
            'categoria_descarte_id' => null,
            'motivo_descarte' => null,
            'data_movimentacao' => now(),
            'versao' => 1,
            'versao_replay' => 1,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'movimentacao_origem_id' => null,
            'devolucao_origem_id' => null,
            'substituida_por_id' => null,
            'substituida_em' => null,
            'motivo_substituicao' => null,
        ];
    }

    /**
     * @return array{id: int|null, valor: float}
     */
    private function custoOperacionalRetorno(Movimentacao $venda, TipoDevolucao $tipo, UnidadeNegocio $unidadeDestino): array
    {
        if ($tipo !== TipoDevolucao::COM_RETORNO_ESTOQUE) {
            return ['id' => null, 'valor' => 0.0];
        }

        $empresaOrigemVenda = Empresa::query()->with('entidade')->findOrFail((int) $venda->id_empresa_origem);
        $unidadeEstoqueVenda = $venda->id_unidade_negocio_estoque !== null
            ? UnidadeNegocio::query()->findOrFail((int) $venda->id_unidade_negocio_estoque)
            : ($empresaOrigemVenda->entidade instanceof UnidadeNegocio ? $empresaOrigemVenda->entidade : null);

        if (! $unidadeEstoqueVenda instanceof UnidadeNegocio || ! $unidadeEstoqueVenda->is_hub || $unidadeDestino->is_hub) {
            return ['id' => null, 'valor' => 0.0];
        }

        $co = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidadeDestino->id)
            ->where('status_position', true)
            ->first();

        return ['id' => $co?->id, 'valor' => (float) ($co?->custo_operacional ?? $unidadeDestino->custo_operacional ?? 0)];
    }

    private function resolverVendaOrigem(int $id): Movimentacao
    {
        $venda = Movimentacao::query()
            ->with(['fruta', 'vendaNota'])
            ->whereKey($id)
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->lockForUpdate()
            ->first();

        if ($venda === null) {
            throw new InvalidArgumentException('Venda original ativa não encontrada.');
        }

        return $venda;
    }

    private function validarSaldoDevolvivel(Movimentacao $venda, float $qtdUm, ?Movimentacao $ignorar = null): void
    {
        if ($qtdUm <= 0) {
            throw new InvalidArgumentException('Quantidade devolvida deve ser maior que zero.');
        }

        if ($qtdUm > $this->saldoDevolvivelUm($venda, $ignorar) + 1e-6) {
            throw new InvalidArgumentException('Quantidade devolvida não pode ultrapassar o saldo devolvível da venda.');
        }
    }

    private function normalizarQuantidade(mixed $raw): float
    {
        if (is_string($raw) && str_contains($raw, ',')) {
            return round((float) TextoCadastro::normalizarDecimalNaoNegativo($raw), 2);
        }

        return round(max(0, (float) $raw), 2);
    }

    private function obterOuCriarEstoqueComLock(int $idUnidade, int $idFruta): Estoque
    {
        return Estoque::query()->firstOrCreate(
            ['id_unidade_negocio' => $idUnidade, 'id_fruta' => $idFruta],
            [
                'qtd_fruta_kg' => '0.00',
                'qtd_fruta_um' => '0.00',
                'preco_medio_kg' => '0.00',
                'preco_medio_um' => '0.00',
                'valor_total_acumulado' => '0.00',
            ],
        );
    }

    private function obterOuCriarPosicaoAtual(Estoque $estoque, int $idUnidade, int $idFruta): MovimentacaoEstoque
    {
        $posicao = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->first();

        if ($posicao !== null) {
            return $posicao;
        }

        return MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => (string) $estoque->qtd_fruta_kg,
            'qtd_fruta_um' => (string) $estoque->qtd_fruta_um,
            'preco_medio_kg' => (string) $estoque->preco_medio_kg,
            'preco_medio_um' => (string) $estoque->preco_medio_um,
            'valor_total_fruta' => (string) $estoque->valor_total_acumulado,
            'status_ultima_posicao' => true,
        ]);
    }

    private function assertDevolucaoAtiva(Movimentacao $m): void
    {
        if ((int) $m->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Devolucao->value) {
            throw new InvalidArgumentException('Somente movimentações da categoria DEVOLUÇÃO.');
        }
        if ($m->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
            throw new InvalidArgumentException('Somente versões ativas podem ser atualizadas.');
        }
    }

    private function nullableTrim(mixed $raw): ?string
    {
        $value = $raw === null ? null : trim((string) $raw);

        return $value === '' ? null : $value;
    }
}
