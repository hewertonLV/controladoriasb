<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasDescarteOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoEmpresaRegistro;
use App\Models\CategoriaDescarte;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Support\Movimentacoes\DoacaoValorEconomico;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras e persistência exclusivas da categoria DESCARTE (saída por perda operacional).
 */
final class DescarteMovimentacaoService
{
    public function __construct(
        private readonly MovimentacaoVersionamentoService $versionamento,
        private readonly ReprocessaSaidasDescarteOrigem $replayDescarte,
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    /**
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     frutas: Collection<int, Fruta>,
     *     categorias_descarte: Collection<int, CategoriaDescarte>,
     * }
     */
    public function opcoesFormularioDescarte(): array
    {
        $empresasOrigem = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->whereHas('entidade', static function ($query): void {
                $query->where('possui_estoque', true);
            })
            ->with('entidade')
            ->get()
            ->filter(function (Empresa $e): bool {
                $u = $e->entidade;

                return $u instanceof UnidadeNegocio
                    && Estoque::query()
                        ->where('id_unidade_negocio', $u->id)
                        ->exists();
            })
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        return [
            'empresas_origem' => $empresasOrigem,
            'frutas' => Fruta::query()->where('kg_por_unidade_medicao', '>', 0)->orderBy('nome')->get(),
            'categorias_descarte' => CategoriaDescarte::query()->orderBy('id')->get(),
        ];
    }

    /**
     * @param  array{
     *     id_empresa_origem:int,
     *     id_fruta:int,
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     categoria_descarte_id:int,
     *     motivo_descarte?:string|null,
     *     observacao?:string|null,
     *     data_movimentacao?:string|null,
     * }  $input
     */
    public function registrarDescarte(array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($input, $user): Movimentacao {
            [$empresaOrigem, $unidadeOrigem, $fruta, $qtdUm, $qtdKg] = $this->resolverEntradaBasica($input);

            $categoriaDescarteId = (int) $input['categoria_descarte_id'];
            CategoriaDescarte::query()->findOrFail($categoriaDescarteId);

            $motivoDescarte = $this->nullableTrim($input['motivo_descarte'] ?? null);
            $observacao = $this->nullableTrim($input['observacao'] ?? null);
            $dataMovimentacao = $this->resolverDataMovimentacao($input['data_movimentacao'] ?? null);

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->garantirPosicaoInicialSeNecessario($estoque, $unidadeOrigem->id, $fruta->id);

            $posicaoOrigem = MovimentacaoEstoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->where('status_ultima_posicao', true)
                ->lockForUpdate()
                ->first();

            $saldoUmAnt = $posicaoOrigem ? (float) $posicaoOrigem->qtd_fruta_um : 0.0;
            $saldoKgAnt = $posicaoOrigem ? (float) $posicaoOrigem->qtd_fruta_kg : 0.0;

            if ($saldoUmAnt + 1e-6 < $qtdUm || $saldoKgAnt + 1e-6 < $qtdKg) {
                throw new InvalidArgumentException('Saldo insuficiente na unidade de origem.');
            }

            $precoMedioKg = (float) $estoque->preco_medio_kg;
            $precoMedioUm = (float) $estoque->preco_medio_um;
            $valorTotalMovimentacao = round($precoMedioKg * $qtdKg, 2);

            $saldoUmNovo = round($saldoUmAnt - $qtdUm, 2);
            $saldoKgNovo = round($saldoKgAnt - $qtdKg, 2);
            $valorAcumuladoNovo = round((float) $estoque->valor_total_acumulado - $valorTotalMovimentacao, 2);
            $valorMeNovo = round((float) ($posicaoOrigem?->valor_total_fruta ?? $estoque->valor_total_acumulado) - $valorTotalMovimentacao, 2);

            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
            $meAntes = $posicaoOrigem !== null ? $this->auditoria->snapshotMovimentacaoEstoque($posicaoOrigem) : null;

            if ($posicaoOrigem !== null) {
                $posicaoOrigem->forceFill(['status_ultima_posicao' => false])->save();
            }

            /** @var Movimentacao $movimentacao */
            $movimentacao = Movimentacao::query()->create($this->atributosDescarte([
                'id_movimentacao_estoque_old' => $posicaoOrigem?->id,
                'id_empresa_origem' => $empresaOrigem->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => $qtdUm,
                'qtd_fruta_kg' => $qtdKg,
                'saldo_estoque_fruta_kg' => $saldoKgNovo,
                'saldo_estoque_fruta_um' => $saldoUmNovo,
                'preco_medio_fruta_kg' => $precoMedioKg,
                'preco_medio_fruta_um' => $precoMedioUm,
                'valor_total_movimentacao' => $valorTotalMovimentacao,
                'categoria_descarte_id' => $categoriaDescarteId,
                'motivo_descarte' => $motivoDescarte,
                'observacao' => $observacao,
                'data_movimentacao' => $dataMovimentacao,
                'versao' => 1,
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            ]));

            $novaPosicaoOrigem = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $unidadeOrigem->id,
                'id_fruta' => $fruta->id,
                'movimentacao_id' => $movimentacao->id,
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
                'valor_total_fruta' => number_format($valorMeNovo, 2, '.', ''),
                'status_ultima_posicao' => true,
            ]);

            $movimentacao->forceFill(['id_movimentacao_estoque_new' => $novaPosicaoOrigem->id])->saveQuietly();

            $estoque->forceFill([
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
                'valor_total_acumulado' => number_format($valorAcumuladoNovo, 2, '.', ''),
            ])->save();

            $this->auditoria->registrarRegistroDescarte(
                $movimentacao->fresh(),
                $user,
                $estoqueAntes,
                $this->auditoria->snapshotEstoque($estoque->fresh()),
                $meAntes,
                $this->auditoria->snapshotMovimentacaoEstoque($novaPosicaoOrigem->fresh()),
            );

            return $movimentacao->fresh(['fruta', 'empresaOrigem', 'categoriaDescarte']);
        });
    }

    /**
     * @param  array{
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     categoria_descarte_id:int,
     *     motivo_descarte?:string|null,
     *     observacao?:string|null,
     *     motivo_substituicao?:string|null,
     * }  $input
     */
    public function atualizarDescarte(Movimentacao $movimentacao, array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($movimentacao, $input, $user): Movimentacao {
            $motivo = $this->nullableTrim($input['motivo_substituicao'] ?? null);
            unset($input['motivo_substituicao']);

            $ativa = Movimentacao::query()->whereKey($movimentacao->id)->lockForUpdate()->firstOrFail();
            $this->assertDescarteSaidaAtiva($ativa);
            $this->versionamento->validarPodeSubstituir($ativa);

            $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $ativa->id_empresa_origem);
            $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
            $fruta = Fruta::query()->findOrFail((int) $ativa->id_fruta);

            $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
            if ($kgPorUm <= 0) {
                throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
            }

            $qtdUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($input['qtd_fruta_um']), 2);
            if ($qtdUm <= 0) {
                throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
            }
            $qtdKg = round($qtdUm * $kgPorUm, 2);

            $categoriaDescarteId = (int) $input['categoria_descarte_id'];
            CategoriaDescarte::query()->findOrFail($categoriaDescarteId);

            $estoque = Estoque::query()
                ->where('id_unidade_negocio', $unidadeOrigem->id)
                ->where('id_fruta', $fruta->id)
                ->lockForUpdate()
                ->firstOrFail();

            $disponivelUm = round((float) $estoque->qtd_fruta_um + (float) $ativa->qtd_fruta_um, 2);
            $disponivelKg = round((float) $estoque->qtd_fruta_kg + (float) $ativa->qtd_fruta_kg, 2);
            if ($disponivelUm + 1e-6 < $qtdUm || $disponivelKg + 1e-6 < $qtdKg) {
                throw new InvalidArgumentException('Saldo insuficiente na unidade de origem para a nova quantidade.');
            }

            $precoMedioKg = (float) $estoque->preco_medio_kg;
            $precoMedioUm = (float) $estoque->preco_medio_um;
            $valorTotalMovimentacao = round($precoMedioKg * $qtdKg, 2);
            $saldoKgNovo = round((float) $estoque->qtd_fruta_kg + (float) $ativa->qtd_fruta_kg - $qtdKg, 2);
            $saldoUmNovo = round((float) $estoque->qtd_fruta_um + (float) $ativa->qtd_fruta_um - $qtdUm, 2);

            $nova = $this->versionamento->criarNovaVersao($ativa, $this->atributosDescarte([
                'id_movimentacao_estoque_old' => $ativa->id_movimentacao_estoque_old,
                'id_empresa_origem' => $ativa->id_empresa_origem,
                'id_fruta' => $ativa->id_fruta,
                'qtd_fruta_um' => $qtdUm,
                'qtd_fruta_kg' => $qtdKg,
                'saldo_estoque_fruta_kg' => $saldoKgNovo,
                'saldo_estoque_fruta_um' => $saldoUmNovo,
                'preco_medio_fruta_kg' => $precoMedioKg,
                'preco_medio_fruta_um' => $precoMedioUm,
                'valor_total_movimentacao' => $valorTotalMovimentacao,
                'categoria_descarte_id' => $categoriaDescarteId,
                'motivo_descarte' => $this->nullableTrim($input['motivo_descarte'] ?? null),
                'observacao' => $this->nullableTrim($input['observacao'] ?? null),
                'cancelada_por' => null,
                'cancelada_em' => null,
                'motivo_cancelamento' => null,
            ]), $motivo, $user);

            $this->replayDescarte->reprocessarSaidasDescarteNaUnidadeOrigem($unidadeOrigem->id, $fruta->id);

            return $nova->fresh(['fruta', 'empresaOrigem', 'categoriaDescarte']);
        });
    }

    public function estornarDescarteNoEstoqueOrigem(Movimentacao $descarte): void
    {
        $this->assertDescarteSaida($descarte);

        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $descarte->id_empresa_origem);
        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
        $frutaId = (int) $descarte->id_fruta;

        $estoqueOrigem = Estoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $frutaId)
            ->lockForUpdate()
            ->firstOrFail();

        $posicaoAtual = MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $unidadeOrigem->id)
            ->where('id_fruta', $frutaId)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->firstOrFail();

        $valorDevolvido = DoacaoValorEconomico::valorTotalMovimentacao($descarte);
        $saldoUmNovo = round((float) $posicaoAtual->qtd_fruta_um + (float) $descarte->qtd_fruta_um, 2);
        $saldoKgNovo = round((float) $posicaoAtual->qtd_fruta_kg + (float) $descarte->qtd_fruta_kg, 2);
        $valorAcumuladoNovo = round((float) $estoqueOrigem->valor_total_acumulado + $valorDevolvido, 2);
        $valorMeNovo = round((float) $posicaoAtual->valor_total_fruta + $valorDevolvido, 2);
        $precoKg = (float) $descarte->preco_medio_fruta_kg;
        $precoUm = (float) $descarte->preco_medio_fruta_um;

        $posicaoAtual->forceFill(['status_ultima_posicao' => false])->save();

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoqueOrigem->id,
            'id_unidade_negocio' => $unidadeOrigem->id,
            'id_fruta' => $frutaId,
            'movimentacao_id' => null,
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorMeNovo, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoqueOrigem->forceFill([
            'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
            'preco_medio_kg' => number_format($precoKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($valorAcumuladoNovo, 2, '.', ''),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function atributosDescarte(array $attrs): array
    {
        return array_merge([
            'id_movimentacao_estoque_new' => null,
            'id_empresa_destino' => null,
            'valor_nf_total' => '0.00',
            'valor_nf_um' => '0.00',
            'valor_nf_kg' => '0.00',
            'valor_icms_total' => '0.00',
            'valor_icms_kg' => '0.00',
            'valor_icms_um' => '0.00',
            'id_frete' => null,
            'valor_frete_rateio' => '0.00',
            'valor_frete_um' => '0.00',
            'valor_frete_kg' => '0.00',
            'id_custo_operacional' => null,
            'valor_custo_operacional' => '0.00',
            'icms_convertido_kg' => '0.00',
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Descarte->value,
            'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
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
            'substituida_por_id' => null,
            'substituida_em' => null,
            'motivo_substituicao' => null,
            'versao_replay' => 1,
        ], [
            'id_movimentacao_estoque_old' => $attrs['id_movimentacao_estoque_old'] ?? null,
            'id_empresa_origem' => $attrs['id_empresa_origem'],
            'id_fruta' => $attrs['id_fruta'],
            'qtd_fruta_um' => number_format((float) $attrs['qtd_fruta_um'], 2, '.', ''),
            'qtd_fruta_kg' => number_format((float) $attrs['qtd_fruta_kg'], 2, '.', ''),
            'saldo_estoque_fruta_kg' => number_format((float) $attrs['saldo_estoque_fruta_kg'], 2, '.', ''),
            'saldo_estoque_fruta_um' => number_format((float) $attrs['saldo_estoque_fruta_um'], 2, '.', ''),
            'preco_medio_fruta_kg' => number_format((float) $attrs['preco_medio_fruta_kg'], 2, '.', ''),
            'preco_medio_fruta_um' => number_format((float) $attrs['preco_medio_fruta_um'], 2, '.', ''),
            'valor_total_movimentacao' => number_format((float) $attrs['valor_total_movimentacao'], 2, '.', ''),
            'categoria_descarte_id' => $attrs['categoria_descarte_id'],
            'motivo_descarte' => $attrs['motivo_descarte'] ?? null,
            'observacao' => $attrs['observacao'] ?? null,
        ], $attrs);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{Empresa, UnidadeNegocio, Fruta, float, float}
     */
    private function resolverEntradaBasica(array $input): array
    {
        $empresaOrigem = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_origem']);
        $this->assertEmpresaTipo($empresaOrigem, TipoEmpresaRegistro::UNIDADE_NEGOCIO);
        $unidadeOrigem = $this->unidadeDaEmpresa($empresaOrigem);
        if (! $unidadeOrigem->possui_estoque) {
            throw new InvalidArgumentException('A unidade de origem deve controlar estoque.');
        }

        $fruta = Fruta::query()->findOrFail((int) $input['id_fruta']);
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        if ($kgPorUm <= 0) {
            throw new InvalidArgumentException('A fruta precisa ter kg por unidade de medição maior que zero.');
        }

        $qtdUm = round((float) TextoCadastro::normalizarDecimalNaoNegativo($input['qtd_fruta_um']), 2);
        if ($qtdUm <= 0) {
            throw new InvalidArgumentException('A quantidade deve ser maior que zero.');
        }

        $qtdKg = round($qtdUm * $kgPorUm, 2);
        if ($qtdKg <= 0) {
            throw new InvalidArgumentException('Quantidade em KG calculada inválida.');
        }

        return [$empresaOrigem, $unidadeOrigem, $fruta, $qtdUm, $qtdKg];
    }

    private function assertDescarteSaidaAtiva(Movimentacao $m): void
    {
        $this->assertDescarteSaida($m);

        if ($m->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
            throw new InvalidArgumentException('Somente versões ativas podem ser atualizadas.');
        }
    }

    private function assertDescarteSaida(Movimentacao $m): void
    {
        if ((int) $m->categoria_movimentacao_id !== CategoriaMovimentacaoTipo::Descarte->value) {
            throw new InvalidArgumentException('Somente movimentações da categoria DESCARTE.');
        }
        if ((int) $m->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
            throw new InvalidArgumentException('Somente saídas de descarte podem ser alteradas por este fluxo.');
        }
    }

    private function unidadeDaEmpresa(Empresa $empresa): UnidadeNegocio
    {
        $entidade = $empresa->entidade;
        if (! $entidade instanceof UnidadeNegocio) {
            throw new InvalidArgumentException('Empresa origem não referencia uma unidade de negócio.');
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

    private function resolverDataMovimentacao(mixed $raw): Carbon
    {
        if ($raw === null || $raw === '') {
            return now();
        }

        return Carbon::parse((string) $raw);
    }

    private function nullableTrim(mixed $raw): ?string
    {
        $value = $raw === null ? null : trim((string) $raw);

        return $value === '' ? null : $value;
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
}
