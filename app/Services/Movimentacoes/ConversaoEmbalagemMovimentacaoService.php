<?php

namespace App\Services\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Support\EmpresaEntidadeQuery;
use App\Support\Movimentacoes\FrutasComEstoqueOrigem;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ConversaoEmbalagemMovimentacaoService
{
    public function __construct(
        private readonly MovimentacaoAuditoriaService $auditoria,
        private readonly ReplayLinhaTempoEstoqueService $replay,
    ) {}

    /**
     * @return array{
     *     empresas_origem: Collection<int, Empresa>,
     *     frutas_origem: Collection<int, Fruta>,
     *     frutas_destino: Collection<int, Fruta>,
     * }
     */
    public function opcoesFormulario(): array
    {
        return [
            'empresas_origem' => EmpresaEntidadeQuery::unidadesComEstoque(somenteComEstoqueCadastrado: true)
                ->with('entidade')
                ->get()
                ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
                ->values(),
            'frutas_origem' => FrutasComEstoqueOrigem::listar(),
            'frutas_destino' => Fruta::query()
                ->where('kg_por_unidade_medicao', '>', 0)
                ->orderBy('nome')
                ->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{saida: Movimentacao, entrada: Movimentacao}
     */
    public function registrarConversao(array $input, ?User $user = null): array
    {
        return DB::transaction(function () use ($input, $user): array {
            $empresa = Empresa::query()
                ->whereKey((int) $input['id_empresa_origem'])
                ->where('entidade_type', UnidadeNegocio::class)
                ->with('entidade')
                ->lockForUpdate()
                ->firstOrFail();

            /** @var UnidadeNegocio $unidade */
            $unidade = $empresa->entidade;
            $frutaOrigem = Fruta::query()->findOrFail((int) $input['id_fruta_origem']);
            $frutaDestino = Fruta::query()->findOrFail((int) $input['id_fruta_destino']);

            if ((int) $frutaOrigem->id === (int) $frutaDestino->id) {
                throw new InvalidArgumentException('A fruta destino deve ser diferente da fruta origem.');
            }

            $qtdOrigemUm = $this->decimal($input['qtd_fruta_um']);
            $qtdResultanteUm = $this->decimal($input['qtd_resultante_um']);
            $kgOrigem = (float) $frutaOrigem->kg_por_unidade_medicao;
            $kgDestino = (float) $frutaDestino->kg_por_unidade_medicao;

            if ($qtdOrigemUm <= 0 || $qtdResultanteUm <= 0 || $kgOrigem <= 0 || $kgDestino <= 0) {
                throw new InvalidArgumentException('Quantidades e fator kg/UM devem ser positivos.');
            }

            $qtdOrigemKg = round($qtdOrigemUm * $kgOrigem, 2);
            $qtdResultanteKg = round($qtdResultanteUm * $kgDestino, 2);
            $qtdPerdaUm = round(max($qtdOrigemUm - $qtdResultanteUm, 0), 2);
            $qtdPerdaKg = round($qtdOrigemKg - $qtdResultanteKg, 2);

            if ($qtdPerdaKg < 0) {
                throw new InvalidArgumentException('A quantidade resultante não pode pesar mais que a fruta original.');
            }

            $estoqueOrigem = $this->estoqueComLock($unidade->id, $frutaOrigem->id);
            $posicaoOrigem = $this->posicaoAtualOuFalha($unidade->id, $frutaOrigem->id);

            if ((float) $estoqueOrigem->qtd_fruta_um < $qtdOrigemUm || (float) $estoqueOrigem->qtd_fruta_kg < $qtdOrigemKg) {
                throw new InvalidArgumentException('Saldo insuficiente para converter a embalagem.');
            }

            $estoqueDestino = $this->obterOuCriarEstoqueComLock($unidade->id, $frutaDestino->id);
            $posicaoDestino = $this->obterOuCriarPosicaoAtual($estoqueDestino, $unidade->id, $frutaDestino->id);

            $estoqueOrigemAntes = $this->auditoria->snapshotEstoque($estoqueOrigem);
            $estoqueDestinoAntes = $this->auditoria->snapshotEstoque($estoqueDestino);
            $meOrigemAntes = $this->auditoria->snapshotMovimentacaoEstoque($posicaoOrigem);
            $meDestinoAntes = $this->auditoria->snapshotMovimentacaoEstoque($posicaoDestino);

            $precoMedioKg = (float) $posicaoOrigem->preco_medio_kg;
            $precoMedioUmOrigem = (float) $posicaoOrigem->preco_medio_um;
            $valorSaida = round($qtdOrigemKg * $precoMedioKg, 2);
            $valorEntrada = round($qtdResultanteKg * $precoMedioKg, 2);
            $valorPerda = round($qtdPerdaKg * $precoMedioKg, 2);

            $saldoOrigemUm = round((float) $posicaoOrigem->qtd_fruta_um - $qtdOrigemUm, 2);
            $saldoOrigemKg = round((float) $posicaoOrigem->qtd_fruta_kg - $qtdOrigemKg, 2);
            $valorOrigem = round((float) $posicaoOrigem->valor_total_fruta - $valorSaida, 2);

            $saida = Movimentacao::query()->create([
                'id_movimentacao_estoque_old' => $posicaoOrigem->id,
                'id_empresa_origem' => $empresa->id,
                'id_empresa_destino' => $empresa->id,
                'id_fruta' => $frutaOrigem->id,
                'id_fruta_destino_conversao' => $frutaDestino->id,
                'qtd_fruta_um' => number_format($qtdOrigemUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdOrigemKg, 2, '.', ''),
                'qtd_resultante_um' => number_format($qtdResultanteUm, 2, '.', ''),
                'qtd_resultante_kg' => number_format($qtdResultanteKg, 2, '.', ''),
                'qtd_perda_conversao_um' => number_format($qtdPerdaUm, 2, '.', ''),
                'qtd_perda_conversao_kg' => number_format($qtdPerdaKg, 2, '.', ''),
                'valor_perda_conversao' => number_format($valorPerda, 2, '.', ''),
                'valor_total_movimentacao' => number_format($valorSaida, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format($saldoOrigemKg, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($saldoOrigemUm, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoMedioKg, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoMedioUmOrigem, 2, '.', ''),
                'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::ConversaoEmbalagem->value,
                'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'data_movimentacao' => now(),
                'observacao' => $this->nullableTrim($input['observacao'] ?? null),
            ]);

            $meSaida = $this->salvarMovimentacaoEstoque(
                $saida,
                $estoqueOrigem,
                $unidade->id,
                $frutaOrigem->id,
                $saldoOrigemKg,
                $saldoOrigemUm,
                $precoMedioKg,
                $precoMedioUmOrigem,
                $valorOrigem,
            );
            $saida->forceFill(['id_movimentacao_estoque_new' => $meSaida->id])->saveQuietly();

            $saldoDestinoUm = round((float) $posicaoDestino->qtd_fruta_um + $qtdResultanteUm, 2);
            $saldoDestinoKg = round((float) $posicaoDestino->qtd_fruta_kg + $qtdResultanteKg, 2);
            $valorDestino = round((float) $posicaoDestino->valor_total_fruta + $valorEntrada, 2);
            $precoDestinoKg = $saldoDestinoKg > 0 ? round($valorDestino / $saldoDestinoKg, 2) : 0.0;
            $precoDestinoUm = round($precoDestinoKg * $kgDestino, 2);

            $entrada = Movimentacao::query()->create([
                'id_movimentacao_estoque_old' => $posicaoDestino->id,
                'id_empresa_origem' => $empresa->id,
                'id_empresa_destino' => $empresa->id,
                'id_fruta' => $frutaDestino->id,
                'conversao_origem_id' => $saida->id,
                'qtd_fruta_um' => number_format($qtdResultanteUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdResultanteKg, 2, '.', ''),
                'valor_total_movimentacao' => number_format($valorEntrada, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format($saldoDestinoKg, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($saldoDestinoUm, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoDestinoKg, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoDestinoUm, 2, '.', ''),
                'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::ConversaoEmbalagem->value,
                'status_movimentacao_id' => StatusMovimentacao::ID_ENTRADA,
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'data_movimentacao' => now(),
                'observacao' => $this->nullableTrim($input['observacao'] ?? null),
            ]);

            $meEntrada = $this->salvarMovimentacaoEstoque(
                $entrada,
                $estoqueDestino,
                $unidade->id,
                $frutaDestino->id,
                $saldoDestinoKg,
                $saldoDestinoUm,
                $precoDestinoKg,
                $precoDestinoUm,
                $valorDestino,
            );
            $entrada->forceFill(['id_movimentacao_estoque_new' => $meEntrada->id])->saveQuietly();
            $saida->forceFill(['pareada_movimentacao_id' => $entrada->id])->saveQuietly();
            $entrada->forceFill(['pareada_movimentacao_id' => $saida->id])->saveQuietly();

            $this->auditoria->registrarRegistroConversaoEmbalagem(
                $saida,
                $entrada,
                $user,
                $estoqueOrigemAntes,
                $this->auditoria->snapshotEstoque($estoqueOrigem->fresh()),
                $estoqueDestinoAntes,
                $this->auditoria->snapshotEstoque($estoqueDestino->fresh()),
                $meOrigemAntes,
                $this->auditoria->snapshotMovimentacaoEstoque($meSaida),
                $meDestinoAntes,
                $this->auditoria->snapshotMovimentacaoEstoque($meEntrada),
            );

            return ['saida' => $saida->fresh(), 'entrada' => $entrada->fresh()];
        });
    }

    private function decimal(mixed $value): float
    {
        return (float) TextoCadastro::normalizarDecimalNaoNegativo($value);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function estoqueComLock(int $idUnidade, int $idFruta): Estoque
    {
        return Estoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->firstOrFail();
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

        $this->ignorarPosicaoAtualDoEstoqueRemovidoLogicamente($idUnidade, $idFruta);

        try {
            return Estoque::query()->create([
                'id_unidade_negocio' => $idUnidade,
                'id_fruta' => $idFruta,
                'qtd_fruta_kg' => '0.00',
                'qtd_fruta_um' => '0.00',
                'preco_medio_kg' => '0.00',
                'preco_medio_um' => '0.00',
                'valor_total_acumulado' => '0.00',
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

        if ($estoque !== null) {
            return $estoque;
        }

        $this->ignorarPosicaoAtualDoEstoqueRemovidoLogicamente($idUnidade, $idFruta);

        $estoque = Estoque::query()->create([
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_acumulado' => '0.00',
        ]);

        if ($estoque === null) {
            throw new \RuntimeException('Falha concorrente ao criar estoque de destino da conversão.');
        }

        return $estoque;
    }

    private function ignorarPosicaoAtualDoEstoqueRemovidoLogicamente(int $idUnidade, int $idFruta): void
    {
        $estoque = Estoque::onlyTrashed()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->lockForUpdate()
            ->first();

        if ($estoque === null) {
            return;
        }

        MovimentacaoEstoque::query()
            ->where('id_estoque', $estoque->id)
            ->where('status_ultima_posicao', true)
            ->update(['status_ultima_posicao' => false]);
    }

    private function posicaoAtualOuFalha(int $idUnidade, int $idFruta): MovimentacaoEstoque
    {
        return MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->where('status_ultima_posicao', true)
            ->lockForUpdate()
            ->firstOrFail();
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
            'qtd_fruta_kg' => '0.00',
            'qtd_fruta_um' => '0.00',
            'preco_medio_kg' => '0.00',
            'preco_medio_um' => '0.00',
            'valor_total_fruta' => '0.00',
            'status_ultima_posicao' => true,
        ]);
    }

    private function salvarMovimentacaoEstoque(
        Movimentacao $movimentacao,
        Estoque $estoque,
        int $idUnidade,
        int $idFruta,
        float $saldoKg,
        float $saldoUm,
        float $precoMedioKg,
        float $precoMedioUm,
        float $valorTotal,
    ): MovimentacaoEstoque {
        MovimentacaoEstoque::query()
            ->where('id_unidade_negocio', $idUnidade)
            ->where('id_fruta', $idFruta)
            ->update(['status_ultima_posicao' => false]);

        $me = MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $idUnidade,
            'id_fruta' => $idFruta,
            'movimentacao_id' => $movimentacao->id,
            'qtd_fruta_kg' => number_format($saldoKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUm, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
            'valor_total_fruta' => number_format($valorTotal, 2, '.', ''),
            'status_ultima_posicao' => true,
        ]);

        $estoque->forceFill([
            'qtd_fruta_kg' => number_format($saldoKg, 2, '.', ''),
            'qtd_fruta_um' => number_format($saldoUm, 2, '.', ''),
            'preco_medio_kg' => number_format($precoMedioKg, 2, '.', ''),
            'preco_medio_um' => number_format($precoMedioUm, 2, '.', ''),
            'valor_total_acumulado' => number_format($valorTotal, 2, '.', ''),
        ])->save();

        return $me;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23000'
            || str_contains(strtolower($e->getMessage()), 'unique')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
