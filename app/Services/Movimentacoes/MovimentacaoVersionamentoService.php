<?php

namespace App\Services\Movimentacoes;

use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Movimentacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MovimentacaoVersionamentoService
{
    public function __construct(
        private readonly MovimentacaoAuditoriaService $auditoria,
    ) {}

    public function obterIdCadeiaRaiz(Movimentacao $movimentacao): int
    {
        return (int) ($movimentacao->movimentacao_origem_id ?? $movimentacao->id);
    }

    public function obterOrigemCadeia(Movimentacao $movimentacao): Movimentacao
    {
        return Movimentacao::query()->findOrFail($this->obterIdCadeiaRaiz($movimentacao));
    }

    public function obterVersaoAtiva(Movimentacao $movimentacao): Movimentacao
    {
        $raizId = $this->obterIdCadeiaRaiz($movimentacao);

        $ativa = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where(function ($q) use ($raizId): void {
                $q->whereKey($raizId)->orWhere('movimentacao_origem_id', $raizId);
            })
            ->orderByDesc('versao')
            ->orderByDesc('id')
            ->first();

        if ($ativa === null) {
            throw new InvalidArgumentException('Não há versão ATIVA para esta cadeia de movimentação.');
        }

        return $ativa;
    }

    public function validarPodeSubstituir(Movimentacao $movimentacao): void
    {
        if ($movimentacao->status_registro !== MovimentacaoStatusRegistro::ATIVO->value) {
            throw new InvalidArgumentException(
                'Somente movimentações com status ATIVO podem receber nova versão.',
            );
        }
    }

    public function marcarComoSubstituida(
        Movimentacao $movimentacao,
        Movimentacao $novaVersao,
        ?string $motivo = null,
    ): void {
        $movimentacao->forceFill([
            'status_registro' => MovimentacaoStatusRegistro::SUBSTITUIDO->value,
            'substituida_por_id' => $novaVersao->id,
            'substituida_em' => now(),
            'motivo_substituicao' => $motivo,
        ])->saveQuietly();
    }

    /**
     * Cria nova linha em {@see Movimentacao}, marca a atual como SUBSTITUIDA e registra auditoria.
     *
     * @param  array<string, mixed>  $novaLinhaAtributos  Atributos persistíveis da nova linha (sem id/timestamps de versão).
     */
    public function criarNovaVersao(
        Movimentacao $movimentacaoAtiva,
        array $novaLinhaAtributos,
        ?string $motivo = null,
        ?User $user = null,
    ): Movimentacao {
        return DB::transaction(function () use ($movimentacaoAtiva, $novaLinhaAtributos, $motivo, $user): Movimentacao {
            $atual = Movimentacao::query()->whereKey($movimentacaoAtiva->id)->lockForUpdate()->firstOrFail();
            $this->validarPodeSubstituir($atual);

            $raiz = $this->obterOrigemCadeia($atual);
            $raizId = $raiz->id;

            Movimentacao::query()
                ->where(function ($q) use ($raizId): void {
                    $q->whereKey($raizId)->orWhere('movimentacao_origem_id', $raizId);
                })
                ->lockForUpdate()
                ->get();

            $ativas = Movimentacao::query()
                ->vigentesParaCalculo()
                ->where(function ($q) use ($raizId): void {
                    $q->whereKey($raizId)->orWhere('movimentacao_origem_id', $raizId);
                })
                ->count();

            if ($ativas !== 1) {
                throw new InvalidArgumentException(
                    'Inconsistência de versionamento: era esperada exatamente uma versão ATIVA na cadeia.',
                );
            }

            $dadosAntesAuditoria = $this->auditoria->snapshotVersao($atual);

            $atual->forceFill([
                'status_registro' => MovimentacaoStatusRegistro::SUBSTITUIDO->value,
                'substituida_por_id' => null,
                'substituida_em' => now(),
                'motivo_substituicao' => $motivo,
            ])->saveQuietly();

            $novaLinhaAtributos['versao'] = (int) $atual->versao + 1;
            $novaLinhaAtributos['movimentacao_origem_id'] = $raizId;
            $novaLinhaAtributos['status_registro'] = MovimentacaoStatusRegistro::ATIVO->value;
            $novaLinhaAtributos['substituida_por_id'] = null;
            $novaLinhaAtributos['motivo_substituicao'] = null;
            $novaLinhaAtributos['substituida_em'] = null;
            $novaLinhaAtributos['data_movimentacao'] = $raiz->data_movimentacao;

            /** @var Movimentacao $nova */
            $nova = Movimentacao::query()->create($novaLinhaAtributos);

            $atual->forceFill(['substituida_por_id' => $nova->id])->saveQuietly();

            $this->auditoria->registrarSubstituicaoDeVersao(
                $atual->fresh(),
                $nova,
                $user,
                $motivo,
                $dadosAntesAuditoria,
            );

            return $nova->fresh();
        });
    }
}
