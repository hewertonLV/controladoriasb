<?php

namespace App\Services\Fretes;

use App\Enums\FreteStatusSituacao;
use App\Models\Frete;
use App\Models\User;
use Illuminate\Support\Carbon;

final class FreteCalendarioService
{
    /**
     * @return array{
     *     mes: string,
     *     label: string,
     *     inicio: string,
     *     fim: string,
     *     eventos: list<array<string, mixed>>
     * }
     */
    public function payloadParaMes(?string $mes, User $user): array
    {
        [$inicio, $fim, $mesReferencia] = $this->intervaloMes($mes);

        $fretes = Frete::query()
            ->with('veiculo')
            ->whereBetween('created_at', [$inicio, $fim])
            ->orderBy('created_at')
            ->orderBy('nome')
            ->get();

        $podeEditar = $user->can('fretes.editar');

        return [
            'mes' => $mesReferencia,
            'label' => $inicio->translatedFormat('F/Y'),
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'eventos' => $fretes->map(fn (Frete $frete): array => $this->mapearEvento($frete, $podeEditar))->all(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function intervaloMes(?string $mes): array
    {
        $normalizado = is_string($mes) && preg_match('/^\d{4}-\d{2}$/', trim($mes)) === 1
            ? trim($mes)
            : now()->format('Y-m');

        $referencia = Carbon::createFromFormat('Y-m', $normalizado)->startOfMonth();

        return [
            $referencia->copy()->startOfMonth()->startOfDay(),
            $referencia->copy()->endOfMonth()->endOfDay(),
            $referencia->format('Y-m'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearEvento(Frete $frete, bool $podeEditar): array
    {
        $aberta = $frete->status_situacao === FreteStatusSituacao::ABERTA->value;
        $data = $frete->created_at?->toDateString() ?? now()->toDateString();
        $veiculo = $frete->veiculo
            ? trim($frete->veiculo->id_sbs.' — '.$frete->veiculo->nome)
            : '—';

        return [
            'id' => (string) $frete->id,
            'title' => $frete->nome,
            'start' => $data,
            'allDay' => true,
            'classNames' => $aberta
                ? ['bg-success-subtle', 'text-success']
                : ['bg-secondary-subtle', 'text-secondary'],
            'extendedProps' => [
                'valor' => number_format((float) $frete->valor, 2, ',', '.'),
                'valor_fruta_kg' => number_format((float) $frete->valor_fruta_kg, 2, ',', '.'),
                'veiculo' => $veiculo,
                'situacao' => $frete->status_situacao,
                'situacao_label' => $aberta ? 'Aberta' : 'Encerrada',
                'criado_em' => $frete->created_at?->format('d/m/Y H:i') ?? '—',
                'descricao' => $frete->descricao,
                'editar_url' => $podeEditar ? route('admin.fretes.edit', $frete) : null,
            ],
        ];
    }
}
