<?php

namespace App\Services\Movimentacoes;

use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\TipoEmpresaRegistro;
use App\Models\CategoriaMovimentacao;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\Movimentacao;
use App\Models\MovimentacaoEstoque;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\EmpresaEntidadeQuery;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Entrada de estoque a partir da produção (sem fornecedor, frete ou ICMS de compra).
 */
final class EntradaEstoqueMovimentacaoService
{
    private const CATEGORIA_NOME = 'ENTRADA ESTOQUE';

    public function __construct(
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    /**
     * @return array{
     *     empresas_unidade: Collection<int, Empresa>,
     *     frutas: Collection<int, Fruta>,
     * }
     */
    public function opcoesFormulario(): array
    {
        $empresasUnidade = EmpresaEntidadeQuery::unidadesComEstoque()
            ->with('entidade')
            ->get()
            ->filter(fn (Empresa $e): bool => app(UnidadeNegocioAccessService::class)->canEntradaEstoque(auth()->user(), (int) $e->entidade->id))
            ->sortBy(fn (Empresa $e): string => mb_strtolower($e->nomeExibicao()))
            ->values();

        return [
            'empresas_unidade' => $empresasUnidade,
            'frutas' => Fruta::query()
                ->where('kg_por_unidade_medicao', '>', 0)
                ->orderBy('nome')
                ->get(),
        ];
    }

    /**
     * @param  array{
     *     id_empresa_origem:int,
     *     id_fruta:int,
     *     qtd_fruta_um:numeric-string|float|int|string,
     *     preco_fruta_um:numeric-string|float|int|string,
     *     observacao?:string|null,
     * }  $input
     */
    public function registrarEntrada(array $input, ?User $user = null): Movimentacao
    {
        return DB::transaction(function () use ($input, $user): Movimentacao {
            $categoriaId = CategoriaMovimentacao::idPorNome(self::CATEGORIA_NOME);

            $empresa = Empresa::query()->with('entidade')->findOrFail((int) $input['id_empresa_origem']);
            $this->assertEmpresaTipo($empresa, TipoEmpresaRegistro::UNIDADE_NEGOCIO);
            $unidade = $empresa->entidade;
            if (! $unidade instanceof UnidadeNegocio) {
                throw new InvalidArgumentException('Unidade de negócio inválida.');
            }
            if (! $unidade->possui_estoque) {
                throw new InvalidArgumentException('A unidade deve controlar estoque.');
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

            $precoUm = (float) TextoCadastro::normalizarValorMonetarioBrasileiro($input['preco_fruta_um']);
            if ($precoUm <= 0) {
                throw new InvalidArgumentException('O preço por unidade de medição deve ser maior que zero.');
            }

            $qtdKg = round($qtdUm * $kgPorUm, 2);
            if ($qtdKg <= 0) {
                throw new InvalidArgumentException('Quantidade em KG calculada inválida.');
            }

            $valorTotal = round($precoUm * $qtdUm, 2);
            $valorNfKg = round($valorTotal / $qtdKg, 2);
            $precoMedioKgLote = $valorNfKg;
            $precoMedioUmLote = $precoUm;

            $observacao = isset($input['observacao']) ? trim((string) $input['observacao']) : null;
            if ($observacao === '') {
                $observacao = null;
            }

            $estoque = $this->obterOuCriarEstoqueComLock($unidade->id, $fruta->id);
            $this->garantirPosicaoInicialSeNecessario($estoque, $unidade->id, $fruta->id);

            $posicaoAnterior = MovimentacaoEstoque::query()
                ->where('id_unidade_negocio', $unidade->id)
                ->where('id_fruta', $fruta->id)
                ->where('status_ultima_posicao', true)
                ->lockForUpdate()
                ->first();

            $saldoUmAnt = $posicaoAnterior ? (float) $posicaoAnterior->qtd_fruta_um : 0.0;
            $saldoKgAnt = $posicaoAnterior ? (float) $posicaoAnterior->qtd_fruta_kg : 0.0;
            $saldoUmNovo = round($saldoUmAnt + $qtdUm, 2);
            $saldoKgNovo = round($saldoKgAnt + $qtdKg, 2);

            $Vprev = (float) $estoque->valor_total_acumulado;
            $Vnovo = round($Vprev + ($precoMedioKgLote * $qtdKg), 2);
            $precoConsolidadoKg = $saldoKgNovo > 0 ? round($Vnovo / $saldoKgNovo, 2) : 0.0;
            $precoConsolidadoUm = round($precoConsolidadoKg * $kgPorUm, 2);

            $estoqueAntes = $this->auditoria->snapshotEstoque($estoque);
            $meAntes = $posicaoAnterior !== null ? $this->auditoria->snapshotMovimentacaoEstoque($posicaoAnterior) : null;

            if ($posicaoAnterior !== null) {
                $posicaoAnterior->forceFill(['status_ultima_posicao' => false])->save();
            }

            /** @var Movimentacao $movimentacao */
            $movimentacao = Movimentacao::query()->create([
                'id_movimentacao_estoque_old' => $posicaoAnterior?->id,
                'id_movimentacao_estoque_new' => null,
                'id_empresa_origem' => $empresa->id,
                'id_empresa_destino' => $empresa->id,
                'id_fruta' => $fruta->id,
                'valor_nf_total' => number_format($valorTotal, 2, '.', ''),
                'valor_nf_um' => number_format($precoUm, 2, '.', ''),
                'valor_nf_kg' => number_format($valorNfKg, 2, '.', ''),
                'valor_total_movimentacao' => number_format($valorTotal, 2, '.', ''),
                'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                'qtd_fruta_kg' => number_format($qtdKg, 2, '.', ''),
                'saldo_estoque_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'saldo_estoque_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_fruta_kg' => number_format($precoMedioKgLote, 2, '.', ''),
                'preco_medio_fruta_um' => number_format($precoMedioUmLote, 2, '.', ''),
                'categoria_movimentacao_id' => $categoriaId,
                'status_movimentacao_id' => StatusMovimentacao::ID_ENTRADA,
                'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
                'data_movimentacao' => now(),
                'observacao' => $observacao,
                'versao' => 1,
            ]);

            $valorTotalSnapshot = round($saldoKgNovo * $precoConsolidadoKg, 2);

            $novaPosicao = MovimentacaoEstoque::query()->create([
                'id_estoque' => $estoque->id,
                'id_unidade_negocio' => $unidade->id,
                'id_fruta' => $fruta->id,
                'movimentacao_id' => $movimentacao->id,
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                'valor_total_fruta' => number_format($valorTotalSnapshot, 2, '.', ''),
                'status_ultima_posicao' => true,
            ]);

            $movimentacao->forceFill(['id_movimentacao_estoque_new' => $novaPosicao->id])->saveQuietly();

            $estoque->forceFill([
                'qtd_fruta_kg' => number_format($saldoKgNovo, 2, '.', ''),
                'qtd_fruta_um' => number_format($saldoUmNovo, 2, '.', ''),
                'preco_medio_kg' => number_format($precoConsolidadoKg, 2, '.', ''),
                'preco_medio_um' => number_format($precoConsolidadoUm, 2, '.', ''),
                'valor_total_acumulado' => number_format($Vnovo, 2, '.', ''),
            ])->save();

            $this->auditoria->registrarRegistroEntradaEstoque(
                $movimentacao->fresh(),
                $user,
                $estoqueAntes,
                $this->auditoria->snapshotEstoque($estoque->fresh()),
                $meAntes,
                $this->auditoria->snapshotMovimentacaoEstoque($novaPosicao->fresh()),
            );

            return $movimentacao->fresh(['fruta', 'empresaOrigem', 'empresaDestino']);
        });
    }

    private function assertEmpresaTipo(Empresa $empresa, TipoEmpresaRegistro $tipo): void
    {
        if ($empresa->tipoRegistro() !== $tipo) {
            throw new InvalidArgumentException(
                sprintf('Empresa «%d» deve ser do tipo %s.', $empresa->id, $tipo->rotulo()),
            );
        }
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
